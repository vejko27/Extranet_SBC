<?php
// Cierra la sesión del usuario y vuelve a la pantalla de login.
require __DIR__ . '/sesion.php';
// Limpia variables, destruye la sesión y borra la cookie
session_unset();
session_destroy();
$p = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);

header('Location: ../index.php');
exit;
