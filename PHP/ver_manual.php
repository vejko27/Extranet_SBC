<?php
// Entrega el PDF de un manual (?f=clave) tras validar permiso y existencia del archivo.
require __DIR__ . '/auth_manuales.php';

// Lista de manuales desde la BD
$manuales = require __DIR__ . '/manuales_lista.php';

// 404 si la clave no corresponde a un manual
$clave = $_GET['f'] ?? '';
if (!isset($manuales[$clave])) {
    http_response_code(404);
    exit('Manual no encontrado');
}

// Ruta del PDF en disco; 404 si el archivo no existe
$ruta = dirname(__DIR__) . '/Manuales/' . $manuales[$clave][2];
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
