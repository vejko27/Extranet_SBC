<?php
require __DIR__ . '/auth_manuales.php';

$manuales = require __DIR__ . '/manuales_lista.php';

$clave = $_GET['f'] ?? '';
if (!isset($manuales[$clave])) {
    http_response_code(404);
    exit('Manual no encontrado');
}

$ruta = dirname(__DIR__) . '/Manuales/' . $manuales[$clave][2];
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Manual no encontrado');
}

session_write_close();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $clave . '.pdf"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
