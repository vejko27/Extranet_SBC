<?php
require_once __DIR__ . '/auth.php';

$veTractos = puede('Tractos', 'ver');
$veCarretas = puede('Carretas', 'ver');
if (!$veTractos && !$veCarretas) {
    http_response_code(403);
    exit('Acceso denegado');
}

$paginaActiva = 'flota_inicio';
$tituloPagina = 'Tractos y Carretas';
require __DIR__ . '/layout_inicio.php';

$modulosFlota = [];
if ($veTractos) {
    $modulosFlota[] = ['flota.php?tipo=tractos', 'Tractos', 'Consulta y actualiza el inventario de tractos.'];
    $modulosFlota[] = ['lavados.php', 'Lavados', 'Registra y consulta los lavados de tractos.'];
    $modulosFlota[] = ['mantenimiento_tractos.php', 'Mantenimiento de Tractos', 'Consulta y actualiza los mantenimientos de tractos.'];
}
if ($veCarretas) {
    $modulosFlota[] = ['flota.php?tipo=carretas', 'Carretas', 'Consulta y actualiza el inventario de carretas.'];
}
if ($veTractos && $veCarretas) {
    $modulosFlota[] = ['programacion_flota.php', 'Programación semanal', 'Asigna carretas a tractos y consulta las semanas anteriores.'];
}
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Tractos y Carretas</h1>
        <p class="subtitulo">Selecciona un módulo de flota para comenzar.</p>
    </div>
</div>

<div class="grid">
    <?php foreach ($modulosFlota as [$url, $titulo, $desc]): ?>
    <a class="tarjeta-acceso" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
        <h3><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
    </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
