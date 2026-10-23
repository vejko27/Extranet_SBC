<?php
// Cierra la sesión del usuario y vuelve a la pantalla de login.
session_start();
// Limpia variables y destruye la sesión
session_unset();
session_destroy();

header('Location: ../index.php');
exit;
