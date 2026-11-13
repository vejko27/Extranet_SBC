<?php
// Campos adicionales de la ficha de personal (agrupados por sección del formulario).
// Tipos: text (VARCHAR), textarea, date, sino (Sí/No), select (lista cerrada), ruc (11 dígitos).
const OPCIONES_SINO = ['Sí', 'No'];

$CAMPOS_EXTRA = [
    'Datos generales adicionales' => [
        'lugar_nacimiento' => ['Lugar de nacimiento', 'text', 100],
        'nacionalidad' => ['Nacionalidad', 'text', 60],
        'grupo_sanguineo' => ['Grupo sanguíneo', 'select', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']],
        'fecha_vencimiento_doc' => ['Vencimiento del documento', 'date'],
        'domiciliado' => ['¿Es domiciliado?', 'sino'],
        'discapacidad' => ['¿Tiene discapacidad?', 'sino'],
        'fv_examen_ocupacional' => ['F.V. examen ocupacional', 'date'],
        'codigo_essalud' => ['Código ESSALUD', 'text', 20],
        'referencia_domicilio' => ['Referencia del domicilio', 'text', 150],
        'observacion' => ['Observación', 'textarea', 500],
    ],
    'Datos laborales' => [
        'area' => ['Área', 'text', 80],
        'actividad' => ['Actividad', 'text', 100],
        'horario' => ['Horario', 'text', 80],
        'jefe_inmediato' => ['Jefe inmediato', 'text', 120],
    ],
    'Otros datos y prestaciones de salud' => [
        'otros_tipo' => ['Tipo (otros datos)', 'text', 60],
        'convenio_doble_tributacion' => ['Convenio para evitar doble tributación', 'text', 60],
        'situacion_eps' => ['Situación EPS', 'text', 60],
        'codigo_eps' => ['Código EPS', 'text', 20],
    ],
    'Régimen pensionario y condiciones laborales' => [
        'tipo_comision' => ['T.C. (tipo de comisión)', 'select', ['Flujo o Por Remuneración', 'Mixta']],
        'fecha_inscripcion_pension' => ['F.I. (fecha de inscripción)', 'date'],
        'jubilado' => ['Jubilado', 'sino'],
        'jubilado_anticipado' => ['Jubilado anticipado', 'sino'],
        'regimen_alternativo' => ['Régimen alternativo o atípico', 'sino'],
        'jornada_maxima' => ['Jornada máxima', 'sino'],
        'horario_nocturno' => ['Horario nocturno', 'sino'],
        'regimen_laboral' => ['Régimen', 'select', ['General', 'Especial']],
        'situacion_especial' => ['Situación especial (tipo)', 'text', 60],
        'fiscalizacion_inmediata' => ['Fiscalización inmediata', 'sino'],
    ],
    'Datos complementarios' => [
        'sctr_salud' => ['SCTR salud', 'text', 40],
        'sctr_pension' => ['SCTR pensión', 'text', 40],
        'periodicidad' => ['Periodicidad', 'text', 40],
        'tipo_remuneracion' => ['Tipo de remuneración', 'text', 40],
        'tipo_pago' => ['Tipo de pago', 'text', 40],
        'tiene_essalud_vida' => ['¿Tiene ESSALUD+Vida?', 'sino'],
        'sindicalizado' => ['¿Es sindicalizado?', 'sino'],
        'seguro_vida_ley' => ['¿Seguro Vida Ley?', 'sino'],
        'trabaja_otras_empresas' => ['¿Trabaja en otras empresas?', 'sino'],
        'es_chofer' => ['¿Es chofer?', 'sino'],
        'rentas_5ta' => ['¿Rentas de 5ta exoneradas?', 'sino'],
        'otros_ingresos_5ta' => ['¿Otros ingresos de 5ta?', 'sino'],
        'ruc' => ['RUC', 'ruc'],
    ],
    'Contrato y período laboral' => [
        'tipo_contrato' => ['Tipo de contrato', 'select', ['01 - a Plazo Indeterminado', '02 - a Tiempo Parcial', '03 - por Inicio o Incremento de Actividad', '04 - por Necesidades del Mercado', '05 - por Reconversión Empresarial', '06 - Ocasional', '07 - de Suplencia', '08 - de Emergencia'], 100],
        'contrato_inicio' => ['Inicio del contrato', 'date'],
        'contrato_fin' => ['Fin del contrato', 'date'],
        'periodo_inicio' => ['Inicio del período laboral', 'date'],
        'fecha_cese' => ['Fecha de cese', 'date'],
        'motivo_cese' => ['Motivo', 'select', ['01 - Renuncia', '02 - Renuncia con incentivos', '03 - Despido o Destitución', '04 - Cese Colectivo', '05 - Jubilación', '06 - Invalidez Absoluta Permanente', '07 - Terminación de la Obra o Servicio', '08 - Mutuo Disenso', '09 - Fallecimiento', '10 - Suspensión de contrato'], 100],
    ],
];

// Lista plana nombre => definición
function camposExtraPlano(array $secciones): array
{
    $o = [];
    foreach ($secciones as $campos) {
        $o += $campos;
    }
    return $o;
}

// Crea en `personal` las columnas que falten (idempotente)
function garantizarColumnasExtra(mysqli $conexion, array $secciones): void
{
    $res = $conexion->query("SHOW COLUMNS FROM personal");
    if (!$res) {
        return;
    }
    $columnasExistentes = $res->fetch_all(MYSQLI_ASSOC);
    $existentes = array_column($columnasExistentes, 'Field');
    $tiposExistentes = [];
    foreach ($columnasExistentes as $columna) {
        $tiposExistentes[$columna['Field']] = $columna['Type'];
    }
    $alter = [];
    foreach (camposExtraPlano($secciones) as $nombre => $def) {
        if (in_array($nombre, $existentes, true)) {
            continue;
        }
        $tipo = $def[1];
        $sql = match ($tipo) {
            'date' => 'DATE NULL',
            'sino' => "VARCHAR(2) NOT NULL DEFAULT ''",
            'select' => 'VARCHAR(' . (int)($def[3] ?? 30) . ") NOT NULL DEFAULT ''",
            'ruc' => "VARCHAR(11) NOT NULL DEFAULT ''",
            default => 'VARCHAR(' . (int)$def[2] . ") NOT NULL DEFAULT ''",
        };
        $alter[] = "ADD COLUMN `$nombre` $sql";
    }
    foreach (['tipo_contrato', 'motivo_cese'] as $nombre) {
        if (!isset($tiposExistentes[$nombre]) || !preg_match('/^varchar\((\d+)\)$/i', $tiposExistentes[$nombre], $coincidencia)) {
            continue;
        }
        if ((int)$coincidencia[1] < 100) {
            $alter[] = "MODIFY COLUMN `$nombre` VARCHAR(100) NOT NULL DEFAULT ''";
        }
    }
    if ($alter) {
        $conexion->query('ALTER TABLE personal ' . implode(', ', $alter));
    }
}

function garantizarTablaContratos(mysqli $conexion): void
{
    $tablas = $conexion->query('SHOW TABLES');
    if (!$tablas) {
        error_log('Extranet_SBC: no se pudo verificar la tabla personal_contratos: ' . $conexion->error);
        http_response_code(500);
        exit('No se pudo verificar el registro de contratos. Contacte al administrador.');
    }
    while ($tabla = $tablas->fetch_row()) {
        if ($tabla[0] === 'personal_contratos') {
            return;
        }
    }

    if (!$conexion->query("CREATE TABLE IF NOT EXISTS personal_contratos (
        id INT NOT NULL AUTO_INCREMENT,
        personal_id INT NOT NULL,
        tipo_contrato VARCHAR(100) NOT NULL DEFAULT '',
        contrato_inicio DATE NULL,
        contrato_fin DATE NULL,
        periodo_inicio DATE NULL,
        fecha_cese DATE NULL,
        motivo_cese VARCHAR(100) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY idx_personal_contratos_personal_id (personal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        error_log('Extranet_SBC: no se pudo preparar la tabla personal_contratos: ' . $conexion->error);
        http_response_code(500);
        exit('No se pudo preparar el registro de contratos. Contacte al administrador.');
    }
}

function garantizarColumnasVigenciaDocumentos(mysqli $conexion): void
{
    $res = $conexion->query("SHOW COLUMNS FROM personal_documentos");
    if (!$res) {
        error_log('Extranet_SBC: no se pudieron verificar las columnas de vigencia de personal_documentos: ' . $conexion->error);
        http_response_code(500);
        exit('No se pudo preparar el seguimiento de documentos. Contacte al administrador.');
    }

    $existentes = array_column($res->fetch_all(MYSQLI_ASSOC), 'Field');
    $faltantes = [];
    foreach ([
        'fecha_inicio' => 'DATE NULL',
        'fecha_vencimiento' => 'DATE NULL',
        'sin_vencimiento' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ] as $nombre => $tipo) {
        if (!in_array($nombre, $existentes, true)) {
            $faltantes[] = "ADD COLUMN `$nombre` $tipo";
        }
    }
    if ($faltantes && !$conexion->query('ALTER TABLE personal_documentos ' . implode(', ', $faltantes))) {
        error_log('Extranet_SBC: no se pudieron agregar columnas de vigencia a personal_documentos: ' . $conexion->error);
        http_response_code(500);
        exit('No se pudo preparar el seguimiento de documentos. Contacte al administrador.');
    }
}
