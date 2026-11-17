<?php
require_once __DIR__ . '/lavados_helper.php';
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$tipo = $_GET['tipo'] ?? '';
if (!$id || !is_string($tipo) || !in_array($tipo, ['pdf', 'xml'], true)) {
    http_response_code(400);
    exit('Documento no válido.');
}
$st = lavadoPreparar('SELECT id, tamano FROM lavados_facturas WHERE lavado_id = ? AND tipo = ?');
$st->bind_param('is', $id, $tipo);
lavadoEjecutar($st);
$factura = lavadoResultado($st)->fetch_assoc();
$st->close();
if (!$factura) {
    http_response_code(404);
    exit('Factura no encontrada.');
}
$facturaId = (int)$factura['id'];
$st = lavadoPreparar('SELECT numero, contenido FROM lavados_factura_partes WHERE factura_id = ? ORDER BY numero');
$st->bind_param('i', $facturaId);
lavadoEjecutar($st);
$partes = lavadoResultado($st);
$contenido = '';
$numero = 0;
while ($parte = $partes->fetch_assoc()) {
    if ((int)$parte['numero'] !== $numero++) {
        lavadoFalloBD('Los bloques de una factura están incompletos.');
    }
    $contenido .= $parte['contenido'];
}
$st->close();
if (strlen($contenido) !== (int)$factura['tamano'] || $contenido === '') {
    lavadoFalloBD('El tamaño de una factura no coincide con sus bloques.');
}
session_write_close();
header('Content-Type: ' . ($tipo === 'pdf' ? 'application/pdf' : 'application/xml'));
header('Content-Disposition: attachment; filename="factura-lavado-' . $id . '.' . $tipo . '"');
header('Content-Length: ' . strlen($contenido));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $contenido;
