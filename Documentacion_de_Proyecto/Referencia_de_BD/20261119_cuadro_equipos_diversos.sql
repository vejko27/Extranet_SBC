-- Ejecutar una sola vez antes de habilitar el cuadro de equipos diversos.
-- Gestionar Ver, Crear, Editar y Eliminar del módulo desde Gestión de permisos.
-- No se otorga acceso a otros roles; el Administrador siempre tiene acceso.
-- Las dos primeras filas comparten datos de compra y estado; se conservan al borrar la primera.
-- Borrar permite seleccionar una o varias filas.
-- El estado admite En Uso y Dado de Baja; los valores anteriores se conservan hasta modificarlos.
SET NAMES utf8mb4;

CREATE TABLE cuadro_equipos_diversos (
    id TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    datos LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cuadro_equipos_diversos (id, datos) VALUES (
    1,
    JSON_ARRAY(
        JSON_ARRAY('01', '000001', '1 UNIDAD EN ESTADO SOLIDO KINGSTON NV3 1TB 6000MB/S NVME M.2 PCI.E SNV3S/1000G', '10436713130', 'E001-239', '19/03/2026', 'S/ 960.01', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', 'compra-2026'),
        JSON_ARRAY('02', '000002', '2UND ESTABILIZADOR FORZA DE 8 TOMAS RIGIDO 900VA', '', '', '', '', '', '', '4', 'compra-2026'),
        JSON_ARRAY('03', '000003', '1 UND ESTABILIZADOR DE CORRIENTE DE 8 TOMAS Y 900W', '10436713130', 'E001-234', '17/01/2026', 'S/ 94.40', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('04', '000004', 'MOCHILA TEROS DE 15.6 PARA LAPTOPS', '10436713130', 'E001-229', '5/12/2025', 'S/ 94.40', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('05', '000005', 'ESTABILIZADOR DE CORRIENTE DE 900W Y 8 TOMAS DE CORRIENTE', '10436713130', 'E001-231', '30/12/2025', 'S/ 200.60', 'ACTIVO EN CURSO EN PRODUCCION', '', '4', ''),
        JSON_ARRAY('06', '000006', 'VENTA PRG. EJEC. CONCAR 2026 VISUAL CB ST REDES', '20211683199', 'F002-0057490', '22/05/2026', 'S/ 1,630.00', 'Renovación de licencias Concar y Sire 2026 contabilidad', '', '4', ''),
        JSON_ARRAY('07', '000007', 'SERVICIO DE MANTENIMIENTO DE IMPRESORA HP 428', '10436713130', 'E001-252', '22/05/2026', 'S/ 212.40', 'Fue reemplazada x la factura 259, x error del proveedor Matallana', '', '2', ''),
        JSON_ARRAY('08', '000008', '1 RACK PARA MONITOR DE 3 PZ TIPO BRAZO MOVIBLE EMPOTRABLE', '10436713130', 'E001-262', '4/06/2026', 'S/ 212.40', 'ACTIVO EN CURSO EN PRODUCCION', '', '2', ''),
        JSON_ARRAY('09', '000009', 'REPARACION DE ENGRANAJE ENCODER', '10436713130', 'E001-263', '10/06/2026', 'S/ 94.40', '', '', '2', ''),
        JSON_ARRAY('10', '000010', 'TECLADO Y MOUSE HP 225 USB -ESPAÑOL (AX2Y7AA)', '10436713130', 'E001-264', '15/06/2026', 'S/ 160.00', '', '', '2', ''),
        JSON_ARRAY('11', '000011', '', '', '', '', '', '', '', '', '')
    )
);

INSERT INTO modulos (nombre)
SELECT 'Equipos Diversos'
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre = 'Equipos Diversos');
