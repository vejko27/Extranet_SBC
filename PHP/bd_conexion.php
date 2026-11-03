<?php
require_once __DIR__ . '/bd_credenciales.php';

// Conexión MySQLi a la base de datos extranet_sbc; la usan los demás scripts con require.
//Configuramos los datos de nuestro servidor de conexion, sea local, como tmb hosting
$host='localhost';
$basededatos = obtenerNombreBD();
$usuario = obtenerUsuarioBD();
$contraseña = obtenerContraseñaBD();
//Luego de ello establecemos la conexion entre base de datos y algoritmo de desarrollo.
// Sin excepciones de mysqli: el fallo se registra en el log y se responde con un mensaje genérico.
mysqli_report(MYSQLI_REPORT_OFF);
try {
    $conexion = @new mysqli($host, $usuario, $contraseña, $basededatos);
    if ($conexion->connect_error) {
        throw new Exception($conexion->connect_error);
    }
} catch (Throwable $e) {
    $archivoCfg = localizarConfigLocalBD();
    $clavesCfg = $archivoCfg === null ? '-' : implode(',', array_keys(cargarConfigLocalBD()));
    error_log("Extranet_SBC: fallo de conexión a la BD (usuario '$usuario', base '$basededatos', config: " . ($archivoCfg ?? 'NO ENCONTRADO') . ", claves: $clavesCfg): " . $e->getMessage());
    http_response_code(503);
    exit('No se pudo conectar con la base de datos. Contacte al administrador.');
}
?>