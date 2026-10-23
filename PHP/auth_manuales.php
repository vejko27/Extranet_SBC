<?php
// El acceso a los manuales lo define el permiso "Ver" del módulo Manuales
require_once __DIR__ . '/auth.php';

exigirPermiso('Manuales', 'ver');
