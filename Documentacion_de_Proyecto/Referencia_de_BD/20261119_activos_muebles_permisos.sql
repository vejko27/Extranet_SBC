-- Registra el módulo Muebles para gestionarlo desde Gestión de permisos.
-- No otorga acceso a otros roles; el Administrador siempre tiene acceso.
INSERT INTO modulos (nombre)
SELECT 'Muebles'
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre = 'Muebles');
