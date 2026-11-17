-- Ejecutar una sola vez, después de 20261117_inventario_flota.sql.
-- Los archivos se guardan en bloques en la base de datos; no requieren carpeta pública.
CREATE TABLE lavados_tractos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracto_id INT UNSIGNED NOT NULL,
    fecha_lavado DATE NOT NULL,
    PRIMARY KEY (id),
    KEY lavados_tractos_fecha (tracto_id, fecha_lavado),
    CONSTRAINT lavados_tractos_flota_fk FOREIGN KEY (tracto_id) REFERENCES inventario_flota (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lavados_facturas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lavado_id INT UNSIGNED NOT NULL,
    tipo ENUM('pdf', 'xml') NOT NULL,
    tamano INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY lavados_facturas_tipo (lavado_id, tipo),
    CONSTRAINT lavados_facturas_lavado_fk FOREIGN KEY (lavado_id) REFERENCES lavados_tractos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lavados_factura_partes (
    factura_id INT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    contenido MEDIUMBLOB NOT NULL,
    PRIMARY KEY (factura_id, numero),
    CONSTRAINT lavados_partes_factura_fk FOREIGN KEY (factura_id) REFERENCES lavados_facturas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
