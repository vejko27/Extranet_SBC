<?php
// Entrega un documento del personal (?d=id; ?descargar para forzar descarga) con permiso de RRHH.
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Requiere permiso de ver en Recursos Humanos
exigirPermiso('Recursos Humanos', 'ver');
$conexion->set_charset('utf8mb4');

// Busca el documento en la BD y arma su ruta en disco
$id = (int)($_GET['d'] ?? 0);
$s = $conexion->prepare("SELECT archivo, mime, nombre_original FROM personal_documentos WHERE id = ?");
$s->bind_param('i', $id);
$s->execute();
$doc = $s->get_result()->fetch_assoc();
$ruta = $doc ? dirname(__DIR__) . '/Documentos_Personal/' . $doc['archivo'] : '';
// 404 si no existe, el nombre guardado es inválido o falta el archivo
if (!$doc || !preg_match('/^[a-f0-9]{24}\.(pdf|jpg|png)$/', $doc['archivo']) || !is_file($ruta)) {
    http_response_code(404);
    exit('Documento no encontrado');
}

// Nombre de archivo saneado; luego se liberan la sesión y se envía el archivo
$nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc['nombre_original']) ?: 'documento';
session_write_close();
header('Content-Type: ' . $doc['mime']);
header('Content-Disposition: ' . (isset($_GET['descargar']) ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($ruta);
