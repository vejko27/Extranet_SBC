<?php
function obtenerContraseñaBD(): string
{
    $contraseña = getenv('DB_PASSWORD');
    return $contraseña === false ? '' : $contraseña;
}
