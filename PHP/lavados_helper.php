<?php
require_once __DIR__ . '/auth.php';
exigirPermiso('Tractos', 'ver');
$conexion->set_charset('utf8mb4');

function lavadoFalloBD(string $detalle): void
{
    global $conexion;
    $conexion->rollback();
    error_log('Registros de flota: ' . $detalle);
    http_response_code(500);
    exit('No se pudo acceder al registro. Contacte al administrador.');
}

function lavadoPreparar(string $sql): mysqli_stmt
{
    global $conexion;
    $st = $conexion->prepare($sql);
    if (!$st) {
        lavadoFalloBD($conexion->error);
    }
    return $st;
}

function lavadoEjecutar(mysqli_stmt $st): void
{
    if (!$st->execute()) {
        lavadoFalloBD($st->error);
    }
}

function lavadoResultado(mysqli_stmt $st): mysqli_result
{
    $resultado = $st->get_result();
    if (!$resultado) {
        lavadoFalloBD($st->error);
    }
    return $resultado;
}

function lavadoEscapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function lavadoArchivo(string $campo, string $tipo, string &$error): ?string
{
    $archivo = $_FILES[$campo] ?? null;
    if ($archivo === null || (is_array($archivo) && ($archivo['error'] ?? null) === UPLOAD_ERR_NO_FILE)) {
        return null;
    }
    if (!is_array($archivo) || ($archivo['error'] ?? null) !== UPLOAD_ERR_OK ||
        !isset($archivo['tmp_name'], $archivo['size'], $archivo['name']) ||
        !is_string($archivo['tmp_name']) || !is_string($archivo['name']) ||
        !is_uploaded_file($archivo['tmp_name']) || $archivo['size'] <= 0 ||
        $archivo['size'] > 10 * 1024 * 1024 ||
        strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== $tipo) {
        $error = 'Selecciona un archivo ' . strtoupper($tipo) . ' válido de hasta 10 MB. Verifica también el límite de subida del servidor.';
        return null;
    }
    $contenido = file_get_contents($archivo['tmp_name']);
    if ($contenido === false || strlen($contenido) !== (int)$archivo['size']) {
        error_log('Registros de flota: no se pudo leer el archivo subido.');
        $error = 'No se pudo leer el archivo ' . strtoupper($tipo) . '.';
        return null;
    }
    if ($tipo === 'pdf' && !str_starts_with($contenido, '%PDF-')) {
        $error = 'El archivo PDF no tiene un formato válido.';
        return null;
    }
    if ($tipo === 'xml') {
        $erroresAnteriores = libxml_use_internal_errors(true);
        $xml = new DOMDocument();
        $valido = $xml->loadXML($contenido, LIBXML_NONET);
        $tieneDoctype = $xml->doctype !== null;
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
        if (!$valido || $tieneDoctype) {
            $error = 'El archivo XML no es válido o contiene una declaración DOCTYPE.';
            return null;
        }
    }
    return $contenido;
}

function lavadoTablasFacturas(string $origen): array
{
    if ($origen === 'lavados') {
        return ['lavados_facturas', 'lavados_factura_partes', 'lavado_id'];
    }
    if ($origen === 'mantenimientos') {
        return ['mantenimientos_facturas', 'mantenimientos_factura_partes', 'mantenimiento_id'];
    }
    throw new InvalidArgumentException('Origen de factura no válido.');
}

function lavadoGuardarArchivo(int $lavadoId, string $tipo, string $contenido, string $origen = 'lavados'): void
{
    global $conexion;
    [$tablaFacturas, $tablaPartes, $columnaId] = lavadoTablasFacturas($origen);
    $tamano = strlen($contenido);
    $st = lavadoPreparar("INSERT INTO $tablaFacturas ($columnaId, tipo, tamano) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), tamano = VALUES(tamano)");
    $st->bind_param('isi', $lavadoId, $tipo, $tamano);
    lavadoEjecutar($st);
    $facturaId = (int)$conexion->insert_id;
    $st->close();

    $st = lavadoPreparar("DELETE FROM $tablaPartes WHERE factura_id = ?");
    $st->bind_param('i', $facturaId);
    lavadoEjecutar($st);
    $st->close();

    $st = lavadoPreparar("INSERT INTO $tablaPartes (factura_id, numero, contenido) VALUES (?, ?, ?)");
    $numero = 0;
    $parte = '';
    $st->bind_param('iis', $facturaId, $numero, $parte);
    for ($posicion = 0; $posicion < $tamano; $posicion += 262144) {
        $parte = substr($contenido, $posicion, 262144);
        lavadoEjecutar($st);
        $numero++;
    }
    $st->close();
}
