ALTER TABLE proveedores
    ADD COLUMN banco VARCHAR(80) NOT NULL DEFAULT '' AFTER certificado,
    ADD COLUMN numero_cuenta VARCHAR(30) NOT NULL DEFAULT '' AFTER banco,
    ADD COLUMN cci VARCHAR(20) NOT NULL DEFAULT '' AFTER numero_cuenta;
