-- Ejecutar una sola vez después de 20261118_sueldo_mensual.sql.
-- Conserva el antiguo tipo de pago y recupera solo valores que ya eran frecuencias.
ALTER TABLE sueldo_mensual ADD COLUMN tipo_sueldo VARCHAR(20) DEFAULT NULL;

UPDATE sueldo_mensual
SET tipo_sueldo = CASE LOWER(TRIM(tipo_pago))
    WHEN 'quincenal' THEN 'Quincenal'
    WHEN 'mensual' THEN 'Mensual'
END
WHERE LOWER(TRIM(tipo_pago)) IN ('quincenal', 'mensual');
