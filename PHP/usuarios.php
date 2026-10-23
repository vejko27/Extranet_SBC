<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

$conexion->set_charset('utf8mb4');

// El acceso lo define el permiso del módulo "Usuarios y roles"
exigirPermiso('Usuarios y roles', 'ver');
$puedeCrear = puede('Usuarios y roles', 'crear');
$puedeEditar = puede('Usuarios y roles', 'editar');
$esAdminSesion = (int)($_SESSION['rol_id'] ?? 0) === 1;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$roles = $conexion->query("SELECT id, nombre FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$rolesValidos = array_map('intval', array_column($roles, 'id'));
$rolesNombre = array_column($roles, 'nombre', 'id');
// Solo el Administrador puede asignar o modificar el rol Administrador
if (!$esAdminSesion) {
    $roles = array_values(array_filter($roles, fn($r) => (int)$r['id'] !== 1));
    $rolesValidos = array_values(array_diff($rolesValidos, [1]));
}
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token inválido');
    }

    $accion = $_POST['accion'] ?? '';

    $idObjetivo = (int)($_POST['id'] ?? 0);
    $objetivoEsAdmin = false;
    if ($idObjetivo > 0) {
        $q = $conexion->prepare("SELECT rol_id FROM usuarios WHERE id = ?");
        $q->bind_param("i", $idObjetivo);
        $q->execute();
        $objetivoEsAdmin = (int)($q->get_result()->fetch_assoc()['rol_id'] ?? 0) === 1;
    }

    if ($accion === 'crear' && !$puedeCrear) {
        $error = 'No tienes permiso para crear usuarios.';
    } elseif (in_array($accion, ['editar', 'toggle_estado'], true) && !$puedeEditar) {
        $error = 'No tienes permiso para editar usuarios.';
    } elseif (in_array($accion, ['editar', 'toggle_estado'], true) && $objetivoEsAdmin && !$esAdminSesion) {
        $error = 'Solo un administrador puede modificar a otro administrador.';
    } elseif ($accion === 'crear') {
        $nombre  = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $pass    = $_POST['password'] ?? '';
        $rol_id  = (int)($_POST['rol_id'] ?? 0);

        if ($nombre === '' || $usuario === '' || $pass === '' || !in_array($rol_id, $rolesValidos, true)) {
            $error = 'Completa todos los campos y elige un rol válido.';
        } else {
            $chk = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $chk->bind_param("s", $usuario);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'Ese usuario ya existe.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $st = $conexion->prepare("INSERT INTO usuarios (nombre, usuario, email, password, rol_id) VALUES (?,?,?,?,?)");
                $st->bind_param("ssssi", $nombre, $usuario, $email, $hash, $rol_id);
                $st->execute();
                $mensaje = 'Usuario creado correctamente.';
            }
        }
    } elseif ($accion === 'toggle_estado') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['id_usuario']) {
            $error = 'No puedes desactivarte a ti mismo.';
        } else {
            $st = $conexion->prepare("UPDATE usuarios SET estado = IF(estado = 'activo', 'inactivo', 'activo') WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $mensaje = 'Estado del usuario actualizado.';
        }
    } elseif ($accion === 'editar') {
        $id      = (int)($_POST['id'] ?? 0);
        $nombre  = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $pass    = $_POST['password'] ?? '';
        $rol_id  = (int)($_POST['rol_id'] ?? 0);
        $estado  = ($_POST['estado'] ?? '') === 'inactivo' ? 'inactivo' : 'activo';

        if ($nombre === '' || $usuario === '') {
            $error = 'Nombre y usuario son obligatorios.';
        } elseif (!in_array($rol_id, $rolesValidos, true)) {
            $error = 'Rol inválido.';
        } elseif ($id === (int)$_SESSION['id_usuario'] && ($rol_id !== 1 || $estado !== 'activo')) {
            $error = 'No puedes quitarte el rol de administrador ni desactivarte a ti mismo.';
        } else {
            $chk = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id <> ?");
            $chk->bind_param("si", $usuario, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'Ese nombre de usuario ya está en uso.';
            } else {
                $st = $conexion->prepare("UPDATE usuarios SET nombre = ?, usuario = ?, email = ?, rol_id = ?, estado = ? WHERE id = ?");
                $st->bind_param("sssisi", $nombre, $usuario, $email, $rol_id, $estado, $id);
                $st->execute();

                if ($pass !== '') {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $st = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $st->bind_param("si", $hash, $id);
                    $st->execute();
                }
                $mensaje = 'Usuario actualizado.';
            }
        }
    }
}

$usuarios = $conexion->query(
    "SELECT id, nombre, usuario, email, rol_id, estado FROM usuarios ORDER BY id"
)->fetch_all(MYSQLI_ASSOC);

$paginaActiva = 'usuarios';
$tituloPagina = 'Gestión de usuarios';
require __DIR__ . '/layout_inicio.php';
?>

<h1>Gestión de usuarios</h1>
<p class="subtitulo">Crea usuarios y asigna sus roles.</p>

<?php if ($mensaje): ?><div class="alerta ok"><?= e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err"><?= e($error) ?></div><?php endif; ?>

<?php if ($puedeCrear): ?>
<div class="card">
    <h2>Crear usuario</h2>
    <form method="POST" class="form-fila">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" placeholder="Nombre y apellido" required>
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="email" name="email" placeholder="Email">
        <input type="password" name="password" placeholder="Contraseña" required>
        <select name="rol_id" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Crear</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Usuarios registrados</h2>
    <div class="tabla-wrap">
    <table>
        <tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td data-label="ID"><?= (int)$u['id'] ?></td>
            <td data-label="Nombre"><?= e($u['nombre']) ?></td>
            <td data-label="Usuario"><?= e($u['usuario']) ?></td>
            <td data-label="Email"><?= e($u['email']) ?></td>
            <td data-label="Rol"><span class="badge"><?= e($rolesNombre[(int)$u['rol_id']] ?? 'Sin rol') ?></span></td>
            <td data-label="Estado"><span class="badge <?= $u['estado'] === 'activo' ? 'badge-ok' : 'badge-off' ?>"><?= e(ucfirst($u['estado'])) ?></span></td>
            <td data-label="Acciones" class="celda-acciones">
                <?php if ($puedeEditar && ($esAdminSesion || (int)$u['rol_id'] !== 1)): ?>
                <button type="button" class="btn-editar"
                    data-id="<?= (int)$u['id'] ?>"
                    data-nombre="<?= e($u['nombre']) ?>"
                    data-usuario="<?= e($u['usuario']) ?>"
                    data-email="<?= e($u['email']) ?>"
                    data-rol="<?= (int)$u['rol_id'] ?>"
                    data-estado="<?= e($u['estado']) ?>">Editar</button>
                <?php if ((int)$u['id'] !== (int)$_SESSION['id_usuario']): ?>
                <form method="POST" class="form-inline">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="toggle_estado">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="<?= $u['estado'] === 'activo' ? 'btn-peligro' : 'btn-ok' ?>">
                        <?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>

<dialog id="modalEditar">
    <form method="POST">
        <h2>Editar usuario</h2>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" id="e_id">
        <div class="form-grupo"><label>Nombre y apellido</label><input type="text" name="nombre" id="e_nombre" required></div>
        <div class="form-grupo"><label>Usuario</label><input type="text" name="usuario" id="e_usuario" required></div>
        <div class="form-grupo"><label>Email</label><input type="email" name="email" id="e_email"></div>
        <div class="form-grupo"><label>Nueva contraseña (dejar vacío para no cambiarla)</label><input type="password" name="password" autocomplete="new-password"></div>
        <div class="form-grupo"><label>Rol</label>
            <select name="rol_id" id="e_rol">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grupo"><label>Estado</label>
            <select name="estado" id="e_estado">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn-sec" id="cerrarModal">Cancelar</button>
            <button type="submit">Guardar cambios</button>
        </div>
    </form>
</dialog>

<script>
const modal = document.getElementById('modalEditar');
document.querySelectorAll('.btn-editar').forEach(b => b.addEventListener('click', () => {
    const d = b.dataset;
    document.getElementById('e_id').value = d.id;
    document.getElementById('e_nombre').value = d.nombre;
    document.getElementById('e_usuario').value = d.usuario;
    document.getElementById('e_email').value = d.email;
    document.getElementById('e_rol').value = d.rol;
    document.getElementById('e_estado').value = d.estado;
    modal.querySelector('input[name=password]').value = '';
    modal.showModal();
}));
document.getElementById('cerrarModal').addEventListener('click', () => modal.close());
modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });
</script>


<?php require __DIR__ . '/layout_fin.php'; ?>
