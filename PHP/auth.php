<?php
// Seguridad de sistema
session_start();

if (!isset($_SESSION['id_usuario'])) {
    $raiz = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $base = substr(str_replace('\\', '/', dirname(__DIR__)), strlen($raiz));
    header('Location: ' . $base . '/index.php');
    exit;
}

require_once __DIR__ . '/permisos_helper.php';
