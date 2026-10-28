ALTER TABLE proveedores
    ADD COLUMN banco_detracciones VARCHAR(80) NOT NULL DEFAULT '' AFTER cci,
    ADD COLUMN cuenta_detracciones VARCHAR(30) NOT NULL DEFAULT '' AFTER banco_detracciones;
