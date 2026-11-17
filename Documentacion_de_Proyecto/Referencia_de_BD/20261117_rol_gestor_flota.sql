-- Agrega el rol Gestor de Flota sin duplicarlo si el script se ejecuta más de una vez.
INSERT INTO roles (nombre)
SELECT 'Gestor de Flota'
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE nombre = 'Gestor de Flota'
);
