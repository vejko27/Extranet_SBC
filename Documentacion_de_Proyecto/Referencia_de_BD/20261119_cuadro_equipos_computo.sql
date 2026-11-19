-- Ejecutar una sola vez antes de habilitar el cuadro de equipos de cómputo.
-- Gestionar Ver, Crear, Editar y Eliminar del módulo desde Gestión de permisos.
-- No se otorga acceso a otros roles; el Administrador siempre tiene acceso.
-- Borrar permite seleccionar una o varias filas.
-- El estado admite En Uso y Dado de Baja; los valores anteriores se conservan hasta modificarlos.
SET NAMES utf8mb4;

CREATE TABLE cuadro_equipos_computo (
    id TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    datos LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cuadro_equipos_computo (id, datos) VALUES (
    1,
    JSON_ARRAY(
        JSON_ARRAY('01', 'Activo 001', 'LENOVO V15 G5. PROCESADOR INTEL I5-13420H; MEMORIA DE 16GB; ALMACENAMIENTO SSD DE 512GB; PANTALLA DE 15.6 PULGADAS. SERIE: PF9XB5922249 FREEDOS', '20603072368', 'E001-440', '3/12/2025', 'S/ 2,452.04', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('02', 'Activo 002', 'MINI PC HP PRO 260 G9 CI5-1334U/16GB RAM/SSD512GB/FREE MONITOR PLANO TEROS TE-2415S, 24 PULGADAS FHD IPS, HDMI, DP CABLE ESPECIAL ADAPTADOR DE 1.8MTRS H/M ESTÁNDAR', '10436713130', 'E001-236', '28/01/2026', 'S/ 2,526.73', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('03', 'Activo 003', 'Compra de access Point D-LINK DAP 2610', '10457600211', 'E001-56', '21/09/2022', 'S/ 650.00', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('04', 'Activo 004', 'Marcador KIT C. ASIST. FACE-T5', '20601675103', 'E001-6133', '6/06/2024', 'S/ 553.42', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('05', 'Activo 005', 'SWITCH TP LINK TL-1016DE DE 16 PUERTOS', '10436713130', 'E001-83', '8/06/2023', 'S/ 430.00', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('06', 'Activo 006', 'SISTEMA DE CAMARAS E INSTALACION DE 01 CAMARA DE VIDEOVIGILANCIA CON DVR Y CABLEADO DE 03 TOMAS ELECTRICAS EN LA OFICINA DE CALLAO.', '20609569680', 'E001-230', '5/03/2024', 'S/ 1,799.50', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('07', 'Activo 007', 'Compra de Gabinete de red', '10457600211', 'E001-51', '26/07/2022', 'S/ 670.00', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('08', '', '', '', '', '', '', '', '', '', '')
    )
);

INSERT INTO modulos (nombre)
SELECT 'Equipos de Cómputo'
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre = 'Equipos de Cómputo');
