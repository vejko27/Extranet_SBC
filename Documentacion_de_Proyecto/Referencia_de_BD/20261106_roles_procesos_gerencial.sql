-- Agrega los roles Procesos y Gerencial sin duplicarlos si el script se ejecuta más de una vez.
INSERT INTO roles (nombre)
SELECT nuevos.nombre
FROM (
    SELECT 'Procesos' AS nombre
    UNION ALL
    SELECT 'Gerencial'
) AS nuevos
WHERE NOT EXISTS (
    SELECT 1
    FROM roles existentes
    WHERE existentes.nombre = nuevos.nombre
);
