<?php
// Valida las credenciales enviadas desde index.php e inicia la sesión del usuario.
session_start();
require 'bd_conexion.php';

// Solo se acepta POST; cualquier otro acceso vuelve al inicio
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Datos recibidos del formulario
$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

// Busca al usuario por nombre de usuario con consulta preparada
$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

// Si existe, verifica la contraseña contra el hash guardado
if ($resultado->num_rows > 0) {
    $user = $resultado->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        // Rechaza usuarios que no estén activos
        if ($user['estado'] !== 'activo') {
            header('Location: ../index.php?error=inactivo');
            exit;
        }

        // Login correcto: renueva el id de sesión y guarda los datos del usuario
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $user['id'];
        $_SESSION['rol_id'] = $user['rol_id'];
        $_SESSION['nombre'] = $user['nombre'];

        header('Location: dashboard.php');
        exit;
    }
}

// Usuario inexistente o contraseña incorrecta
header('Location: ../index.php?error=credenciales');
exit;
