<?php
// Guardia de acceso: exige sesión iniciada y carga las funciones de permisos.
// Seguridad de sistema
require_once __DIR__ . '/sesion.php';

// Expira la sesión tras 2 horas sin actividad
if (isset($_SESSION['id_usuario']) && time() - ($_SESSION['ultima_actividad'] ?? 0) > 7200) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['ultima_actividad'] = time();

if (isset($_SESSION['id_usuario'])) {
    // Revalida estado y rol contra la BD en cada petición
    require_once __DIR__ . '/bd_conexion.php';
    $stAuth = $conexion->prepare("SELECT rol_id, estado FROM usuarios WHERE id = ?");
    $stAuth->bind_param('i', $_SESSION['id_usuario']);
    $stAuth->execute();
    $uAuth = $stAuth->get_result()->fetch_assoc();
    $stAuth->close();
    if (!$uAuth || $uAuth['estado'] !== 'activo') {
        session_unset();
        session_destroy();
        session_start();
    } else {
        $_SESSION['rol_id'] = (int)$uAuth['rol_id'];
    }
}

if (!isset($_SESSION['id_usuario'])) {
    // Sin sesión: calcula la ruta base del proyecto y redirige al login
    $raiz = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $base = substr(str_replace('\\', '/', dirname(__DIR__)), strlen($raiz));
    header('Location: ' . $base . '/index.php');
    exit;
}

// Helpers de permisos por módulo (exigirPermiso, etc.)
require_once __DIR__ . '/permisos_helper.php';
