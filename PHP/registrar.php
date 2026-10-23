<?php
$paginaActiva = 'registrar';
$tituloPagina = 'Registrar usuario';
require __DIR__ . '/layout_inicio.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Usuarios y roles', 'crear');

$conexion->set_charset('utf8mb4');
$roles = $conexion->query("SELECT id, nombre FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);
if (!$esAdmin) {
    $roles = array_values(array_filter($roles, fn($r) => (int)$r['id'] !== 1));
}
$msg = $_GET['msg'] ?? '';
?>

<h1>Registrar usuario</h1>
<p class="subtitulo">Crea una cuenta y asígnale un rol.</p>

<?php if ($msg === 'ok'): ?><div class="alerta ok">Usuario guardado correctamente.</div><?php endif; ?>
<?php if ($msg === 'error'): ?><div class="alerta err">No se pudo guardar el usuario. Revisa los datos.</div><?php endif; ?>

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
