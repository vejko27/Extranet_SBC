<?php
$paginaActiva = 'dashboard';
$tituloPagina = 'Panel Principal';
require __DIR__ . '/layout_inicio.php';

$tarjetas = [];
if ($veUsuarios)    $tarjetas[] = ['usuarios.php', 'Usuarios y roles', 'Crea usuarios y asigna sus roles.'];
if ($creaUsuarios)  $tarjetas[] = ['registrar.php', 'Registrar usuario', 'Formulario rápido de alta.'];
if ($esAdmin)       $tarjetas[] = ['permisos.php', 'Permisos', 'Gestión de permisos por módulo.'];
if ($adminManuales) $tarjetas[] = ['admin_manuales.php', 'Administrar manuales', 'Sube, edita o elimina manuales en PDF.'];
if ($veManuales)    $tarjetas[] = ['manuales.php', 'Manuales', 'Consulta los manuales en PDF.'];
if ($veProveedores) $tarjetas[] = ['proveedores.php', 'Proveedores', 'Listado de proveedores homologados.'];
?>

<h1>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
<p class="subtitulo">Selecciona una opción para comenzar.</p>

<?php if ($tarjetas): ?>
<div class="grid">
    <?php foreach ($tarjetas as [$url, $titulo, $desc]): ?>
    <a class="tarjeta-acceso" href="<?= $url ?>">
        <h3><?= htmlspecialchars($titulo) ?></h3>
        <p><?= htmlspecialchars($desc) ?></p>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card"><p>Has iniciado sesión correctamente. Aún no tienes módulos asignados.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/layout_fin.php'; ?>
