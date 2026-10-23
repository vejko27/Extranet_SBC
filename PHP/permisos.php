<?php
// Gestión de permisos por rol y módulo (solo Administrador): formulario de casillas y guardado por POST.
$paginaActiva = 'permisos';
$tituloPagina = 'Gestión de permisos';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Solo el rol Administrador (1) puede entrar
if ((int)($_SESSION['rol_id'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado');
}

$conexion->set_charset('utf8mb4');
// Token CSRF de sesión y función e() para escapar HTML
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Acciones disponibles y consultas de roles y módulos
$acciones = ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'];
$roles = $conexion->query("SELECT id, nombre FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$rolesIds = array_map('intval', array_column($roles, 'id'));
$modulos = $conexion->query("SELECT id, nombre FROM modulos ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Procesamiento POST: valida CSRF y rol, y guarda los permisos de cada módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rolId = (int)($_POST['rol_id'] ?? 0);
    $ok = $err = '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif (!in_array($rolId, $rolesIds, true) || $rolId === 1) {
        $err = 'Rol no válido. Los permisos del Administrador no se modifican.';
    } else {
        // Inserta o actualiza (upsert) los permisos del rol por módulo
        $st = $conexion->prepare(
            "INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar) VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE ver = VALUES(ver), crear = VALUES(crear), editar = VALUES(editar), eliminar = VALUES(eliminar)"
        );
        foreach ($modulos as $m) {
            $v = isset($_POST['p'][$m['id']]['ver']) ? 1 : 0;
            $c = isset($_POST['p'][$m['id']]['crear']) ? 1 : 0;
            $ed = isset($_POST['p'][$m['id']]['editar']) ? 1 : 0;
            $el = isset($_POST['p'][$m['id']]['eliminar']) ? 1 : 0;
            $mid = (int)$m['id'];
            $st->bind_param('iiiiii', $rolId, $mid, $v, $c, $ed, $el);
            $st->execute();
        }
        $ok = 'Permisos actualizados correctamente.';
    }

    // Post/Redirect/Get: evita reenviar el formulario al refrescar
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: permisos.php?rol=' . $rolId);
    exit;
}

// Mensaje flash de la operación anterior
$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);

// Rol seleccionado (el primero si no es válido)
$rolSel = (int)($_GET['rol'] ?? 0);
if (!in_array($rolSel, $rolesIds, true)) {
    $rolSel = $rolesIds[0] ?? 0;
}
$esAdminSel = $rolSel === 1;

// Permisos actuales del rol seleccionado, indexados por módulo
$actuales = [];
$st = $conexion->prepare("SELECT modulo_id, ver, crear, editar, eliminar FROM permisos WHERE rol_id = ?");
$st->bind_param('i', $rolSel);
$st->execute();
foreach ($st->get_result() as $p) {
    $actuales[(int)$p['modulo_id']] = $p;
}

require __DIR__ . '/layout_inicio.php';
?>

<h1>Gestión de permisos</h1>
<p class="subtitulo">Define qué puede hacer cada rol en cada módulo.</p>

<?php /* Alertas de resultado */ ?>
<?php if ($flash['ok']): ?><div class="alerta ok"><?= e($flash['ok']) ?></div><?php endif; ?>
<?php if ($flash['err']): ?><div class="alerta err"><?= e($flash['err']) ?></div><?php endif; ?>

<!-- Selector de rol: recarga la página con el rol elegido -->
<div class="card" style="margin-bottom:24px">
    <form method="GET" class="form-fila">
        <div class="form-grupo">
            <label>Rol</label>
            <select name="rol" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $rolSel ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Tabla de permisos: una fila por módulo y una casilla por acción (bloqueada para el Administrador) -->
<div class="card">
    <?php if ($esAdminSel): ?>
        <p class="subtitulo" style="margin-bottom:16px">El Administrador siempre tiene todos los permisos y no se puede modificar.</p>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="rol_id" value="<?= $rolSel ?>">
        <table>
            <tr>
                <th>Módulo</th>
                <?php foreach ($acciones as $a): ?><th class="col-check"><?= e($a) ?></th><?php endforeach; ?>
            </tr>
            <?php foreach ($modulos as $m): $p = $actuales[(int)$m['id']] ?? []; ?>
                <tr>
                    <td data-label="Módulo"><?= e($m['nombre']) ?></td>
                    <?php foreach ($acciones as $k => $a): ?>
                        <td data-label="<?= e($a) ?>" class="col-check">
                            <input type="checkbox" name="p[<?= (int)$m['id'] ?>][<?= $k ?>]" value="1"
                                <?= $esAdminSel || !empty($p[$k]) ? 'checked' : '' ?>
                                <?= $esAdminSel ? 'disabled' : '' ?>>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$esAdminSel): ?>
            <button type="submit" style="margin-top:18px">Guardar permisos</button>
        <?php endif; ?>
    </form>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
