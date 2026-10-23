<?php
// Guardia de acceso: exige sesión iniciada y carga las funciones de permisos.
// Seguridad de sistema
session_start();

if (!isset($_SESSION['id_usuario'])) {
    // Sin sesión: calcula la ruta base del proyecto y redirige al login
    $raiz = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $base = substr(str_replace('\\', '/', dirname(__DIR__)), strlen($raiz));
    header('Location: ' . $base . '/index.php');
    exit;
}

// Helpers de permisos por módulo (exigirPermiso, etc.)
require_once __DIR__ . '/permisos_helper.php';
