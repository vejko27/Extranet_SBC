-- Ejecutar una sola vez después de 20261118_sueldo_tipo_sueldo.sql.
-- Los importes existentes, incluido cero, se consideran préstamos registrados.
ALTER TABLE sueldo_mensual ADD COLUMN prestamo_estado VARCHAR(12) DEFAULT NULL;

UPDATE sueldo_mensual SET prestamo_estado = 'aplica' WHERE prestamo IS NOT NULL;
