-- Ejecutar después de 20261118_sueldo_permisos.sql.
-- Los importes se ingresan manualmente y los valores desconocidos quedan en NULL.
CREATE TABLE IF NOT EXISTS sueldo_mensual (
    personal_id INT NOT NULL,
    periodo DATE NOT NULL,
    ingresos DECIMAL(12,2) DEFAULT NULL,
    descuentos DECIMAL(12,2) DEFAULT NULL,
    quincena DECIMAL(12,2) DEFAULT NULL,
    prestamo DECIMAL(12,2) DEFAULT NULL,
    neto DECIMAL(12,2) DEFAULT NULL,
    aporte DECIMAL(12,2) DEFAULT NULL,
    sueldo_basico DECIMAL(12,2) DEFAULT NULL,
    sueldo_diario DECIMAL(12,2) DEFAULT NULL,
    sueldo_hora DECIMAL(12,2) DEFAULT NULL,
    fecha_pago DATE DEFAULT NULL,
    tipo_pago VARCHAR(40) DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (personal_id, periodo),
    CONSTRAINT sueldo_mensual_personal_fk FOREIGN KEY (personal_id) REFERENCES personal (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
