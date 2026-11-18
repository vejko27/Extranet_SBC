<?php
const SUELDO_IMPORTES = [
    'ingresos' => 'Ingresos', 'descuentos' => 'Descuentos', 'quincena' => 'Quincena',
    'prestamo' => 'Préstamo', 'neto' => 'Neto', 'aporte' => 'Aporte',
    'sueldo_basico' => 'Sueldo básico', 'sueldo_diario' => 'Sueldo diario',
    'sueldo_hora' => 'Sueldo por hora',
];
const SUELDO_TIPOS = ['Quincenal', 'Mensual'];
const SUELDO_CAMPOS = SUELDO_IMPORTES + ['fecha_pago' => 'Fecha de pago', 'tipo_sueldo' => 'Tipo de sueldo'];

function sueldoEscapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function sueldoErrorBD(string $detalle): void
{
    error_log('Extranet_SBC: error en Sueldo: ' . $detalle);
    http_response_code(500);
    exit('No se pudo acceder a los datos de sueldo. Verifique que las migraciones de Sueldo estén instaladas y contacte al administrador.');
}

function sueldoPeriodo($valor): string
{
    if (!is_string($valor) || !preg_match('/^(?:19\d{2}|[2-9]\d{3})-(?:0[1-9]|1[0-2])$/D', $valor)) {
        http_response_code(400);
        exit('El período no es válido.');
    }
    return $valor;
}

function sueldoTrabajadores(mysqli $conexion, string $periodo): array
{
    $fechaPeriodo = $periodo . '-01';
    $st = $conexion->prepare("SELECT p.id, p.apellido_paterno, p.apellido_materno, p.nombres, p.dni,
        p.carnet_extranjeria, p.pasaporte, p.puesto_trabajo, p.sede_labora, p.fecha_ingreso,
        s.version AS sueldo_version,
        s.ingresos, s.descuentos, s.quincena, s.prestamo, s.prestamo_estado, s.neto, s.aporte,
        s.sueldo_basico, s.sueldo_diario, s.sueldo_hora, s.fecha_pago, s.tipo_sueldo
        FROM personal p LEFT JOIN sueldo_mensual s ON s.personal_id = p.id AND s.periodo = ?
        WHERE p.estado = 'activo'
        ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres, p.id");
    if (!$st) {
        sueldoErrorBD($conexion->error);
    }
    $st->bind_param('s', $fechaPeriodo);
    if (!$st->execute() || !($resultado = $st->get_result())) {
        sueldoErrorBD($st->error);
    }
    $trabajadores = $resultado->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $trabajadores;
}

function sueldoNombre(array $trabajador): string
{
    return trim(implode(' ', array_filter([$trabajador['apellido_paterno'], $trabajador['apellido_materno'], $trabajador['nombres']])));
}

