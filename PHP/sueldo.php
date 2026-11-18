<?php
$paginaActiva = 'sueldo';
$tituloPagina = 'Sueldo';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sueldo_helper.php';

exigirPermiso('Sueldo', 'ver');
header('Cache-Control: no-store');
$conexion->set_charset('utf8mb4');
$periodo = sueldoPeriodo($_GET['periodo'] ?? (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m'));
$trabajadores = sueldoTrabajadores($conexion, $periodo);
$esMantenimiento = false;
$puedeEditarSueldo = false;

require __DIR__ . '/layout_inicio.php';
?>
<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Sueldo</h1>
        <p class="subtitulo">Planilla de remuneraciones del personal activo.</p>
    </div>
</div>

<div class="card">
    <form method="get" class="sueldo-periodo">
        <label for="periodo">Período</label>
        <input id="periodo" type="month" name="periodo" min="1900-01" max="9999-12" value="<?= sueldoEscapar($periodo) ?>" required>
        <button type="submit" class="btn-sec">Consultar</button>
    </form>
    <p class="subtitulo sueldo-ayuda">Los importes se ingresan manualmente en Mantenimiento de personal; no se calculan automáticamente. Los datos personales corresponden a su situación actual.</p>
    <p class="subtitulo sueldo-ayuda">Trabajadores activos: <?= count($trabajadores) ?>. Los registros de trabajadores dados de baja se conservan, pero no aparecen en esta lista.</p>
    <?php require __DIR__ . '/sueldo_tabla.php'; ?>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
