-- Ejecutar una sola vez en la base de datos de la extranet antes de desplegar el código.
-- Los datos iniciales reciben la fecha de carga del inventario.
CREATE TABLE inventario_flota (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('tractos', 'carretas') NOT NULL,
    nombre VARCHAR(100) NOT NULL DEFAULT '',
    placa VARCHAR(15) NOT NULL,
    actualizado_en DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY inventario_flota_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventario_flota (tipo, nombre, placa, actualizado_en) VALUES
    ('tractos', 'YOLITA', 'C7M770', CURRENT_TIMESTAMP),
    ('tractos', 'MICAELA', 'C7M717', CURRENT_TIMESTAMP),
    ('tractos', 'coronel', 'Z4W754', CURRENT_TIMESTAMP),
    ('carretas', '', 'AUL999', CURRENT_TIMESTAMP),
    ('carretas', '', 'AUL972', CURRENT_TIMESTAMP),
    ('carretas', '', 'AWC982', CURRENT_TIMESTAMP),
    ('carretas', '', 'D4V978', CURRENT_TIMESTAMP),
    ('carretas', '', 'D4V984', CURRENT_TIMESTAMP);
