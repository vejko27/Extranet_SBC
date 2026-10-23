<?php
session_start();
require 'bd_conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $user = $resultado->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        if ($user['estado'] !== 'activo') {
            header('Location: ../index.php?error=inactivo');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $user['id'];
        $_SESSION['rol_id'] = $user['rol_id'];
        $_SESSION['nombre'] = $user['nombre'];

        header('Location: dashboard.php');
        exit;
    }
}

header('Location: ../index.php?error=credenciales');
exit;
