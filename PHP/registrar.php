<?php
// Formulario para registrar un nuevo usuario (el guardado lo hace guardar_usuario.php).
$paginaActiva = 'registrar';
$tituloPagina = 'Registrar usuario';
// Carga layout y conexión a la BD, y valida el permiso de crear usuarios
require __DIR__ . '/layout_inicio.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Usuarios y roles', 'crear');

// Roles disponibles; quien no es administrador no puede asignar el rol 1
$conexion->set_charset('utf8mb4');
$roles = $conexion->query("SELECT id, nombre FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);
if (!$esAdmin) {
    $roles = array_values(array_filter($roles, fn($r) => (int)$r['id'] !== 1));
}
// Mensaje de resultado tras guardar (ok/error)
$msg = $_GET['msg'] ?? '';
?>

<h1>Registrar usuario</h1>
<p class="subtitulo">Crea una cuenta y asígnale un rol.</p>

<?php /* Alertas de resultado */ ?>
<?php if ($msg === 'ok'): ?><div class="alerta ok">Usuario guardado correctamente.</div><?php endif; ?>
<?php if ($msg === 'error'): ?><div class="alerta err">No se pudo guardar el usuario. Revisa los datos.</div><?php endif; ?>

<!-- Formulario de alta: envía los datos por POST a guardar_usuario.php -->
<div class="card" style="max-width:520px">
    <form action="guardar_usuario.php" method="POST">
        <div class="form-grupo">
            <label>Nombre y apellido</label>
            <input type="text" name="nombre" required>
        </div>
        <div class="form-grupo">
            <label>Usuario</label>
            <input type="text" name="usuario" required>
        </div>
        <div class="form-grupo">
            <label>Contraseña</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-grupo">
            <label>Rol</label>
            <select name="rol_id">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Guardar</button>
    </form>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
