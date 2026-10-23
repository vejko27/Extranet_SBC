<?php
// Plantilla común: requiere $paginaActiva y $tituloPagina antes de incluirla
require_once __DIR__ . '/auth.php';
$esAdmin = (int)($_SESSION['rol_id'] ?? 0) === 1;
$veUsuarios = puede('Usuarios y roles', 'ver');
$creaUsuarios = puede('Usuarios y roles', 'crear');
$veManuales = puede('Manuales', 'ver');
$adminManuales = puede('Manuales', 'crear') || puede('Manuales', 'editar') || puede('Manuales', 'eliminar');
$veProveedores = puede('Proveedores', 'ver');
$manuales = require __DIR__ . '/manuales_lista.php';
$manualActivo = $paginaActiva === 'manuales' ? (string)($_GET['m'] ?? array_key_first($manuales)) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?></title>
    <link rel="icon" type="image/x-icon" href="../icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="shortcut icon" href="../icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="stylesheet" href="../estilos/style_panel.css?v=<?= filemtime(dirname(__DIR__) . '/estilos/style_panel.css') ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="marca">Extranet <span>SBC</span></div>
        <nav>
            <a href="dashboard.php" class="<?= $paginaActiva === 'dashboard' ? 'activo' : '' ?>">Inicio</a>
            <?php if ($veUsuarios): ?>
                <a href="usuarios.php" class="<?= $paginaActiva === 'usuarios' ? 'activo' : '' ?>">Usuarios y roles</a>
            <?php endif; ?>
            <?php if ($creaUsuarios): ?>
                <a href="registrar.php" class="<?= $paginaActiva === 'registrar' ? 'activo' : '' ?>">Registrar usuario</a>
            <?php endif; ?>
            <?php if ($esAdmin): ?>
                <a href="permisos.php" class="<?= $paginaActiva === 'permisos' ? 'activo' : '' ?>">Permisos</a>
            <?php endif; ?>
            <?php if ($adminManuales): ?>
                <a href="admin_manuales.php" class="<?= $paginaActiva === 'admin_manuales' ? 'activo' : '' ?>">Administrar manuales</a>
            <?php endif; ?>
            <?php if ($veProveedores): ?>
                <a href="proveedores.php" class="<?= $paginaActiva === 'proveedores' ? 'activo' : '' ?>">Proveedores</a>
            <?php endif; ?>
            <?php if ($veManuales): ?>
                <button type="button" class="grupo-titulo grupo-toggle" id="toggle-manuales" aria-expanded="true" aria-controls="submenu-manuales">
                    <span>Manuales</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-manuales">
                <?php foreach ($manuales as $clave => [$t, $d]): ?>
                    <a href="manuales.php?m=<?= urlencode($clave) ?>"
                       class="sub<?= $manualActivo === (string)$clave ? ' activo' : '' ?>"
                       data-title="<?= htmlspecialchars($t) ?>"
                       data-description="<?= htmlspecialchars($d) ?>"
                       data-pdf="ver_manual.php?f=<?= urlencode($clave) ?>"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-manuales'), s = document.getElementById('submenu-manuales');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuManuales', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuManuales'); } catch (e) {}
                    set(g !== '0');
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
            <?php endif; ?>
        </nav>
        <a href="logout.php" class="salir">Cerrar sesión</a>
    </aside>
    <main class="contenido">
