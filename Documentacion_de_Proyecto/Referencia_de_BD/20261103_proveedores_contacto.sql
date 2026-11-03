ALTER TABLE proveedores
    ADD COLUMN persona_contacto VARCHAR(120) NOT NULL DEFAULT '' AFTER cuenta_detracciones,
    ADD COLUMN correo VARCHAR(120) NOT NULL DEFAULT '' AFTER persona_contacto,
    ADD COLUMN celular VARCHAR(20) NOT NULL DEFAULT '' AFTER correo,
    ADD COLUMN pagina_web VARCHAR(200) NOT NULL DEFAULT '' AFTER celular,
    ADD COLUMN cobranza_persona VARCHAR(120) NOT NULL DEFAULT '' AFTER pagina_web,
    ADD COLUMN cobranza_correo VARCHAR(120) NOT NULL DEFAULT '' AFTER cobranza_persona,
    ADD COLUMN cobranza_celular VARCHAR(20) NOT NULL DEFAULT '' AFTER cobranza_correo,
    ADD COLUMN pagos_persona VARCHAR(120) NOT NULL DEFAULT '' AFTER cobranza_celular,
    ADD COLUMN pagos_correo VARCHAR(120) NOT NULL DEFAULT '' AFTER pagos_persona,
    ADD COLUMN pagos_celular VARCHAR(20) NOT NULL DEFAULT '' AFTER pagos_correo;
