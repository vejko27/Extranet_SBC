<?php
// Procesa el alta de un usuario (POST desde registrar.php) y redirige con el resultado.
require __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Solo quien tiene permiso de crear en Usuarios y roles
exigirPermiso('Usuarios y roles', 'crear');

// Solo se acepta el envío por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrar.php');
    exit;
}

// Lectura y limpieza de los datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$rol_id = (int)($_POST['rol_id'] ?? 0);

// Validación de campos obligatorios
// Solo el Administrador puede crear otros administradores
if ($nombre === '' || $usuario === '' || $password === '' || $rol_id <= 0
    || ($rol_id === 1 && (int)$_SESSION['rol_id'] !== 1)) {
    header('Location: registrar.php?msg=error');
    exit;
}

// La contraseña se guarda cifrada
$hash = password_hash($password, PASSWORD_DEFAULT);

// Inserción con consulta preparada
$stmt = $conexion->prepare("INSERT INTO usuarios (nombre,usuario,password,rol_id) VALUES (?,?,?,?)");
$stmt->bind_param("sssi", $nombre, $usuario, $hash, $rol_id);

// Redirige con msg=ok o msg=error según el resultado del INSERT
header('Location: registrar.php?msg=' . ($stmt->execute() ? 'ok' : 'error'));
exit;
