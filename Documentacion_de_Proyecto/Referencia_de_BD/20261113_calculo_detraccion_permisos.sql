-- Ejecutar antes de desplegar la integración con Gestión de permisos.
-- Al registrar el módulo por primera vez, conserva el acceso de Procesos y Gerencial.
-- Las siguientes ejecuciones no modifican permisos ya configurados.
START TRANSACTION;

SET @detraccion_nuevo = NOT EXISTS (
    SELECT 1 FROM modulos WHERE nombre = 'Cálculo de detracción'
);

INSERT INTO modulos (nombre)
SELECT 'Cálculo de detracción'
WHERE @detraccion_nuevo = 1;

INSERT INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar)
SELECT roles.id, modulos.id, 1, 0, 0, 0
FROM roles
JOIN modulos ON modulos.nombre = 'Cálculo de detracción'
WHERE @detraccion_nuevo = 1
  AND roles.nombre IN ('Gerencial', 'Procesos')
  AND NOT EXISTS (
      SELECT 1 FROM permisos
      WHERE permisos.rol_id = roles.id AND permisos.modulo_id = modulos.id
  );

COMMIT;
