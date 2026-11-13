<?php
// Página de inicio/login: redirige al panel si ya hay sesión y muestra el formulario de acceso.
require_once __DIR__ . '/PHP/sesion.php';
// Si el usuario ya inició sesión, va directo al dashboard
if (isset($_SESSION['id_usuario'])) {
    header('Location: PHP/dashboard.php');
    exit;
}

// Mensajes de error según el código recibido por GET
$errores = [
    'credenciales' => 'Usuario o contraseña incorrectos.',
    'inactivo' => 'Usuario inactivo. Contacta al administrador.',
];
$err = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extranet de SBC_TRANSPORT</title>
    <link rel="icon" type="image/x-icon" href="icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="shortcut icon" href="icono_de_navegación/Icono_de_pestana_de_navegacion.ico">
    <link rel="stylesheet" href="estilos/Style.css?v=<?= filemtime(__DIR__ . '/estilos/Style.css') ?>" />
  </head>
  <body>
    <div class="login-container">
      <div class="overlay"></div>
      <!-- Formulario de login: envía usuario y contraseña a PHP/login.php -->
      <form class="login-form" action="PHP/login.php" method="POST">
        <h1>Sistema de Gestión Interno SBC</h1>
        <h2>SBC TRANSPORT SAC</h2>
        <p>Ingresa a tu cuenta corporativa</p>

        <?php /* Muestra el error de login si el código es válido */ ?>
        <?php if (isset($errores[$err])): ?>
          <p style="color:#b00020;font-weight:bold;"><?= htmlspecialchars($errores[$err]) ?></p>
        <?php endif; ?>

        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" placeholder="Usuario" required>

        <label for="password">Contraseña</label>
        <div class="password-field">
          <input type="password" id="password" name="password" placeholder=".............." required>
          <button class="password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="password">
            <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
        <button class="botton" type="submit">Iniciar Sesión SBC</button>
      </form>
    </div>
    <script>
      const passwordInput = document.getElementById('password');
      const passwordToggle = document.querySelector('.password-toggle');

      passwordToggle.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'text';
        passwordInput.type = isVisible ? 'password' : 'text';
        passwordToggle.setAttribute('aria-pressed', String(!isVisible));
        passwordToggle.setAttribute('aria-label', isVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
      });
    </script>
  </body>
</html>
