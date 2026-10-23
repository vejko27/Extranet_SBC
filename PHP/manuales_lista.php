<?php
// Devuelve el listado de manuales (consultado en BD) para quien lo incluya.
// Lista de manuales desde la base de datos: id => [título, descripción, archivo PDF]
require_once __DIR__ . '/bd_conexion.php';

$conexion->set_charset('utf8mb4');
$lista = [];
// Consulta los manuales y los indexa por id
foreach ($conexion->query("SELECT id, titulo, descripcion, archivo FROM manuales ORDER BY id") as $m) {
    $lista[$m['id']] = [$m['titulo'], $m['descripcion'], $m['archivo']];
}
return $lista;
