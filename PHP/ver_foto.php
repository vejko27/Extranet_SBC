<?php
// Entrega la foto de un empleado (?id=personal_id) con permiso de ver en Recursos Humanos.
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Recursos Humanos', 'ver');

$id = (int)($_GET['id'] ?? 0);
$s = $conexion->prepare("SELECT foto FROM personal WHERE id = ?");
$s->bind_param('i', $id);
$s->execute();
$foto = (string)($s->get_result()->fetch_column() ?: '');
$ruta = dirname(__DIR__) . '/Documentos_Personal/' . $foto;
if (!preg_match('/^[a-f0-9]{24}\.(jpg|png)$/', $foto) || !is_file($ruta)) {
    http_response_code(404);
    exit('Foto no encontrada');
}

session_write_close();
header('Content-Type: ' . (str_ends_with($foto, '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($ruta);
