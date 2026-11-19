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
$veFormatos = puede('Formatos de Procedimientos internos', 'ver');
$adminFormatos = puede('Formatos de Procedimientos internos', 'crear') || puede('Formatos de Procedimientos internos', 'editar') || puede('Formatos de Procedimientos internos', 'eliminar');
$veProveedores = puede('Proveedores', 'ver');
$veRRHH = puede('Recursos Humanos', 'ver');
$veSueldo = puede('Sueldo', 'ver');
$veDetraccion = puede('Cálculo de detracción', 'ver');
$veTractos = puede('Tractos', 'ver');
$veCarretas = puede('Carretas', 'ver');
$veMuebles = puede('Muebles', 'ver');
$veEquiposComputo = puede('Equipos de Cómputo', 'ver');
$veEquiposDiversos = puede('Equipos Diversos', 'ver');
$veMonitores = puede('Monitores', 'ver');
$veFlota = $veTractos || $veCarretas;
$flotaActiva = in_array($paginaActiva, ['flota_inicio', 'tractos', 'carretas', 'lavados', 'mantenimiento_tractos', 'programacion_flota'], true);
$activosActivo = in_array($paginaActiva, ['activos_muebles', 'activos_computo', 'activos_diversos', 'activos_monitores'], true);
$rrhhActivo = in_array($paginaActiva, ['rrhh', 'rrhh_contratos', 'rrhh_documentos_vencidos', 'rrhh_boletas'], true);
$sueldoActivo = in_array($paginaActiva, ['sueldo', 'sueldo_mantenimiento'], true);
// Lista de manuales y manual activo (solo en la página de manuales)
$listarFormatos = false;
$manuales = require __DIR__ . '/manuales_lista.php';
$formatos = [];
if ($veFormatos) {
    $listarFormatos = true;
    $formatos = require __DIR__ . '/manuales_lista.php';
}
$listarFormatos = false;
$manualActivo = $paginaActiva === 'manuales' && isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : null;
$formatoActivo = $paginaActiva === 'formatos_procedimientos' && isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : null;
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
            <button type="button" class="sidebar-colapsar" id="toggle-sidebar" aria-label="Abrir menú" aria-expanded="false" title="Abrir menú">‹</button>
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
            <?php if ($adminFormatos): ?>
                <a href="admin_formatos_procedimientos.php" data-short="AF" title="Administrar formatos de procedimientos internos" class="<?= $paginaActiva === 'admin_formatos_procedimientos' ? 'activo' : '' ?>">Administrar formatos de procedimientos internos</a>
            <?php endif; ?>
            <?php if ($veProveedores): ?>
                <a href="proveedores.php" data-short="P" title="Proveedores" class="<?= $paginaActiva === 'proveedores' ? 'activo' : '' ?>">Proveedores</a>
            <?php endif; ?>
            <?php if ($veDetraccion): ?>
                <a href="calculo_detraccion.php" data-short="CD" title="Cálculo de detracción" class="<?= $paginaActiva === 'calculo_detraccion' ? 'activo' : '' ?>">Cálculo de detracción</a>
            <?php endif; ?>
            <?php if ($veSueldo): ?>
                <button type="button" class="grupo-titulo grupo-toggle<?= $sueldoActivo ? ' activo' : '' ?>" id="toggle-sueldo" data-short="MS" title="Mundo Sueldos" aria-expanded="true" aria-controls="submenu-sueldo">
                    <span class="grupo-nombre">Mundo Sueldos</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-sueldo">
                    <a href="sueldo.php" class="sub<?= $paginaActiva === 'sueldo' ? ' activo' : '' ?>">Planilla</a>
                    <a href="sueldo_mantenimiento.php" class="sub<?= $paginaActiva === 'sueldo_mantenimiento' ? ' activo' : '' ?>">Mantenimiento de personal</a>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-sueldo'), s = document.getElementById('submenu-sueldo');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuSueldo', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuSueldo'); } catch (e) {}
                    set(g !== '0' || <?= $sueldoActivo ? 'true' : 'false' ?>);
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
            <?php endif; ?>
            <?php if ($veFlota): ?>
                <button type="button" class="grupo-titulo grupo-toggle<?= $flotaActiva ? ' activo' : '' ?>" id="toggle-tractos-carretas" data-short="TC" title="Inventario de tractos y carretas" aria-expanded="true" aria-controls="submenu-tractos-carretas">
                    <span class="grupo-nombre">Tractos y Carretas</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-tractos-carretas">
                    <?php if ($veTractos): ?>
                        <a href="flota.php?tipo=tractos" class="sub<?= $paginaActiva === 'tractos' ? ' activo' : '' ?>">Tractos</a>
                        <a href="lavados.php" class="sub<?= $paginaActiva === 'lavados' ? ' activo' : '' ?>">Lavados</a>
                        <a href="mantenimiento_tractos.php" class="sub<?= $paginaActiva === 'mantenimiento_tractos' ? ' activo' : '' ?>">Mantenimiento de Tractos</a>
                    <?php endif; ?>
                    <?php if ($veCarretas): ?>
                        <a href="flota.php?tipo=carretas" class="sub<?= $paginaActiva === 'carretas' ? ' activo' : '' ?>">Carretas</a>
                    <?php endif; ?>
                    <?php if ($veTractos && $veCarretas): ?>
                        <a href="programacion_flota.php" class="sub<?= $paginaActiva === 'programacion_flota' ? ' activo' : '' ?>">Programación semanal</a>
                    <?php endif; ?>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-tractos-carretas'), s = document.getElementById('submenu-tractos-carretas');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuFlota', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuFlota'); } catch (e) {}
                    set(g !== '0' || <?= $flotaActiva ? 'true' : 'false' ?>);
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
            <?php endif; ?>
            <?php if ($veMuebles || $veEquiposComputo || $veEquiposDiversos || $veMonitores): ?>
                <button type="button" class="grupo-titulo grupo-toggle<?= $activosActivo ? ' activo' : '' ?>" id="toggle-activos" data-short="AC" title="Activos" aria-expanded="true" aria-controls="submenu-activos">
                    <span class="grupo-nombre">Activos</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-activos">
                    <?php if ($veMuebles): ?>
                        <a href="activos_muebles.php" class="sub<?= $paginaActiva === 'activos_muebles' ? ' activo' : '' ?>">Muebles</a>
                    <?php endif; ?>
                    <?php if ($veEquiposComputo): ?>
                        <a href="activos_computo.php" class="sub<?= $paginaActiva === 'activos_computo' ? ' activo' : '' ?>">Equipos de Cómputo</a>
                    <?php endif; ?>
                    <?php if ($veEquiposDiversos): ?>
                        <a href="activos_diversos.php" class="sub<?= $paginaActiva === 'activos_diversos' ? ' activo' : '' ?>">Equipos Diversos</a>
                    <?php endif; ?>
                    <?php if ($veMonitores): ?>
                        <a href="activos_monitores.php" class="sub<?= $paginaActiva === 'activos_monitores' ? ' activo' : '' ?>">Monitores</a>
                    <?php endif; ?>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-activos'), s = document.getElementById('submenu-activos');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuActivos', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuActivos'); } catch (e) {}
                    set(g !== '0' || <?= $activosActivo ? 'true' : 'false' ?>);
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
            <?php endif; ?>
            <?php if ($veRRHH): ?>
                <button type="button" class="grupo-titulo grupo-toggle<?= $rrhhActivo ? ' activo' : '' ?>" id="toggle-recursos-humanos" data-short="RH" title="Recursos Humanos" aria-expanded="true" aria-controls="submenu-recursos-humanos">
                    <span class="grupo-nombre">Recursos Humanos</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-recursos-humanos">
                    <a href="rrhh.php" class="sub<?= $paginaActiva === 'rrhh' ? ' activo' : '' ?>" title="Fichas del personal">Personal</a>
                    <a href="rrhh_contratos.php" class="sub<?= $paginaActiva === 'rrhh_contratos' ? ' activo' : '' ?>" title="Seguimiento de contratos">Contratos</a>
                    <a href="rrhh_documentos_vencidos.php" class="sub<?= $paginaActiva === 'rrhh_documentos_vencidos' ? ' activo' : '' ?>" title="Seguimiento de documentos vencidos">Documentos vencidos</a>
                    <a href="rrhh_boletas.php" class="sub<?= $paginaActiva === 'rrhh_boletas' ? ' activo' : '' ?>" title="Control de entrega de boletas">Entrega de boletas</a>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-recursos-humanos'), s = document.getElementById('submenu-recursos-humanos');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuRecursosHumanos', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuRecursosHumanos'); } catch (e) {}
                    set(g !== '0' || <?= $rrhhActivo ? 'true' : 'false' ?>);
                    b.addEventListener('click', function () { set(b.getAttribute('aria-expanded') !== 'true'); });
                })();
                </script>
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
                       data-modulo="manuales"
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
            <?php if ($veFormatos): ?>
                <button type="button" class="grupo-titulo grupo-toggle" id="toggle-formatos" data-short="F" title="Formatos de Procedimientos internos" aria-expanded="true" aria-controls="submenu-formatos">
                    <span class="grupo-nombre">Formatos de Procedimientos Internos</span><span class="flecha" aria-hidden="true">▾</span>
                </button>
                <div class="submenu" id="submenu-formatos">
                <?php foreach ($formatos as $clave => [$t, $d]): ?>
                    <a href="formatos_procedimientos.php?m=<?= urlencode($clave) ?>"
                       class="sub<?= $formatoActivo === (string)$clave ? ' activo' : '' ?>"
                       title="<?= htmlspecialchars($t) ?>"
                       data-title="<?= htmlspecialchars($t) ?>"
                       data-description="<?= htmlspecialchars($d) ?>"
                       data-modulo="formatos"
                       data-pdf="ver_formato_procedimiento.php?f=<?= urlencode($clave) ?>"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('toggle-formatos'), s = document.getElementById('submenu-formatos');
                    function set(abierto) {
                        b.setAttribute('aria-expanded', abierto);
                        s.hidden = !abierto;
                        try { localStorage.setItem('menuFormatosProcedimientos', abierto ? '1' : '0'); } catch (e) {}
                    }
                    var g = null; try { g = localStorage.getItem('menuFormatosProcedimientos'); } catch (e) {}
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
