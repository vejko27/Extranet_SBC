-- Ejecutar después de 20261117_inventario_flota.sql.
-- Conserva las asignaciones por fecha para consultar semanas anteriores.
CREATE TABLE programacion_flota (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracto_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    carreta_id INT UNSIGNED DEFAULT NULL,
    nota VARCHAR(500) NOT NULL DEFAULT '',
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY programacion_flota_tracto_fecha (tracto_id, fecha),
    UNIQUE KEY programacion_flota_carreta_fecha (fecha, carreta_id),
    CONSTRAINT programacion_flota_tracto_fk FOREIGN KEY (tracto_id) REFERENCES inventario_flota (id),
    CONSTRAINT programacion_flota_carreta_fk FOREIGN KEY (carreta_id) REFERENCES inventario_flota (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
