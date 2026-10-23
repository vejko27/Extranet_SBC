<?php
// Conexión MySQLi a la base de datos extranet_sbc; la usan los demás scripts con require.
//Configuramos los datos de nuestro servidor de conexion, sea local, como tmb hosting
$host='localhost';
$basededatos ='extranet_sbc';
$usuario ='root';
$contraseña = '';
//Luego de ello establecemos la conexion entre base de datos y algoritmo de desarrollo.
$conexion = new mysqli($host, $usuario,$contraseña,$basededatos);

//Luego habilitamos las condicionales

try {
    if ($conexion->connect_error) {
        throw new Exception("Error de conexión: " . $conexion->connect_error);
    }
} catch (Exception $e) {
    echo "Excepción capturada: " . $e->getMessage();
}   
?>