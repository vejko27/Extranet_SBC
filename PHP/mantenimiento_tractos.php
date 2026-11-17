<?php
require_once __DIR__ . '/lavados_helper.php';
$paginaActiva = 'mantenimiento_tractos';
$tituloPagina = 'Mantenimiento de tractos';
$puedeCrear = puede('Tractos', 'crear');
$puedeEditar = puede('Tractos', 'editar');
$puedeEliminar = puede('Tractos', 'eliminar');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$tractoId = filter_var($_GET['tracto'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$editarId = filter_var($_GET['editar'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$fecha = '';
$observaciones = '';
$error = '';
$tracto = null;

if ($tractoId) {
    $st = lavadoPreparar("SELECT id, nombre, placa FROM inventario_flota WHERE id = ? AND tipo = 'tractos'");
    $st->bind_param('i', $tractoId);
    lavadoEjecutar($st);
    $tracto = lavadoResultado($st)->fetch_assoc();
    $st->close();
    if (!$tracto) {
        http_response_code(404);
        exit('Tracto no encontrado.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tracto) {
        http_response_code(400);
        exit('Selecciona un tracto.');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        exit('Sesión inválida o archivos demasiado grandes. Recarga la página y verifica el límite de subida.');
    }
    $accion = $_POST['accion'] ?? '';
    $editarId = filter_var($_POST['mantenimiento_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if (!in_array($accion, ['crear', 'editar', 'eliminar'], true) || ($accion !== 'crear' && !$editarId)) {
        http_response_code(400);
        exit('Acción no válida.');
    }
    exigirPermiso('Tractos', $accion);
    if ($accion === 'eliminar') {
        $st = lavadoPreparar('DELETE FROM mantenimientos_tractos WHERE id = ? AND tracto_id = ?');
        $st->bind_param('ii', $editarId, $tractoId);
        lavadoEjecutar($st);
        $eliminado = $st->affected_rows;
        $st->close();
        if (!$eliminado) {
            http_response_code(404);
            exit('Mantenimiento no encontrado.');
        }
        $_SESSION['mantenimiento_mensaje'] = 'Mantenimiento y facturas eliminados correctamente.';
        header('Location: mantenimiento_tractos.php?tracto=' . $tractoId . '#historial-mantenimientos');
        exit;
    }
    if ($accion === 'crear') {
        $editarId = 0;
    }
    $fecha = is_string($_POST['fecha_mantenimiento'] ?? null) ? $_POST['fecha_mantenimiento'] : '';
    $observaciones = is_string($_POST['observaciones'] ?? null) ? trim($_POST['observaciones']) : '';
    $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        $error = 'Selecciona una fecha de mantenimiento válida.';
    } elseif ($observaciones === '' || !mb_check_encoding($observaciones, 'UTF-8') || mb_strlen($observaciones, 'UTF-8') > 2000) {
        $error = 'Indica qué se hizo y por qué en las observaciones (máximo 2000 caracteres).';
    }
    $pdf = $error === '' ? lavadoArchivo('factura_pdf', 'pdf', $error) : null;
    $xml = $error === '' ? lavadoArchivo('factura_xml', 'xml', $error) : null;
    if ($error === '') {
        if (!$conexion->begin_transaction()) {
            lavadoFalloBD($conexion->error);
        }
        if ($editarId) {
            $st = lavadoPreparar('SELECT id FROM mantenimientos_tractos WHERE id = ? AND tracto_id = ? FOR UPDATE');
            $st->bind_param('ii', $editarId, $tractoId);
            lavadoEjecutar($st);
            $existe = lavadoResultado($st)->fetch_assoc();
            $st->close();
            if (!$existe) {
                $conexion->rollback();
                http_response_code(404);
                exit('Mantenimiento no encontrado.');
            }
            $st = lavadoPreparar('UPDATE mantenimientos_tractos SET fecha_mantenimiento = ?, observaciones = ? WHERE id = ? AND tracto_id = ?');
            $st->bind_param('ssii', $fecha, $observaciones, $editarId, $tractoId);
            lavadoEjecutar($st);
            $st->close();
            $mantenimientoId = $editarId;
        } else {
            $st = lavadoPreparar('INSERT INTO mantenimientos_tractos (tracto_id, fecha_mantenimiento, observaciones) VALUES (?, ?, ?)');
            $st->bind_param('iss', $tractoId, $fecha, $observaciones);
            lavadoEjecutar($st);
            $mantenimientoId = (int)$conexion->insert_id;
            $st->close();
        }
        if ($pdf !== null) {
            lavadoGuardarArchivo($mantenimientoId, 'pdf', $pdf, 'mantenimientos');
        }
        if ($xml !== null) {
            lavadoGuardarArchivo($mantenimientoId, 'xml', $xml, 'mantenimientos');
        }
        if (!$conexion->commit()) {
            lavadoFalloBD($conexion->error);
        }
        $_SESSION['mantenimiento_mensaje'] = 'Mantenimiento guardado correctamente.';
        header('Location: mantenimiento_tractos.php?tracto=' . $tractoId . '#historial-mantenimientos');
        exit;
    }
} elseif ($editarId && $tracto) {
    exigirPermiso('Tractos', 'editar');
    $st = lavadoPreparar('SELECT fecha_mantenimiento, observaciones FROM mantenimientos_tractos WHERE id = ? AND tracto_id = ?');
    $st->bind_param('ii', $editarId, $tractoId);
    lavadoEjecutar($st);
    $mantenimiento = lavadoResultado($st)->fetch_assoc();
    $st->close();
    if (!$mantenimiento) {
        http_response_code(404);
        exit('Mantenimiento no encontrado.');
    }
    $fecha = $mantenimiento['fecha_mantenimiento'];
    $observaciones = $mantenimiento['observaciones'];
} elseif ($editarId) {
    http_response_code(400);
    exit('Selecciona un tracto.');
}

$resultado = $conexion->query("SELECT f.id, f.nombre, f.placa, m.fecha_mantenimiento, m.observaciones
    FROM inventario_flota f
    LEFT JOIN mantenimientos_tractos m ON m.id = (
        SELECT ultimo.id FROM mantenimientos_tractos ultimo
        WHERE ultimo.tracto_id = f.id ORDER BY ultimo.fecha_mantenimiento DESC, ultimo.id DESC LIMIT 1
    )
    WHERE f.tipo = 'tractos' ORDER BY f.id");
if (!$resultado) {
    lavadoFalloBD($conexion->error);
}
$tractos = $resultado->fetch_all(MYSQLI_ASSOC);
$historial = [];
if ($tracto) {
    $st = lavadoPreparar("SELECT m.id, m.fecha_mantenimiento, m.observaciones,
        EXISTS(SELECT 1 FROM mantenimientos_facturas f WHERE f.mantenimiento_id = m.id AND f.tipo = 'pdf') AS tiene_pdf,
        EXISTS(SELECT 1 FROM mantenimientos_facturas f WHERE f.mantenimiento_id = m.id AND f.tipo = 'xml') AS tiene_xml
        FROM mantenimientos_tractos m WHERE m.tracto_id = ?
        ORDER BY m.fecha_mantenimiento DESC, m.id DESC");
    $st->bind_param('i', $tractoId);
    lavadoEjecutar($st);
    $historial = lavadoResultado($st)->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
$mensaje = $_SESSION['mantenimiento_mensaje'] ?? '';
unset($_SESSION['mantenimiento_mensaje']);
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div><h1>Mantenimiento de tractos</h1><p class="subtitulo">Consulta y registra los mantenimientos y sus facturas.</p></div>
</div>
<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= lavadoEscapar($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err" role="alert"><?= lavadoEscapar($error) ?></div><?php endif; ?>

<div class="card">
    <h2>Tractos</h2>
    <div class="tabla-wrap">
        <table class="flota-tabla">
            <tr><th>ID del tracto</th><th>Tracto</th><th>Último mantenimiento</th><th>Observaciones</th><th>Acciones</th></tr>
            <?php foreach ($tractos as $fila): ?>
            <tr>
                <td data-label="ID del tracto"><?= (int)$fila['id'] ?></td>
                <td data-label="Tracto"><?= lavadoEscapar($fila['nombre'] . ' (' . $fila['placa'] . ')') ?></td>
                <td data-label="Último mantenimiento"><?= $fila['fecha_mantenimiento'] ? lavadoEscapar(date('d/m/Y', strtotime($fila['fecha_mantenimiento']))) : '' ?></td>
                <td data-label="Observaciones"><div class="flota-observaciones"><?= lavadoEscapar($fila['observaciones'] ?? '') ?></div></td>
                <td data-label="Acciones" class="celda-acciones"><a class="btn" href="mantenimiento_tractos.php?tracto=<?= (int)$fila['id'] ?>#historial-mantenimientos">Historial y registrar</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$tractos): ?><p>No hay tractos registrados.</p><?php endif; ?>
    </div>
</div>

<?php if ($tracto): ?>
<div class="card" id="historial-mantenimientos">
    <h2>Historial: <?= lavadoEscapar($tracto['nombre'] . ' (' . $tracto['placa'] . ')') ?></h2>
    <?php if (!$historial): ?><p>Aún no hay mantenimientos registrados para este tracto.</p><?php else: ?>
    <div class="tabla-wrap">
        <table class="flota-tabla">
            <tr><th>Fecha</th><th>Observaciones</th><th>PDF</th><th>XML</th><th>Acciones</th></tr>
            <?php foreach ($historial as $fila): ?>
            <tr>
                <td data-label="Fecha"><?= lavadoEscapar(date('d/m/Y', strtotime($fila['fecha_mantenimiento']))) ?></td>
                <td data-label="Observaciones"><div class="flota-observaciones"><?= lavadoEscapar($fila['observaciones']) ?></div></td>
                <td data-label="PDF"><?php if ($fila['tiene_pdf']): ?><a href="lavado_factura.php?id=<?= (int)$fila['id'] ?>&amp;tipo=pdf&amp;origen=mantenimientos">Descargar PDF</a><?php else: ?>Sin adjuntar<?php endif; ?></td>
                <td data-label="XML"><?php if ($fila['tiene_xml']): ?><a href="lavado_factura.php?id=<?= (int)$fila['id'] ?>&amp;tipo=xml&amp;origen=mantenimientos">Descargar XML</a><?php else: ?>Sin adjuntar<?php endif; ?></td>
                <td data-label="Acciones" class="celda-acciones">
                    <?php if ($puedeEditar): ?><a class="btn btn-sec" href="mantenimiento_tractos.php?tracto=<?= $tractoId ?>&amp;editar=<?= (int)$fila['id'] ?>#formulario-mantenimiento">Editar o adjuntar</a><?php endif; ?>
                    <?php if ($puedeEliminar): ?>
                    <form method="post" class="form-inline" onsubmit="return confirm('¿Eliminar este mantenimiento y sus facturas PDF y XML? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="csrf" value="<?= lavadoEscapar($_SESSION['csrf']) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="mantenimiento_id" value="<?= (int)$fila['id'] ?>">
                        <button type="submit" class="btn-peligro" aria-label="Borrar mantenimiento del <?= lavadoEscapar(date('d/m/Y', strtotime($fila['fecha_mantenimiento']))) ?>">Borrar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($editarId ? $puedeEditar : $puedeCrear): ?>
<div class="card" id="formulario-mantenimiento">
    <h2><?= $editarId ? 'Editar mantenimiento o adjuntar factura' : 'Registrar mantenimiento' ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= lavadoEscapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="<?= $editarId ? 'editar' : 'crear' ?>">
        <?php if ($editarId): ?><input type="hidden" name="mantenimiento_id" value="<?= $editarId ?>"><?php endif; ?>
        <div class="form-grupo"><label for="fecha-mantenimiento">Fecha de mantenimiento</label>
            <input id="fecha-mantenimiento" type="date" name="fecha_mantenimiento" value="<?= lavadoEscapar($fecha) ?>" required>
        </div>
        <div class="form-grupo"><label for="observaciones-mantenimiento">Observaciones: ¿qué se hizo y por qué?</label>
            <textarea class="flota-observaciones-input" id="observaciones-mantenimiento" name="observaciones" rows="4" maxlength="2000" required><?= lavadoEscapar($observaciones) ?></textarea>
        </div>
        <div class="form-grupo"><label for="mantenimiento-pdf">Factura PDF (opcional, máximo 10 MB)</label>
            <input id="mantenimiento-pdf" type="file" name="factura_pdf" accept=".pdf,application/pdf">
        </div>
        <div class="form-grupo"><label for="mantenimiento-xml">Factura XML (opcional, máximo 10 MB)</label>
            <input id="mantenimiento-xml" type="file" name="factura_xml" accept=".xml,application/xml,text/xml">
        </div>
        <?php if ($editarId): ?><p>Solo se reemplazan los archivos que selecciones.</p><?php endif; ?>
        <div class="form-fila flota-acciones">
            <button type="submit">Guardar mantenimiento</button>
            <?php if ($editarId): ?><a class="btn btn-sec" href="mantenimiento_tractos.php?tracto=<?= $tractoId ?>#formulario-mantenimiento">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout_fin.php'; ?>
