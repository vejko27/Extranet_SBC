-- Ejecutar en la base de datos de la extranet antes de desplegar el código.
-- Conserva los manuales existentes y crea un catálogo independiente con la misma estructura.
-- Desplegar la carpeta Formatos_Procedimientos_Internos con su .htaccess y
-- permitir que el servidor web escriba en ella para poder subir archivos.
CREATE TABLE IF NOT EXISTS formatos_procedimientos_internos LIKE manuales;

-- El administrador tiene acceso automático. Los demás permisos se asignan desde
-- la pantalla Permisos; no se copian para evitar conceder acceso sin autorización.
INSERT INTO modulos (nombre)
SELECT 'Formatos de Procedimientos internos'
WHERE NOT EXISTS (
    SELECT 1 FROM modulos WHERE nombre = 'Formatos de Procedimientos internos'
);
