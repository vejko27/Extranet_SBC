<?php
require_once __DIR__ . '/bd_credenciales.php';
require_once __DIR__ . '/bd_conexion.php';

// Función puede(): consulta si el rol actual tiene un permiso; exigirPermiso(): corta con 403 si no.
// Permisos por rol y módulo. El Administrador (rol 1) siempre tiene todos los permisos.
// Acciones válidas: ver, crear, editar, eliminar
function puede(string $modulo, string $accion): bool
{
    // Caché por petición: los permisos se cargan una sola vez
    static $cache = null;

    // Administrador: acceso total; rol o acción inválidos: denegado
    $rol = (int)($_SESSION['rol_id'] ?? 0);
    if ($rol === 1) {
        return true;
    }
    if ($rol <= 0 || !in_array($accion, ['ver', 'crear', 'editar', 'eliminar'], true)) {
        return false;
    }

    // Primera llamada: lee de la BD los permisos del rol por módulo
    if ($cache === null) {
        $cache = [];
        global $conexion;
        $conexion->set_charset('utf8mb4');
        $st = $conexion->prepare(
            "SELECT m.nombre, p.ver, p.crear, p.editar, p.eliminar
             FROM permisos p JOIN modulos m ON m.id = p.modulo_id WHERE p.rol_id = ?"
        );
        $st->bind_param('i', $rol);
        $st->execute();
        foreach ($st->get_result() as $f) {
            $cache[$f['nombre']] = $f;
        }
    }

    // Devuelve true solo si el permiso está marcado para ese módulo
    return !empty($cache[$modulo][$accion]);
}

// Responde 403 y detiene la página si el usuario no tiene el permiso
function exigirPermiso(string $modulo, string $accion): void
{
    if (!puede($modulo, $accion)) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}

function puedeVerDetraccion(): bool
{
    static $permitido = null;
    if ($permitido !== null) {
        return $permitido;
    }

    $rol = (int)($_SESSION['rol_id'] ?? 0);
    if ($rol === 1) {
        return $permitido = true;
    }
    if ($rol <= 0) {
        return $permitido = false;
    }

    global $conexion;
    $consulta = $conexion->prepare('SELECT nombre FROM roles WHERE id = ?');
    if (!$consulta) {
        error_log('Extranet_SBC: no se pudo verificar el acceso a detracciones: ' . $conexion->error);
        http_response_code(500);
        exit('No se pudo verificar el acceso. Contacte al administrador.');
    }
    $consulta->bind_param('i', $rol);
    if (!$consulta->execute() || !($resultado = $consulta->get_result())) {
        error_log('Extranet_SBC: no se pudo consultar el rol para detracciones: ' . $consulta->error);
        http_response_code(500);
        exit('No se pudo verificar el acceso. Contacte al administrador.');
    }
    $fila = $resultado->fetch_assoc();
    $consulta->close();
    $nombre = trim((string)($fila['nombre'] ?? ''));
    return $permitido = strcasecmp($nombre, 'Gerencial') === 0 || strcasecmp($nombre, 'Procesos') === 0;
}
