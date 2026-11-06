<?php
// Entrega el PDF de un manual (?f=clave) tras validar permiso y existencia del archivo.
$esFormatos = defined('MODULO_FORMATOS') && MODULO_FORMATOS;
if ($esFormatos) {
    require_once __DIR__ . '/auth.php';
    exigirPermiso('Formatos de Procedimientos internos', 'ver');
} else {
    require __DIR__ . '/auth_manuales.php';
}

// Lista de manuales desde la BD
$listarFormatos = $esFormatos;
$manuales = require __DIR__ . '/manuales_lista.php';

// 404 si la clave no corresponde a un manual
$clave = $_GET['f'] ?? '';
if (!is_string($clave) || !isset($manuales[$clave])) {
    http_response_code(404);
    exit('Manual no encontrado');
}

// Ruta del PDF en disco; 404 si el archivo no existe
$ruta = dirname(__DIR__) . ($esFormatos ? '/Formatos_Procedimientos_Internos/' : '/Manuales/') . $manuales[$clave][2];
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Manual no encontrado');
}

// Libera la sesión y envía el PDF con sus cabeceras
session_write_close();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $clave . '.pdf"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
