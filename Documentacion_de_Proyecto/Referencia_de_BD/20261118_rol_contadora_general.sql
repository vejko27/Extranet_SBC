-- Agrega el rol sin asignar permisos; se configuran desde Gestión de permisos.
INSERT INTO roles (nombre)
SELECT 'Contadora General'
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE nombre = 'Contadora General'
);
