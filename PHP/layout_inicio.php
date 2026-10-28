<?php
// Cabecera de la plantilla común: sesión, permisos del usuario, menú lateral y apertura del contenido.
// Plantilla común: requiere $paginaActiva y $tituloPagina antes de incluirla
require_once __DIR__ . '/auth.php';
// Banderas de permiso que determinan qué opciones del menú se muestran
$esAdmin = (int)($_SESSION['rol_id'] ?? 0) === 1;
$veUsuarios = puede('Usuarios y roles', 'ver');
$creaUsuarios = puede('Usuarios y roles', 'crear');
$veManuales = puede('Manuales', 'ver');
$adminManuales = puede('Manuales', 'crear') || puede('Manuales', 'editar') || puede('Manuales', 'eliminar');
$veProveedores = puede('Proveedores', 'ver');
$veRRHH = puede('Recursos Humanos', 'ver');
// Lista de manuales y manual activo (solo en la página de manuales)
$manuales = require __DIR__ . '/manuales_lista.php';
$manualActivo = $paginaActiva === 'manuales' ? (string)($_GET['m'] ?? array_key_first($manuales)) : null;
?>
<?php /* Cabecera HTML y hojas de estilo */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?></title>
    <link rel="icon" type="image/x-icon" href="../icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="shortcut icon" href="../icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="stylesheet" href="../estilos/style_panel.css?v=<?= filemtime(dirname(__DIR__) . '/estilos/style_panel.css') ?>">
    <script>
        try {
            if (localStorage.getItem('sidebarColapsado') === '1') {
                document.documentElement.classList.add('sidebar-colapsado');
            }
        } catch (e) {}
    </script>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-cabecera">
            <div class="marca">Extranet <span>SBC</span></div>
            <button type="button" class="sidebar-colapsar" id="toggle-sidebar" aria-label="Contraer menú" aria-expanded="true" title="Contraer menú">‹</button>
        </div>
        <!-- Menú lateral: cada enlace aparece solo si el usuario tiene el permiso -->
        <nav>
            <a href="dashboard.php" data-short="I" title="Inicio" class="<?= $paginaActiva === 'dashboard' ? 'activo' : '' ?>">Inicio</a>
            <?php if ($veUsuarios): ?>
                <a href="usuarios.php" data-short="U" title="Usuarios y roles" class="<?= $paginaActiva === 'usuarios' ? 'activo' : '' ?>">Usuarios y roles</a>
            <?php endif; ?>
            <?php if ($creaUsuarios): ?>
                <a href="registrar.php" data-short="R" title="Registrar usuario" class="<?= $paginaActiva === 'registrar' ? 'activo' : '' ?>">Registrar usuario</a>
            <?php endif; ?>
            <?php if ($esAdmin): ?>
                <a href="permisos.php" data-short="P" title="Permisos" class="<?= $paginaActiva === 'permisos' ? 'activo' : '' ?>">Permisos</a>
            <?php endif; ?>
            <?php if ($adminManuales): ?>
                <a href="admin_manuales.php" data-short="A" title="Administrar manuales" class="<?= $paginaActiva === 'admin_manuales' ? 'activo' : '' ?>">Administrar manuales</a>
            <?php endif; ?>
            <?php if ($veProveedores): ?>
                <a href="proveedores.php" data-short="P" title="Proveedores" class="<?= $paginaActiva === 'proveedores' ? 'activo' : '' ?>">Proveedores</a>
            <?php endif; ?>
            <?php if ($veRRHH): ?>
                <a href="rrhh.php" data-short="RH" title="Recursos Humanos" class="<?= $paginaActiva === 'rrhh' ? 'activo' : '' ?>">Recursos Humanos</a>
            <?php endif; ?>
            <?php if ($veManuales): ?>
                <!-- Submenú desplegable con un enlace por cada manual -->
                <button type="button" class="grupo-titulo grupo-toggle" id="toggle-manuales" data-short="M" title="Manuales" aria-expanded="true" aria-controls="submenu-manuales">
                    <span class="grupo-nombre">Manuales</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-manuales">
                <?php foreach ($manuales as $clave => [$t, $d]): ?>
                    <a href="manuales.php?m=<?= urlencode($clave) ?>"
                       class="sub<?= $manualActivo === (string)$clave ? ' activo' : '' ?>"
                       title="<?= htmlspecialchars($t) ?>"
                       data-title="<?= htmlspecialchars($t) ?>"
                       data-description="<?= htmlspecialchars($d) ?>"
                       data-pdf="ver_manual.php?f=<?= urlencode($clave) ?>"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    // Abre/cierra el submenú de manuales y recuerda el estado en localStorage
                    var b = document.getElementById('toggle-manuales'), s = document.getElementById('submenu-manuales');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuManuales', abierto ? '1' : '0'); } catch (e) {}
                    }
                    // Restaura el estado guardado (abierto por defecto)
                    var g = null; try { g = localStorage.getItem('menuManuales'); } catch (e) {}
                    set(g !== '0');
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
            <?php endif; ?>
        </nav>
        <!-- Enlace para cerrar sesión -->
        <a href="logout.php" class="salir" data-short="↪" title="Cerrar sesión">Cerrar sesión</a>
    </aside>
    <!-- Aquí empieza el contenido de cada página; se cierra en layout_fin.php -->
    <main class="contenido">
