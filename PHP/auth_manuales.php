<?php
// Guardia de acceso al módulo Manuales (requiere sesión y permiso "ver").
// El acceso a los manuales lo define el permiso "Ver" del módulo Manuales
require_once __DIR__ . '/auth.php';

// Corta la ejecución si el usuario no tiene permiso de ver manuales
exigirPermiso('Manuales', 'ver');
