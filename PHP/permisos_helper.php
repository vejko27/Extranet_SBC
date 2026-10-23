<?php
// Permisos por rol y módulo. El Administrador (rol 1) siempre tiene todos los permisos.
// Acciones válidas: ver, crear, editar, eliminar
function puede(string $modulo, string $accion): bool
{
    static $cache = null;
    static $bd = null;

    $rol = (int)($_SESSION['rol_id'] ?? 0);
    if ($rol === 1) {
        return true;
    }
    if ($rol <= 0 || !in_array($accion, ['ver', 'crear', 'editar', 'eliminar'], true)) {
        return false;
    }

    if ($cache === null) {
        $cache = [];
        $bd = new mysqli('localhost', 'root', '', 'extranet_sbc');
        $bd->set_charset('utf8mb4');
        $st = $bd->prepare(
            "SELECT m.nombre, p.ver, p.crear, p.editar, p.eliminar
             FROM permisos p JOIN modulos m ON m.id = p.modulo_id WHERE p.rol_id = ?"
        );
        $st->bind_param('i', $rol);
        $st->execute();
        foreach ($st->get_result() as $f) {
            $cache[$f['nombre']] = $f;
        }
        $bd->close();
    }

    return !empty($cache[$modulo][$accion]);
}

function exigirPermiso(string $modulo, string $accion): void
{
    if (!puede($modulo, $accion)) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}
