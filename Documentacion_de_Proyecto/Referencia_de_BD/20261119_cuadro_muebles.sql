-- Ejecutar una sola vez antes de usar la edición del cuadro de muebles.
-- Los importes y datos de compra agrupados se conservan una sola vez.
-- Gestionar Ver, Crear, Editar y Eliminar del módulo Muebles desde Gestión de permisos.
-- Borrar elimina solamente la fila seleccionada y conserva los datos de compra compartidos.
-- Las filas nuevas pueden quedar sin clasificar y completarse posteriormente.
-- El estado admite En Uso y Dado de Baja; los valores anteriores se conservan hasta modificarlos.
SET NAMES utf8mb4;
CREATE TABLE cuadro_muebles (
    id TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    datos LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cuadro_muebles (id, datos) VALUES (
    1,
    JSON_ARRAY(
        JSON_ARRAY('01', '', 'Credenza de melamina / Ocampos', '20601143888', 'E001-748', '5/02/2026', 'S/ 613.60', '', '', '4'),
        JSON_ARRAY('02', 'Código identificador', 'Credenza de Michel Silva', '10424249829', 'E001-259', '11/05/2026', 'S/ 480.00', '', '', '4'),
        JSON_ARRAY('03', '0051', 'Credenza larga de color marrón / Ubicación SBC Transport SAC', '20600101588', 'F001-14476', '21/05/2026', 'S/ 800.00', 'En uso', 'Compra de activos Business a SBC 21/05/2026', '2'),
        JSON_ARRAY('04', '0052', 'Mesa marrón para 1 usuario con cajón / / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('05', '0053', 'Mesa marrón para 1 usuario con cajón / / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('06', '0054', 'Mesa marrón para 1 usuario con cajón / / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('07', '0055', 'Mesa ploma con cajón básico / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('08', '0056', 'Mesa ploma con 3 cajones básico / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('09', '0057', 'Mesa básica armable / Ubicación SBC Transport SAC', '', '', '', '', 'En uso', '', '2'),
        JSON_ARRAY('10', '0010', '', '', '', '', '', '', '', '1'),
        JSON_ARRAY('11', '0011', '', '', '', '', '', '', '', '1'),
        JSON_ARRAY('12', '0012', '', '', '', '', '', '', '', '1'),
        JSON_ARRAY('13', '0013', '', '', '', '', '', '', '', '1'),
        JSON_ARRAY('14', '0014', '', '', '', '', '', '', '', '1')
    )
);
