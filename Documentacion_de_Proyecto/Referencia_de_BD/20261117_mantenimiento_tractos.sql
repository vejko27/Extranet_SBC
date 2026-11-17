-- Ejecutar una sola vez, después de 20261117_inventario_flota.sql.
-- Los archivos se guardan en bloques en la base de datos; no requieren carpeta pública.
CREATE TABLE mantenimientos_tractos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracto_id INT UNSIGNED NOT NULL,
    fecha_mantenimiento DATE NOT NULL,
    observaciones TEXT NOT NULL,
    PRIMARY KEY (id),
    KEY mantenimientos_tractos_fecha (tracto_id, fecha_mantenimiento),
    CONSTRAINT mantenimientos_flota_fk FOREIGN KEY (tracto_id) REFERENCES inventario_flota (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mantenimientos_facturas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mantenimiento_id INT UNSIGNED NOT NULL,
    tipo ENUM('pdf', 'xml') NOT NULL,
    tamano INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY mantenimientos_facturas_tipo (mantenimiento_id, tipo),
    CONSTRAINT mantenimientos_facturas_fk FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos_tractos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mantenimientos_factura_partes (
    factura_id INT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    contenido MEDIUMBLOB NOT NULL,
    PRIMARY KEY (factura_id, numero),
    CONSTRAINT mantenimientos_partes_fk FOREIGN KEY (factura_id) REFERENCES mantenimientos_facturas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
