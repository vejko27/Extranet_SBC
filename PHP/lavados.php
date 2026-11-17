<?php
require_once __DIR__ . '/lavados_helper.php';
$paginaActiva = 'lavados';
$tituloPagina = 'Lavados de tractos';
$puedeCrear = puede('Tractos', 'crear');
$puedeEditar = puede('Tractos', 'editar');
$puedeEliminar = puede('Tractos', 'eliminar');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$tractoId = filter_var($_GET['tracto'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$editarId = filter_var($_GET['editar'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$fecha = '';
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
    $editarId = filter_var($_POST['lavado_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if (!in_array($accion, ['crear', 'editar', 'eliminar'], true) || ($accion !== 'crear' && !$editarId)) {
        http_response_code(400);
        exit('Acción no válida.');
    }
    exigirPermiso('Tractos', $accion);
    if ($accion === 'eliminar') {
        $st = lavadoPreparar('DELETE FROM lavados_tractos WHERE id = ? AND tracto_id = ?');
        $st->bind_param('ii', $editarId, $tractoId);
        lavadoEjecutar($st);
        $eliminado = $st->affected_rows;
        $st->close();
        if (!$eliminado) {
            http_response_code(404);
            exit('Lavado no encontrado.');
        }
        $_SESSION['lavados_mensaje'] = 'Lavado y facturas eliminados correctamente.';
        header('Location: lavados.php?tracto=' . $tractoId . '#historial-lavados');
        exit;
    }
    if ($accion === 'crear') {
        $editarId = 0;
    }
    $fecha = is_string($_POST['fecha_lavado'] ?? null) ? $_POST['fecha_lavado'] : '';
    $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        $error = 'Selecciona una fecha de lavado válida.';
    }
    $pdf = $error === '' ? lavadoArchivo('factura_pdf', 'pdf', $error) : null;
    $xml = $error === '' ? lavadoArchivo('factura_xml', 'xml', $error) : null;
    if ($error === '') {
        if (!$conexion->begin_transaction()) {
            lavadoFalloBD($conexion->error);
        }
        if ($editarId) {
            $st = lavadoPreparar('SELECT id FROM lavados_tractos WHERE id = ? AND tracto_id = ? FOR UPDATE');
            $st->bind_param('ii', $editarId, $tractoId);
            lavadoEjecutar($st);
            $existe = lavadoResultado($st)->fetch_assoc();
            $st->close();
            if (!$existe) {
                $conexion->rollback();
                http_response_code(404);
                exit('Lavado no encontrado.');
            }
            $st = lavadoPreparar('UPDATE lavados_tractos SET fecha_lavado = ? WHERE id = ? AND tracto_id = ?');
            $st->bind_param('sii', $fecha, $editarId, $tractoId);
            lavadoEjecutar($st);
            $st->close();
            $lavadoId = $editarId;
        } else {
            $st = lavadoPreparar('INSERT INTO lavados_tractos (tracto_id, fecha_lavado) VALUES (?, ?)');
            $st->bind_param('is', $tractoId, $fecha);
            lavadoEjecutar($st);
            $lavadoId = (int)$conexion->insert_id;
            $st->close();
        }
        if ($pdf !== null) {
            lavadoGuardarArchivo($lavadoId, 'pdf', $pdf);
        }
        if ($xml !== null) {
            lavadoGuardarArchivo($lavadoId, 'xml', $xml);
        }
        if (!$conexion->commit()) {
            lavadoFalloBD($conexion->error);
        }
        $_SESSION['lavados_mensaje'] = 'Lavado guardado correctamente.';
        header('Location: lavados.php?tracto=' . $tractoId . '#historial-lavados');
        exit;
    }
} elseif ($editarId && $tracto) {
    exigirPermiso('Tractos', 'editar');
    $st = lavadoPreparar('SELECT fecha_lavado FROM lavados_tractos WHERE id = ? AND tracto_id = ?');
    $st->bind_param('ii', $editarId, $tractoId);
    lavadoEjecutar($st);
    $lavado = lavadoResultado($st)->fetch_assoc();
    $st->close();
    if (!$lavado) {
        http_response_code(404);
        exit('Lavado no encontrado.');
    }
    $fecha = $lavado['fecha_lavado'];
} elseif ($editarId) {
    http_response_code(400);
    exit('Selecciona un tracto.');
}

$resultado = $conexion->query("SELECT f.id, f.nombre, f.placa, MAX(l.fecha_lavado) AS ultimo_lavado
    FROM inventario_flota f LEFT JOIN lavados_tractos l ON l.tracto_id = f.id
    WHERE f.tipo = 'tractos' GROUP BY f.id, f.nombre, f.placa ORDER BY f.id");
if (!$resultado) {
    lavadoFalloBD($conexion->error);
}
$tractos = $resultado->fetch_all(MYSQLI_ASSOC);
$historial = [];
if ($tracto) {
    $st = lavadoPreparar("SELECT l.id, l.fecha_lavado,
        MAX(CASE WHEN f.tipo = 'pdf' THEN 1 ELSE 0 END) AS tiene_pdf,
        MAX(CASE WHEN f.tipo = 'xml' THEN 1 ELSE 0 END) AS tiene_xml
        FROM lavados_tractos l LEFT JOIN lavados_facturas f ON f.lavado_id = l.id
        WHERE l.tracto_id = ? GROUP BY l.id, l.fecha_lavado ORDER BY l.fecha_lavado DESC, l.id DESC");
    $st->bind_param('i', $tractoId);
    lavadoEjecutar($st);
    $historial = lavadoResultado($st)->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
$mensaje = $_SESSION['lavados_mensaje'] ?? '';
unset($_SESSION['lavados_mensaje']);
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div><h1>Lavados de tractos</h1><p class="subtitulo">Consulta y registra los lavados y sus facturas.</p></div>
</div>
<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= lavadoEscapar($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err" role="alert"><?= lavadoEscapar($error) ?></div><?php endif; ?>

<div class="card">
    <h2>Tractos</h2>
    <div class="tabla-wrap">
        <table class="flota-tabla">
            <tr><th>ID del tracto</th><th>Tracto</th><th>Fecha del último lavado</th><th>Acciones</th></tr>
            <?php foreach ($tractos as $fila): ?>
            <tr>
                <td data-label="ID del tracto"><?= (int)$fila['id'] ?></td>
                <td data-label="Tracto"><?= lavadoEscapar($fila['nombre'] . ' (' . $fila['placa'] . ')') ?></td>
                <td data-label="Fecha del último lavado"><?= $fila['ultimo_lavado'] ? lavadoEscapar(date('d/m/Y', strtotime($fila['ultimo_lavado']))) : '' ?></td>
                <td data-label="Acciones" class="celda-acciones"><a class="btn" href="lavados.php?tracto=<?= (int)$fila['id'] ?>#historial-lavados">Historial y registrar</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$tractos): ?><p>No hay tractos registrados.</p><?php endif; ?>
    </div>
</div>

<?php if ($tracto): ?>
<div class="card" id="historial-lavados">
    <h2>Historial: <?= lavadoEscapar($tracto['nombre'] . ' (' . $tracto['placa'] . ')') ?></h2>
    <?php if (!$historial): ?><p>Aún no hay lavados registrados para este tracto.</p><?php else: ?>
    <div class="tabla-wrap">
        <table class="flota-tabla">
            <tr><th>Fecha de lavado</th><th>PDF</th><th>XML</th><th>Acciones</th></tr>
            <?php foreach ($historial as $fila): ?>
            <tr>
                <td data-label="Fecha de lavado"><?= lavadoEscapar(date('d/m/Y', strtotime($fila['fecha_lavado']))) ?></td>
                <td data-label="PDF"><?php if ($fila['tiene_pdf']): ?><a href="lavado_factura.php?id=<?= (int)$fila['id'] ?>&amp;tipo=pdf">Descargar PDF</a><?php else: ?>Sin adjuntar<?php endif; ?></td>
                <td data-label="XML"><?php if ($fila['tiene_xml']): ?><a href="lavado_factura.php?id=<?= (int)$fila['id'] ?>&amp;tipo=xml">Descargar XML</a><?php else: ?>Sin adjuntar<?php endif; ?></td>
                <td data-label="Acciones" class="celda-acciones">
                    <?php if ($puedeEditar): ?><a class="btn btn-sec" href="lavados.php?tracto=<?= $tractoId ?>&amp;editar=<?= (int)$fila['id'] ?>#formulario-lavado">Editar o adjuntar</a><?php endif; ?>
                    <?php if ($puedeEliminar): ?>
                    <form method="post" class="form-inline" onsubmit="return confirm('¿Eliminar este lavado y sus facturas PDF y XML? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="csrf" value="<?= lavadoEscapar($_SESSION['csrf']) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="lavado_id" value="<?= (int)$fila['id'] ?>">
                        <button type="submit" class="btn-peligro" aria-label="Borrar lavado del <?= lavadoEscapar(date('d/m/Y', strtotime($fila['fecha_lavado']))) ?>">Borrar</button>
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
<div class="card" id="formulario-lavado">
    <h2><?= $editarId ? 'Editar lavado o adjuntar factura' : 'Registrar lavado' ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= lavadoEscapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="<?= $editarId ? 'editar' : 'crear' ?>">
        <?php if ($editarId): ?><input type="hidden" name="lavado_id" value="<?= $editarId ?>"><?php endif; ?>
        <div class="form-grupo"><label for="fecha-lavado">Fecha de lavado</label>
            <input id="fecha-lavado" type="date" name="fecha_lavado" value="<?= lavadoEscapar($fecha) ?>" required>
        </div>
        <div class="form-grupo"><label for="factura-pdf">Factura PDF (opcional, máximo 10 MB)</label>
            <input id="factura-pdf" type="file" name="factura_pdf" accept=".pdf,application/pdf">
        </div>
        <div class="form-grupo"><label for="factura-xml">Factura XML (opcional, máximo 10 MB)</label>
            <input id="factura-xml" type="file" name="factura_xml" accept=".xml,application/xml,text/xml">
        </div>
        <?php if ($editarId): ?><p>Solo se reemplazan los archivos que selecciones.</p><?php endif; ?>
        <div class="form-fila flota-acciones">
            <button type="submit">Guardar lavado</button>
            <?php if ($editarId): ?><a class="btn btn-sec" href="lavados.php?tracto=<?= $tractoId ?>#formulario-lavado">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout_fin.php'; ?>
