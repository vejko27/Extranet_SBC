-- Ejecutar antes de desplegar la integración con Gestión de permisos.
-- Los roles Operativo y Gerencial conservan inicialmente su acceso actual.
-- Las siguientes ejecuciones no modifican permisos ya configurados.
START TRANSACTION;

SET @tractos_nuevo = NOT EXISTS (
    SELECT 1 FROM modulos WHERE nombre = 'Tractos'
);
SET @carretas_nuevo = NOT EXISTS (
    SELECT 1 FROM modulos WHERE nombre = 'Carretas'
);

INSERT INTO modulos (nombre)
SELECT 'Tractos'
WHERE @tractos_nuevo = 1;

INSERT INTO modulos (nombre)
SELECT 'Carretas'
WHERE @carretas_nuevo = 1;

INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar)
SELECT roles.id, modulos.id, 1, 1, 1, 1
FROM roles
JOIN modulos ON modulos.nombre = 'Tractos'
WHERE @tractos_nuevo = 1
  AND roles.nombre IN ('Operativo', 'Gerencial')
  AND NOT EXISTS (
      SELECT 1 FROM permisos
      WHERE permisos.rol_id = roles.id AND permisos.modulo_id = modulos.id
  );

INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar)
SELECT roles.id, modulos.id, 1, 1, 1, 1
FROM roles
JOIN modulos ON modulos.nombre = 'Carretas'
WHERE @carretas_nuevo = 1
  AND roles.nombre IN ('Operativo', 'Gerencial')
  AND NOT EXISTS (
      SELECT 1 FROM permisos
      WHERE permisos.rol_id = roles.id AND permisos.modulo_id = modulos.id
  );

COMMIT;
