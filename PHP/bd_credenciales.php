<?php
// Busca el archivo de credenciales: DB_CONFIG_FILE, luego Vcanova/bd_credenciales_local.php
// en las carpetas superiores (fuera de public_html en producción) y por último PHP/bd_credenciales.local.php.
function localizarConfigLocalBD(): ?string
{
    $ruta = getenv('DB_CONFIG_FILE');
    if ($ruta !== false && $ruta !== '' && is_file($ruta)) {
        return $ruta;
    }

    $dir = __DIR__;
    while (true) {
        $candidato = $dir . '/Vcanova/bd_credenciales_local.php';
        if (is_file($candidato)) {
            return $candidato;
        }
        $padre = dirname($dir);
        if ($padre === $dir) {
            break;
        }
        $dir = $padre;
    }

    $local = __DIR__ . '/bd_credenciales.local.php';
    return is_file($local) ? $local : null;
}

// Lee la configuración local: puede devolver un string (solo la contraseña)
// o un arreglo ['usuario' => '...', 'contraseña' => '...'].
function cargarConfigLocalBD(): array
{
    $archivoLocal = localizarConfigLocalBD();
    if ($archivoLocal === null) {
        return [];
    }
    $config = require $archivoLocal;
    if (is_string($config)) {
        return ['contraseña' => $config];
    }
    if (is_array($config)) {
        return $config;
    }
    throw new RuntimeException('La configuración local de la base de datos no es válida.');
}

function obtenerUsuarioBD(): string
{
    $usuario = getenv('DB_USER');
    if ($usuario !== false && $usuario !== '') {
        return $usuario;
    }

    $config = cargarConfigLocalBD();
    if (!empty($config['usuario']) && is_string($config['usuario'])) {
        return $config['usuario'];
    }

    return 'root';
}

function obtenerNombreBD(): string
{
    $nombre = getenv('DB_NAME');
    if ($nombre !== false && $nombre !== '') {
        return $nombre;
    }

    $config = cargarConfigLocalBD();
    if (!empty($config['basedatos']) && is_string($config['basedatos'])) {
        return $config['basedatos'];
    }

    return 'extranet_sbc';
}

function obtenerContraseñaBD(): string
{
    $contraseña = getenv('DB_PASSWORD');
    if ($contraseña !== false && $contraseña !== '') {
        return $contraseña;
    }

    $config = cargarConfigLocalBD();
    return isset($config['contraseña']) && is_string($config['contraseña']) ? $config['contraseña'] : '';
}
