<?php
$paginaActiva = 'sueldo_mantenimiento';
$tituloPagina = 'Mantenimiento de personal - Sueldo';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sueldo_helper.php';

exigirPermiso('Sueldo', 'ver');
header('Cache-Control: no-store');
$puedeEditarSueldo = puede('Sueldo', 'editar');
$conexion->set_charset('utf8mb4');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$periodo = sueldoPeriodo($esPost ? ($_POST['periodo'] ?? null) : ($_GET['periodo'] ?? (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m')));
$personalRaw = $esPost ? ($_POST['personal'] ?? '') : ($_GET['personal'] ?? '');
if (!is_string($personalRaw) || ($personalRaw !== '' && (!preg_match('/^[1-9]\d{0,9}$/D', $personalRaw) || (float)$personalRaw > 2147483647))) {
    http_response_code(400);
    exit('El trabajador no es válido.');
}
$personalId = $personalRaw === '' ? 0 : (int)$personalRaw;
$versionRaw = $esPost ? ($_POST['version'] ?? null) : '0';
if (!is_string($versionRaw) || !preg_match('/^(?:0|[1-9]\d{0,9})$/D', $versionRaw) || (float)$versionRaw > 4294967295) {
    http_response_code(400);
    exit('La versión del registro no es válida.');
}
$error = '';
$valoresFormulario = [];

if ($esPost) {
    if (!$puedeEditarSueldo) {
        http_response_code(403);
        exit('Acceso denegado');
    }
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Sesión inválida. Recarga la página.');
    }
    if (!$personalId) {
        http_response_code(400);
        exit('El trabajador no es válido.');
    }

    $estadoPrestamo = $_POST['prestamo_estado'] ?? null;
    if (!is_string($estadoPrestamo) || !in_array($estadoPrestamo, ['', 'aplica', 'no_aplica'], true)) {
        http_response_code(400);
        exit('El estado del préstamo no es válido.');
    }
    foreach (SUELDO_CAMPOS as $campo => $etiqueta) {
        $valor = $campo === 'prestamo' && $estadoPrestamo === 'no_aplica' ? '' : ($_POST[$campo] ?? null);
        if (!is_string($valor)) {
            http_response_code(400);
            exit('Los datos de sueldo están incompletos.');
        }
        $valoresFormulario[$campo] = trim($valor);
    }
    if ($estadoPrestamo === 'aplica' && $valoresFormulario['prestamo'] === '') {
        $error = 'Ingresa el importe del préstamo.';
    } elseif ($estadoPrestamo === '' && $valoresFormulario['prestamo'] !== '') {
        $error = 'Selecciona Con préstamo o borra el importe para dejarlo sin especificar.';
    }
    foreach (SUELDO_IMPORTES as $campo => $etiqueta) {
        $valor = $valoresFormulario[$campo];
        if ($error === '' && $valor !== '' && !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $valor)) {
            $error = 'Los importes deben ser positivos o cero, con hasta 10 dígitos enteros y 2 decimales.';
            break;
        }
    }
    $fechaPago = $valoresFormulario['fecha_pago'];
    if ($error === '' && $fechaPago !== '') {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaPago);
        if (!$fecha || $fecha->format('Y-m-d') !== $fechaPago || $fechaPago < '1000-01-01') {
            $error = 'La fecha de pago no es válida.';
        }
    }
    if ($error === '' && $valoresFormulario['tipo_sueldo'] !== '' && !in_array($valoresFormulario['tipo_sueldo'], SUELDO_TIPOS, true)) {
        $error = 'El tipo de sueldo debe ser Quincenal o Mensual.';
    }

    if ($error === '') {
        if (!$conexion->begin_transaction()) {
            sueldoErrorBD($conexion->error);
        }
        $comprobar = $conexion->prepare("SELECT id FROM personal WHERE id = ? AND estado = 'activo' FOR UPDATE");
        if (!$comprobar) {
            $conexion->rollback();
            sueldoErrorBD($conexion->error);
        }
        $comprobar->bind_param('i', $personalId);
        if (!$comprobar->execute() || !($resultado = $comprobar->get_result())) {
            $conexion->rollback();
            sueldoErrorBD($comprobar->error);
        }
        $existe = (bool)$resultado->fetch_assoc();
        $comprobar->close();
        if (!$existe) {
            $conexion->rollback();
            http_response_code(404);
            exit('El trabajador ya no está activo o no existe.');
        }

        $fechaPeriodo = $periodo . '-01';
        $revision = $conexion->prepare('SELECT version FROM sueldo_mensual WHERE personal_id = ? AND periodo = ? FOR UPDATE');
        if (!$revision) {
            $conexion->rollback();
            sueldoErrorBD($conexion->error);
        }
        $revision->bind_param('is', $personalId, $fechaPeriodo);
        if (!$revision->execute() || !($resultadoRevision = $revision->get_result())) {
            $conexion->rollback();
            sueldoErrorBD($revision->error);
        }
        $registroActual = $resultadoRevision->fetch_assoc();
        $revision->close();
        if ((string)($registroActual['version'] ?? '0') !== $versionRaw) {
            $conexion->rollback();
            $error = 'Otra persona modificó estos datos. Copia los cambios que necesites y vuelve a abrir el registro para revisar la versión actual.';
        }
    }

    if ($error === '') {
        $valores = [];
        foreach (SUELDO_CAMPOS as $campo => $etiqueta) {
            $valores[$campo] = $valoresFormulario[$campo] === '' ? null : $valoresFormulario[$campo];
        }
        $estadoPrestamoGuardado = $estadoPrestamo === '' ? null : $estadoPrestamo;
        $guardar = $conexion->prepare("INSERT INTO sueldo_mensual
            (personal_id, periodo, ingresos, descuentos, quincena, prestamo, prestamo_estado, neto, aporte, sueldo_basico, sueldo_diario, sueldo_hora, fecha_pago, tipo_sueldo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ingresos = VALUES(ingresos), descuentos = VALUES(descuentos),
                quincena = VALUES(quincena), prestamo = VALUES(prestamo), prestamo_estado = VALUES(prestamo_estado), neto = VALUES(neto), aporte = VALUES(aporte),
                sueldo_basico = VALUES(sueldo_basico), sueldo_diario = VALUES(sueldo_diario),
                sueldo_hora = VALUES(sueldo_hora), fecha_pago = VALUES(fecha_pago), tipo_sueldo = VALUES(tipo_sueldo),
                version = version + 1");
        if (!$guardar) {
            $conexion->rollback();
            sueldoErrorBD($conexion->error);
        }
        $guardar->bind_param('isssssssssssss', $personalId, $fechaPeriodo, $valores['ingresos'], $valores['descuentos'],
            $valores['quincena'], $valores['prestamo'], $estadoPrestamoGuardado, $valores['neto'], $valores['aporte'], $valores['sueldo_basico'],
            $valores['sueldo_diario'], $valores['sueldo_hora'], $valores['fecha_pago'], $valores['tipo_sueldo']);
        if (!$guardar->execute()) {
            $detalle = $guardar->error;
            $guardar->close();
            $conexion->rollback();
            sueldoErrorBD($detalle);
        }
        $guardar->close();
        if (!$conexion->commit()) {
            sueldoErrorBD($conexion->error);
        }
        $_SESSION['sueldo_mensaje'] = 'Datos de sueldo guardados para el período seleccionado.';
        header('Location: sueldo_mantenimiento.php?periodo=' . urlencode($periodo));
        exit;
    }
}

$trabajadores = sueldoTrabajadores($conexion, $periodo);
$seleccionado = null;
if ($personalId && $puedeEditarSueldo) {
    foreach ($trabajadores as $trabajador) {
        if ((int)$trabajador['id'] === $personalId) {
            $seleccionado = $trabajador;
            break;
        }
    }
    if (!$seleccionado) {
        http_response_code(404);
        exit('El trabajador ya no está activo o no existe.');
    }
}
$mensaje = $_SESSION['sueldo_mensaje'] ?? '';
unset($_SESSION['sueldo_mensaje']);
$esMantenimiento = true;
require __DIR__ . '/layout_inicio.php';
?>
<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Mantenimiento de personal</h1>
        <p class="subtitulo">Registro mensual de datos de sueldo del personal activo.</p>
    </div>
</div>

<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= sueldoEscapar($mensaje) ?></div><?php endif; ?>
<div class="card">
    <form method="get" class="sueldo-periodo">
        <label for="periodo">Período</label>
        <input id="periodo" type="month" name="periodo" min="1900-01" max="9999-12" value="<?= sueldoEscapar($periodo) ?>" required>
        <button type="submit" class="btn-sec">Consultar</button>
    </form>
    <p class="subtitulo sueldo-ayuda">Selecciona Editar para llenar los datos de un trabajador. Los importes se ingresan manualmente; los campos vacíos no equivalen a cero.</p>
    <p class="subtitulo sueldo-ayuda">Los cambios de esta plantilla solo se guardan para el mes seleccionado.</p>
    <p class="subtitulo sueldo-ayuda">Los registros de trabajadores dados de baja se conservan, pero no aparecen en esta lista.</p>
    <?php if (!$puedeEditarSueldo): ?><p class="alerta">Solo tienes permiso de consulta; se requiere Editar de Sueldo para registrar datos.</p><?php endif; ?>
    <?php require __DIR__ . '/sueldo_tabla.php'; ?>
</div>

<?php if ($seleccionado): ?>
<div class="card sueldo-formulario" id="editar-sueldo">
    <h2>Editar: <?= sueldoEscapar(sueldoNombre($seleccionado)) ?> · <?= sueldoEscapar($periodo) ?></h2>
    <?php if ($error): ?><div class="alerta err" role="alert"><?= sueldoEscapar($error) ?></div><?php endif; ?>
    <p class="subtitulo sueldo-ayuda">Documento: <?= sueldoEscapar($seleccionado['dni'] ?: ($seleccionado['carnet_extranjeria'] ?: $seleccionado['pasaporte'])) ?> · Puesto: <?= sueldoEscapar($seleccionado['puesto_trabajo']) ?> · Sede: <?= sueldoEscapar($seleccionado['sede_labora']) ?></p>
    <form method="post" action="sueldo_mantenimiento.php#editar-sueldo">
        <input type="hidden" name="csrf" value="<?= sueldoEscapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="periodo" value="<?= sueldoEscapar($periodo) ?>">
        <input type="hidden" name="personal" value="<?= $personalId ?>">
        <input type="hidden" name="version" value="<?= sueldoEscapar($esPost ? $versionRaw : ($seleccionado['sueldo_version'] ?? '0')) ?>">
        <div class="sueldo-campos">
            <?php foreach (SUELDO_CAMPOS as $campo => $etiqueta):
                $valor = $error !== '' ? $valoresFormulario[$campo] : $seleccionado[$campo];
                $tipo = isset(SUELDO_IMPORTES[$campo]) ? 'number' : 'date';
            ?>
                <div class="form-grupo">
                    <?php if ($campo === 'prestamo'):
                        $estadoSeleccionado = $error !== '' ? $estadoPrestamo : ($seleccionado['prestamo_estado'] ?? '');
                    ?>
                        <label for="sueldo-prestamo-estado">Préstamo</label>
                        <div class="sueldo-prestamo-ayuda">
                            <select id="sueldo-prestamo-estado" name="prestamo_estado" aria-describedby="sueldo-prestamo-ayuda">
                                <option value=""<?= $estadoSeleccionado === '' ? ' selected' : '' ?>>Sin especificar</option>
                                <option value="aplica"<?= $estadoSeleccionado === 'aplica' ? ' selected' : '' ?>>Con préstamo</option>
                                <option value="no_aplica"<?= $estadoSeleccionado === 'no_aplica' ? ' selected' : '' ?>>No aplica</option>
                            </select>
                            <div id="sueldo-prestamo-ayuda" class="sueldo-prestamo-tooltip" role="tooltip">Al guardar «No aplica» se elimina el importe de este mes.</div>
                        </div>
                        <div id="sueldo-prestamo-monto">
                            <label for="sueldo-prestamo">Importe del préstamo</label>
                            <input id="sueldo-prestamo" name="prestamo" type="number" min="0" max="9999999999.99" step="0.01" value="<?= sueldoEscapar($valor) ?>">
                        </div>
                        <small id="sueldo-prestamo-aviso" class="sueldo-prestamo-aviso" role="status" aria-live="polite" data-tiene-prestamo="<?= $seleccionado['prestamo'] !== null ? '1' : '0' ?>"<?= $estadoSeleccionado === 'no_aplica' && $seleccionado['prestamo'] !== null ? '' : ' hidden' ?>>Al guardar «No aplica» se eliminará el importe guardado de este mes.</small>
                        <noscript><small>Al guardar «No aplica» se elimina el importe de este mes.</small></noscript>
                    <?php elseif ($campo === 'tipo_sueldo'): ?>
                        <label for="sueldo-tipo_sueldo"><?= sueldoEscapar($etiqueta) ?></label>
                        <select id="sueldo-tipo_sueldo" name="tipo_sueldo">
                            <option value="">Sin especificar</option>
                            <?php foreach (SUELDO_TIPOS as $opcion): ?>
                                <option value="<?= sueldoEscapar($opcion) ?>"<?= $valor === $opcion ? ' selected' : '' ?>><?= sueldoEscapar($opcion) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <label for="sueldo-<?= sueldoEscapar($campo) ?>"><?= sueldoEscapar($etiqueta) ?></label>
                        <input id="sueldo-<?= sueldoEscapar($campo) ?>" name="<?= sueldoEscapar($campo) ?>" type="<?= $tipo ?>" value="<?= sueldoEscapar($valor) ?>"
                            <?= $tipo === 'number' ? 'min="0" max="9999999999.99" step="0.01"' : 'min="1000-01-01" max="9999-12-31"' ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="sueldo-acciones">
            <button type="submit">Guardar datos</button>
            <a class="btn btn-sec" href="sueldo_mantenimiento.php?periodo=<?= sueldoEscapar($periodo) ?>">Cancelar</a>
        </div>
    </form>
</div>
<script>
(function () {
    var estado = document.getElementById('sueldo-prestamo-estado');
    var monto = document.getElementById('sueldo-prestamo-monto');
    var importe = document.getElementById('sueldo-prestamo');
    var aviso = document.getElementById('sueldo-prestamo-aviso');
    function actualizarPrestamo() {
        monto.hidden = estado.value === 'no_aplica' || (estado.value === '' && importe.value === '');
        importe.disabled = estado.value === 'no_aplica';
        importe.required = estado.value === 'aplica';
        aviso.hidden = estado.value !== 'no_aplica' || aviso.dataset.tienePrestamo !== '1';
    }
    estado.addEventListener('change', actualizarPrestamo);
    actualizarPrestamo();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/layout_fin.php'; ?>
