-- Ejecutar una sola vez antes de habilitar el cuadro de monitores.
-- Gestionar Ver, Crear, Editar y Eliminar del módulo desde Gestión de permisos.
-- No se otorga acceso a otros roles; el Administrador siempre tiene acceso.
-- Las filas 02 y 03 comparten datos de compra y estado; se conservan al borrar la primera.
-- Borrar permite seleccionar una o varias filas.
-- El estado admite En Uso y Dado de Baja; los valores anteriores se conservan hasta modificarlos.
SET NAMES utf8mb4;

CREATE TABLE cuadro_monitores (
    id TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    datos LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cuadro_monitores (id, datos) VALUES (
    1,
    JSON_ARRAY(
        JSON_ARRAY('01', 'M026', 'LENOVO THINKVISION E24-30', '20603072368', 'E001-456', '7/04/2026', 'S/ 578.20', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('02', 'M022', 'Monitor Samsung: F24T350FHL serie: 0ASTHCNRB0022T', '20600101588', 'F001 - 14477', '21/05/2026', 'S/ 200.00', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', 'compra-2026'),
        JSON_ARRAY('03', 'M023', 'Monitor Samsung: F24T350FHL serie: 0ASTHCNT100284T', '', '', '', '', '', '', '4', 'compra-2026'),
        JSON_ARRAY('04', 'M004', '', '', '', '', '', '', '', '1', ''),
        JSON_ARRAY('05', 'M005', '', '', '', '', '', '', '', '1', ''),
        JSON_ARRAY('06', 'M006', '', '', '', '', '', '', '', '1', ''),
        JSON_ARRAY('07', 'M007', '', '', '', '', '', '', '', '1', ''),
        JSON_ARRAY('08', 'M008', '', '', '', '', '', '', '', '1', '')
    )
);

INSERT INTO modulos (nombre)
SELECT 'Monitores'
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre = 'Monitores');
