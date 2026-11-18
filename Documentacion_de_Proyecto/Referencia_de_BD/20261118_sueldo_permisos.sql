-- Ejecutar antes de desplegar Sueldo para habilitar su gestión por rol.
-- No se otorga acceso a otros roles; el Administrador siempre tiene acceso.
INSERT INTO modulos (nombre)
SELECT 'Sueldo'
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre = 'Sueldo');
