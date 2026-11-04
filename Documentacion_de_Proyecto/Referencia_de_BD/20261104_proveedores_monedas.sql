-- Monedas de las cuentas bancarias de proveedores (valores: USD, PEN o 'USD,PEN').
-- Solo agrega columnas con valor vacío por defecto; no modifica ni elimina datos existentes.
-- Es seguro re-ejecutarlo: cada columna se agrega únicamente si todavía no existe
-- (proveedores.php también las crea automáticamente al abrirse).

SET @agregar = (SELECT IF(COUNT(*) = 0,
    "ALTER TABLE proveedores ADD COLUMN moneda_cuenta VARCHAR(7) NOT NULL DEFAULT ''",
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedores' AND COLUMN_NAME = 'moneda_cuenta');
PREPARE sentencia FROM @agregar; EXECUTE sentencia; DEALLOCATE PREPARE sentencia;

SET @agregar = (SELECT IF(COUNT(*) = 0,
    "ALTER TABLE proveedores ADD COLUMN moneda_cuenta2 VARCHAR(7) NOT NULL DEFAULT ''",
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedores' AND COLUMN_NAME = 'moneda_cuenta2');
PREPARE sentencia FROM @agregar; EXECUTE sentencia; DEALLOCATE PREPARE sentencia;

SET @agregar = (SELECT IF(COUNT(*) = 0,
    "ALTER TABLE proveedores ADD COLUMN moneda_cuenta3 VARCHAR(7) NOT NULL DEFAULT ''",
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedores' AND COLUMN_NAME = 'moneda_cuenta3');
PREPARE sentencia FROM @agregar; EXECUTE sentencia; DEALLOCATE PREPARE sentencia;

SET @agregar = (SELECT IF(COUNT(*) = 0,
    "ALTER TABLE proveedores ADD COLUMN moneda_detracciones VARCHAR(7) NOT NULL DEFAULT ''",
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedores' AND COLUMN_NAME = 'moneda_detracciones');
PREPARE sentencia FROM @agregar; EXECUTE sentencia; DEALLOCATE PREPARE sentencia;
