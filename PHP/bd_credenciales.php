<?php
function obtenerContraseñaBD(): string
{
    $contraseña = getenv('DB_PASSWORD');
    if ($contraseña !== false && $contraseña !== '') {
        return $contraseña;
    }

    $archivoLocal = __DIR__ . '/bd_credenciales.local.php';
    if (is_file($archivoLocal)) {
        $contraseña = require $archivoLocal;
        if (!is_string($contraseña)) {
            throw new RuntimeException('La configuración local de la contraseña de la base de datos no es válida.');
        }
        return $contraseña;
    }

    return '';
}
