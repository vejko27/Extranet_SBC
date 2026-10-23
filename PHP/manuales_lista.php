<?php
// Lista de manuales desde la base de datos: id => [título, descripción, archivo PDF]
require_once __DIR__ . '/bd_conexion.php';

$conexion->set_charset('utf8mb4');
$lista = [];
foreach ($conexion->query("SELECT id, titulo, descripcion, archivo FROM manuales ORDER BY id") as $m) {
    $lista[$m['id']] = [$m['titulo'], $m['descripcion'], $m['archivo']];
}
return $lista;
