<?php
// Ficha de datos del personal: formulario para crear/editar/ver (solo lectura) una ficha
// con datos del empleado, domicilio, contacto, banco, emergencia, cónyuge e hijos.
// Valida y guarda todo en una transacción.
// Variables de la página para el layout (menú activo y título)
$paginaActiva = 'rrhh';
$tituloPagina = 'Ficha de datos del personal';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Permisos: "ver" para entrar; "crear" para fichas nuevas y "editar" para existentes
exigirPermiso('Recursos Humanos', 'ver');
$puedeCrear = puede('Recursos Humanos', 'crear');
$puedeEditar = puede('Recursos Humanos', 'editar');

// Codificación, zona horaria y token CSRF de la sesión
$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/Lima');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
// Escapa texto para imprimirlo de forma segura en HTML
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// id = 0 es ficha nueva; $editable indica si el usuario puede modificar el formulario
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editable = $id ? $puedeEditar : $puedeCrear;
if (!$id && !$puedeCrear) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Listas de opciones válidas y límites de cantidad de hijos y contactos de emergencia
$SEXOS = ['Masculino', 'Femenino'];
$CIVIL = ['Soltero(a)', 'Casado(a)', 'Divorciado(a)', 'Viudo(a)'];
$HIJOS = ['Ninguno', 'Uno', 'Dos', 'Tres', 'Más de tres'];
$TIPOS_DH = ['Cónyuge', 'Concubino(a)'];
$BANCOS = ['BANCO BCP', 'BANCO BBVA', 'BANCO SCOTIABANK'];
$AFPS = ['PRIMA', 'HABITAT', 'PROFUTURO', 'INTEGRA'];
$EDUCACION = [
    'Sin Educación Formal', 'Educación Primaria Completa', 'Educación Primaria Incompleta',
    'Educación Secundaria Completa', 'Educación Secundaria Incompleta', 'Educación Técnica Completa',
    'Educación Técnica Incompleta', 'Educación Superior (Instituto Superior, etc) Completa',
    'Educación Superior (Instituto Superior, etc) Incompleta', 'Educación Universitaria Completa',
    'Educación Universitaria Incompleta', 'Grado de Bachiller', 'Titulado', 'Estudios de Maestría Completa',
    'Estudios de Maestría Incompleta', 'Grado de Maestro', 'Estudios de Doctorado Completo',
    'Estudios de Doctorado Incompleto', 'Grado de Doctor',
];
const MAX_HIJOS = 12;
const MAX_EMERGENCIA = 3;

// Longitud máxima de cada campo (datos de persona y datos propios del titular)
$CAMPOS_PERSONA = [
    'apellido_paterno' => 80, 'apellido_materno' => 80, 'nombres' => 120,
    'dni' => 8, 'carnet_extranjeria' => 20, 'pasaporte' => 20,
];
$CAMPOS_TITULAR = [
    'via' => 150, 'distrito' => 80, 'provincia' => 80, 'departamento' => 80,
    'telefono_fijo' => 20, 'celular' => 20, 'email' => 120, 'puesto_trabajo' => 100, 'sede_labora' => 150,
    'cuenta_bancaria' => 30, 'cci_bancaria' => 20, 'banco' => 60, 'banco_cts' => 60, 'cuenta_cts' => 30, 'cci_cts' => 20, 'tipo_afp' => 20, 'cuspp' => 12, 'profesion' => 255,
];

// Comprueba que la cadena sea una fecha real con formato Y-m-d
function fechaValida(string $f): bool
{
    $fd = DateTime::createFromFormat('Y-m-d', $f);
    return $fd && $fd->format('Y-m-d') === $f;
}

// Limpia y valida una persona (titular o derechohabiente). Acumula errores en $err.
function limpiarPersona(array $in, array $campos, array $opc, array &$err, string $etq, bool $esTitular): array
{
    $o = [];
    // Textos: recorta, pasa apellidos/nombres a mayúsculas y controla la longitud
    foreach ($campos as $c => $max) {
        $v = trim((string)($in[$c] ?? ''));
        if (in_array($c, ['apellido_paterno', 'apellido_materno', 'nombres'], true)) {
            $v = mb_strtoupper($v, 'UTF-8');
        }
        if (mb_strlen($v) > $max) {
            $err[] = "$etq: «" . str_replace('_', ' ', $c) . "» es demasiado largo.";
            $v = mb_substr($v, 0, $max);
        }
        $o[$c] = $v;
    }
    // Documentos de identidad: DNI de 8 dígitos; carnet y pasaporte alfanuméricos
    if ($o['dni'] !== '' && !preg_match('/^\d{8}$/', $o['dni'])) {
        $err[] = "$etq: el DNI debe tener 8 dígitos.";
    }
    foreach (['carnet_extranjeria', 'pasaporte'] as $c) {
        if ($o[$c] !== '' && !preg_match('/^[A-Za-z0-9]+$/', $o[$c])) {
            $err[] = "$etq: " . str_replace('_', ' ', $c) . ' solo admite letras y números.';
        }
    }
    // Fecha de nacimiento (no futura) y sexo dentro de las opciones permitidas
    $f = trim((string)($in['fecha_nacimiento'] ?? ''));
    if ($f !== '' && (!fechaValida($f) || $f > date('Y-m-d'))) {
        $err[] = "$etq: fecha de nacimiento no válida.";
    }
    $o['fecha_nacimiento'] = $f;
    $sx = trim((string)($in['sexo'] ?? ''));
    if ($sx !== '' && !in_array($sx, $opc['sexos'], true)) {
        $err[] = "$etq: sexo no válido.";
        $sx = '';
    }
    $o['sexo'] = $sx;

    // Campos exclusivos del titular: listas cerradas, extras, correo, teléfonos e ingreso
    if ($esTitular) {
        foreach (['estado_civil' => $opc['civil'], 'num_hijos' => $opc['hijos'], 'nivel_educativo' => $opc['educacion']] as $c => $lista) {
            $v = trim((string)($in[$c] ?? ''));
            if ($v !== '' && !in_array($v, $lista, true)) {
                $err[] = "$etq: valor no válido en " . str_replace('_', ' ', $c) . '.';
                $v = '';
            }
            $o[$c] = $v;
        }
        foreach ($opc['extra'] as $c => $max) {
            $v = trim((string)($in[$c] ?? ''));
            if (mb_strlen($v) > $max) {
                $err[] = "$etq: «" . str_replace('_', ' ', $c) . "» es demasiado largo.";
                $v = mb_substr($v, 0, $max);
            }
            $o[$c] = $v;
        }
        foreach (['banco', 'banco_cts'] as $c) {
            if ($o[$c] !== '' && !in_array($o[$c], $opc['bancos'], true)) {
                $err[] = "$etq: selecciona un banco de la lista en " . str_replace('_', ' ', $c) . '.';
                $o[$c] = '';
            }
        }
        if ($o['tipo_afp'] !== '' && !in_array($o['tipo_afp'], $opc['afps'], true)) {
            $err[] = "$etq: selecciona un tipo de AFP de la lista.";
            $o['tipo_afp'] = '';
        }
        $o['cuspp'] = strtoupper($o['cuspp']);
        if ($o['cuspp'] !== '' && !preg_match('/^[A-Z0-9]{12}$/', $o['cuspp'])) {
            $err[] = "$etq: el CUSPP debe tener 12 caracteres alfanuméricos.";
        }
        foreach (['cci_bancaria', 'cci_cts'] as $c) {
            if ($o[$c] !== '' && !preg_match('/^\d{20}$/', $o[$c])) {
                $err[] = "$etq: el " . str_replace('_', ' ', strtoupper($c)) . ' debe tener 20 dígitos.';
            }
        }
        if ($o['email'] !== '' && !filter_var($o['email'], FILTER_VALIDATE_EMAIL)) {
            $err[] = "$etq: correo electrónico no válido.";
        }
        foreach (['celular', 'telefono_fijo'] as $c) {
            if ($o[$c] !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $o[$c])) {
                $err[] = "$etq: " . str_replace('_', ' ', $c) . ' no válido.';
            }
        }
        $fi = trim((string)($in['fecha_ingreso'] ?? ''));
        if ($fi !== '' && !fechaValida($fi)) {
            $err[] = "$etq: fecha de ingreso no válida.";
        }
        $o['fecha_ingreso'] = $fi;
    }
    return $o;
}

// Indica si todos los campos (salvo los ignorados) están vacíos
function filaVacia(array $p, array $ignorar = []): bool
{
    foreach ($p as $k => $v) {
        if (!in_array($k, $ignorar, true) && $v !== '') {
            return false;
        }
    }
    return true;
}

// Estado inicial del formulario: opciones, errores y valores vacíos por defecto
$opc = ['sexos' => $SEXOS, 'civil' => $CIVIL, 'hijos' => $HIJOS, 'educacion' => $EDUCACION, 'bancos' => $BANCOS, 'afps' => $AFPS, 'extra' => $CAMPOS_TITULAR];
$errores = [];

$fd = array_fill_keys(array_merge(
    array_keys($CAMPOS_PERSONA), array_keys($CAMPOS_TITULAR),
    ['fecha_nacimiento', 'sexo', 'estado_civil', 'num_hijos', 'nivel_educativo', 'fecha_ingreso']
), '');
$conyuge = ['tipo' => ''] + array_fill_keys(array_merge(array_keys($CAMPOS_PERSONA), ['fecha_nacimiento', 'sexo']), '');
$hijos = [];
$emergencia = [];

// Procesamiento POST: valida CSRF y permisos, y luego cada sección del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errores[] = 'Sesión inválida, recarga la página.';
    } elseif (!$editable) {
        $errores[] = 'No tienes permiso para realizar esta acción.';
    }

    // Datos del empleado (titular)
    $fd = limpiarPersona($_POST, $CAMPOS_PERSONA, $opc, $errores, 'Empleado', true);

    // Derechohabiente (cónyuge o concubino): si hay datos, exige tipo, apellido paterno y nombres
    $in = $_POST['conyuge'] ?? [];
    $conyuge = limpiarPersona(is_array($in) ? $in : [], $CAMPOS_PERSONA, $opc, $errores, 'Derechohabiente', false);
    $conyuge['tipo'] = trim((string)($in['tipo'] ?? ''));
    if ($conyuge['tipo'] !== '' && !in_array($conyuge['tipo'], $TIPOS_DH, true)) {
        $errores[] = 'Derechohabiente: tipo no válido.';
        $conyuge['tipo'] = '';
    }
    if (!filaVacia($conyuge, ['tipo']) && $conyuge['tipo'] === '') {
        $errores[] = 'Derechohabiente: indica si es cónyuge o concubino(a).';
    }
    if (!filaVacia($conyuge, ['tipo']) && ($conyuge['apellido_paterno'] === '' || $conyuge['nombres'] === '')) {
        $errores[] = 'Derechohabiente: apellido paterno y nombres son obligatorios.';
    }

    // Hijos: se limitan a MAX_HIJOS, se ignoran filas vacías y se exigen datos mínimos
    $n = 0;
    foreach ((array)($_POST['hijos'] ?? []) as $h) {
        if (!is_array($h) || ++$n > MAX_HIJOS) {
            continue;
        }
        $p = limpiarPersona($h, $CAMPOS_PERSONA, $opc, $errores, "Hijo(a) $n", false);
        if (filaVacia($p)) {
            continue;
        }
        if ($p['apellido_paterno'] === '' || $p['nombres'] === '') {
            $errores[] = "Hijo(a) $n: apellido paterno y nombres son obligatorios.";
        }
        $hijos[] = $p;
    }

    // Contactos de emergencia: límite MAX_EMERGENCIA, longitudes, nombre obligatorio y teléfonos válidos
    $n = 0;
    foreach ((array)($_POST['emergencia'] ?? []) as $em) {
        if (!is_array($em) || ++$n > MAX_EMERGENCIA) {
            continue;
        }
        $r = [];
        foreach (['nombres' => 160, 'celular' => 20, 'fijo' => 20, 'parentesco' => 40] as $c => $max) {
            $r[$c] = trim((string)($em[$c] ?? ''));
            if (mb_strlen($r[$c]) > $max) {
                $errores[] = "Contacto de emergencia $n: «$c» es demasiado largo.";
                $r[$c] = mb_substr($r[$c], 0, $max);
            }
        }
        if (filaVacia($r)) {
            continue;
        }
        if ($r['nombres'] === '') {
            $errores[] = "Contacto de emergencia $n: indica nombres y apellidos.";
        }
        foreach (['celular', 'fijo'] as $c) {
            if ($r[$c] !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $r[$c])) {
                $errores[] = "Contacto de emergencia $n: $c no válido.";
            }
        }
        $emergencia[] = $r;
    }

    // Validaciones generales del empleado: nombre, documento obligatorio y DNI no duplicado
    if ($fd['apellido_paterno'] === '' || $fd['nombres'] === '') {
        $errores[] = 'Empleado: apellido paterno y nombres son obligatorios.';
    }
    if ($fd['dni'] === '' && $fd['carnet_extranjeria'] === '' && $fd['pasaporte'] === '') {
        $errores[] = 'Empleado: indica DNI, carnet de extranjería o pasaporte.';
    }
    if (!$errores && $fd['dni'] !== '') {
        $s = $conexion->prepare("SELECT id FROM personal WHERE dni = ? AND id <> ? LIMIT 1");
        $s->bind_param('si', $fd['dni'], $id);
        $s->execute();
        if ($s->get_result()->fetch_row()) {
            $errores[] = 'Ya existe una ficha con ese DNI.';
        }
    }

    // Sin errores: guarda en transacción (fechas vacías pasan a NULL)
    if (!$errores) {
        $fechas =  ['fecha_nacimiento', 'fecha_ingreso'];
        $vals = [];
        foreach ($fd as $k => $v) {
            $vals[$k] = ($v === '' && in_array($k, $fechas, true)) ? null : $v;
        }
        $conexion->begin_transaction();
        try {
            $cols = array_keys($vals);
            // Edición: actualiza la ficha y borra derechohabientes/emergencia para reinsertarlos
            if ($id) {
                $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
                $s = $conexion->prepare("UPDATE personal SET $set WHERE id=?");
                $params = array_values($vals);
                $params[] = $id;
                $s->bind_param(str_repeat('s', count($cols)) . 'i', ...$params);
                $s->execute();
                foreach (['personal_derechohabientes', 'personal_emergencia'] as $tabla) {
                    $x = $conexion->prepare("DELETE FROM $tabla WHERE personal_id=?");
                    $x->bind_param('i', $id);
                    $x->execute();
                }
            } else {
                // Alta: inserta la ficha y obtiene el nuevo id
                $lista =  implode(',', array_map(fn($c) => "`$c`", $cols));
                $marc = implode(',', array_fill(0, count($cols), '?'));
                $s = $conexion->prepare("INSERT INTO personal ($lista) VALUES ($marc)");
                $params = array_values($vals);
                $s->bind_param(str_repeat('s', count($cols)), ...$params);
                $s->execute();
                $id = $conexion->insert_id;
            }

            // Inserta cónyuge/concubino e hijos en derechohabientes
            $ins = $conexion->prepare("INSERT INTO personal_derechohabientes (personal_id,tipo,apellido_paterno,apellido_materno,nombres,dni,carnet_extranjeria,pasaporte,fecha_nacimiento,sexo) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $tipos = 'isssssssss';
            $ins2 = function (string $tipo, array $p) use ($ins, $id, $tipos) {
                $f = $p['fecha_nacimiento'] === '' ? null : $p['fecha_nacimiento'];
                $ins->bind_param($tipos, $id, $tipo, $p['apellido_paterno'], $p['apellido_materno'], $p['nombres'], $p['dni'], $p['carnet_extranjeria'], $p['pasaporte'], $f, $p['sexo']);
                $ins->execute();
            };
            if (!filaVacia($conyuge, ['tipo'])) {
                $ins2($conyuge['tipo'], $conyuge);
            }
            foreach ($hijos as $h) {
                $ins2('Hijo(a)', $h);
            }

            // Inserta los contactos de emergencia y confirma la transacción
            $em = $conexion->prepare("INSERT INTO personal_emergencia (personal_id,nombres,celular,fijo,parentesco) VALUES (?,?,?,?,?)");
            foreach ($emergencia as $r) {
                $em->bind_param('issss', $id, $r['nombres'], $r['celular'], $r['fijo'], $r['parentesco']);
                $em->execute();
            }
            $conexion->commit();
            $_SESSION['flash'] = ['ok' => 'Ficha guardada correctamente.', 'err' => ''];
            header('Location: rrhh.php');
            exit;
        } catch (Throwable $ex) {
            // Ante cualquier fallo se revierte todo
            $conexion->rollback();
            $errores[] = 'No se pudo guardar la ficha; no se hicieron cambios.';
        }
    }
} elseif ($id) {
    // GET con id: carga la ficha, sus derechohabientes y contactos de emergencia
    $s =  $conexion->prepare("SELECT * FROM personal WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $fila = $s->get_result()->fetch_assoc();
    if (!$fila) {
        http_response_code(404);
        exit('Ficha no encontrada');
    }
    foreach ($fd as $k => $_) {
        $fd[$k] = (string)($fila[$k] ?? '');
    }
    $s = $conexion->prepare("SELECT * FROM personal_derechohabientes WHERE personal_id = ? ORDER BY id");
    $s->bind_param('i', $id);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        if ($r['tipo'] === 'Hijo(a)') {
            $hijos[] = $r;
        } else {
            $conyuge = $r;
        }
    }
    $s = $conexion->prepare("SELECT * FROM personal_emergencia WHERE personal_id = ? ORDER BY id");
    $s->bind_param('i', $id);
    $s->execute();
    $emergencia = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Genera las opciones <option> de un select, marcando la seleccionada
function opciones(array $lista, string $sel, string $vacio = '— Seleccionar —'): string
{
    $h = '<option value="">' . e($vacio) . '</option>';
    foreach ($lista as $o) {
        $h .= '<option' . ($o === $sel ? ' selected' : '') . '>' . e($o) . '</option>';
    }
    return $h;
}
// Genera un campo de formulario (etiqueta + input) con valores escapados
function campo(string $etq, string $nombre, string $valor, string $extra = '', string $tipo = 'text'): string
{
    return '<div class="form-grupo"><label>' . e($etq) . '</label><input type="' . $tipo . '" name="' . e($nombre) . '" value="' . e($valor) . '" ' . $extra . '></div>';
}
// Genera el bloque de campos de una persona; $pre es el prefijo del name (p. ej. hijos[0])
function bloquePersona(string $pre, array $p, array $SEXOS): string
{
    $n = fn($c) => $pre === '' ? $c : "{$pre}[$c]";
    $h = '<div class="ficha-grid">';
    $h .= campo('Apellido paterno', $n('apellido_paterno'), $p['apellido_paterno'] ?? '', 'maxlength="80"');
    $h .= campo('Apellido materno', $n('apellido_materno'), $p['apellido_materno'] ?? '', 'maxlength="80"');
    $h .= campo('Nombres', $n('nombres'), $p['nombres'] ?? '', 'maxlength="120"');
    $h .= campo('DNI N°', $n('dni'), $p['dni'] ?? '', 'maxlength="8" inputmode="numeric" pattern="\d{8}" title="8 dígitos"');
    $h .= campo('Carnet de extranjería N°', $n('carnet_extranjeria'), $p['carnet_extranjeria'] ?? '', 'maxlength="20"');
    $h .= campo('Pasaporte N°', $n('pasaporte'), $p['pasaporte'] ?? '', 'maxlength="20"');
    $h .= campo('Fecha de nacimiento', $n('fecha_nacimiento'), $p['fecha_nacimiento'] ?? '', 'max="' . date('Y-m-d') . '"', 'date');
    $h .= '<div class="form-grupo"><label>Sexo</label><select name="' . e($n('sexo')) . '">' . opciones($SEXOS, $p['sexo'] ?? '') . '</select></div>';
    return $h . '</div>';
}
// Fila repetible de un hijo(a), con botón Quitar si es editable
function filaHijo($i, array $p, array $SEXOS, bool $editable): string
{
    $h = '<div class="ficha-item"><div class="ficha-item-cab"><strong>Hijo(a)</strong>';
    if ($editable) {
        $h .= '<button type="button" class="btn-sec btn-quitar">Quitar</button>';
    }
    return $h . '</div>' . bloquePersona("hijos[$i]", $p, $SEXOS) . '</div>';
}
// Fila repetible de un contacto de emergencia, con botón Quitar si es editable
function filaEmergencia($i, array $p, bool $editable): string
{
    $h = '<div class="ficha-item"><div class="ficha-item-cab"><strong>Contacto</strong>';
    if ($editable) {
        $h .= '<button type="button" class="btn-sec btn-quitar">Quitar</button>';
    }
    $h .= '</div><div class="ficha-grid">';
    $h .= campo('Nombres y apellidos', "emergencia[$i][nombres]", $p['nombres'] ?? '', 'maxlength="160"');
    $h .= campo('Celular', "emergencia[$i][celular]", $p['celular'] ?? '', 'maxlength="20" inputmode="tel"');
    $h .= campo('Fijo', "emergencia[$i][fijo]", $p['fijo'] ?? '', 'maxlength="20" inputmode="tel"');
    $h .= campo('Parentesco', "emergencia[$i][parentesco]", $p['parentesco'] ?? '', 'maxlength="40"');
    return $h . '</div></div>';
}

// Cabecera común del layout; desde aquí comienza el HTML
require __DIR__ . '/layout_inicio.php';
$nombreTitular = trim($fd['apellido_paterno'] . ' ' . $fd['apellido_materno'] . ' ' . $fd['nombres']);
?>
<!-- Encabezado: título, imprimir y volver -->

<div class="prov-cabecera">
    <div>
        <h1>Ficha de datos del personal</h1>
        <p class="subtitulo"><?= $id ? e($nombreTitular) : 'Nueva ficha' ?><?= $editable ? '' : ' · Solo lectura' ?></p>
    </div>
    <div class="no-imprimir">
        <button type="button" class="btn-sec" onclick="window.print()">Imprimir</button>
        <a class="btn btn-sec" href="rrhh.php">Volver</a>
    </div>
</div>

<?php /* Lista de errores de validación */ ?>
<?php if ($errores): ?>
    <div class="alerta err"><ul class="lista-errores"><?php foreach ($errores as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- Formulario principal; el fieldset se deshabilita en modo solo lectura -->
<form method="POST" class="ficha" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <fieldset <?= $editable ? '' : 'disabled' ?>>

        <!-- Sección: datos del empleado -->
        <div class="card">
            <h2>Información del empleado</h2>
            <?= bloquePersona('', $fd, $SEXOS) ?>
            <div class="ficha-grid">
                <?= campo('Puesto de trabajo al que será asignado', 'puesto_trabajo', $fd['puesto_trabajo'], 'maxlength="100"') ?>
            </div>
        </div>

        <!-- Sección: estado civil y número de hijos -->
        <div class="card">
            <h2>Estado civil y familia</h2>
            <div class="ficha-grid">
                <div class="form-grupo"><label>Estado civil</label><select name="estado_civil"><?= opciones($CIVIL, $fd['estado_civil']) ?></select></div>
                <div class="form-grupo"><label>N° de hijos</label><select name="num_hijos"><?= opciones($HIJOS, $fd['num_hijos']) ?></select></div>
            </div>
        </div>

        <!-- Sección: domicilio -->
        <div class="card">
            <h2>Domicilio</h2>
            <div class="ficha-grid">
                <?= campo('Av./Jr./Calle/Psje.', 'via', $fd['via'], 'maxlength="150"') ?>
                <?= campo('Distrito', 'distrito', $fd['distrito'], 'maxlength="80"') ?>
                <?= campo('Provincia', 'provincia', $fd['provincia'], 'maxlength="80"') ?>
                <?= campo('Departamento', 'departamento', $fd['departamento'], 'maxlength="80"') ?>
            </div>
        </div>

        <!-- Sección: teléfonos y correo -->
        <div class="card">
            <h2>Teléfonos y correo</h2>
            <div class="ficha-grid">
                <?= campo('Teléfono fijo', 'telefono_fijo', $fd['telefono_fijo'], 'maxlength="20" inputmode="tel"') ?>
                <?= campo('Celular', 'celular', $fd['celular'], 'maxlength="20" inputmode="tel"') ?>
                <?= campo('E-mail (correo electrónico)', 'email', $fd['email'], 'maxlength="120"', 'email') ?>
            </div>
        </div>

        <!-- Sección: sede, ingreso y datos bancarios -->
        <div class="card">
            <h2>Sede y datos bancarios</h2>
            <div class="ficha-grid">
                <?= campo('Sede donde labora', 'sede_labora', $fd['sede_labora'], 'maxlength="150"') ?>
                <?= campo('Fecha de ingreso a planilla', 'fecha_ingreso', $fd['fecha_ingreso'], '', 'date') ?>
                <div class="form-grupo"><label>Nombre de banco</label><select name="banco"><?= opciones($BANCOS, $fd['banco']) ?></select></div>
                <?= campo('Número de cuenta bancaria', 'cuenta_bancaria', $fd['cuenta_bancaria'], 'maxlength="30"') ?>
                <?= campo('CCI de cuenta bancaria', 'cci_bancaria', $fd['cci_bancaria'], 'maxlength="20" inputmode="numeric" pattern="\d{20}" title="20 dígitos"') ?>
                <div class="form-grupo"><label>Nombre de banco de CTS</label><select name="banco_cts"><?= opciones($BANCOS, $fd['banco_cts']) ?></select></div>
                <?= campo('Número de cuenta CTS', 'cuenta_cts', $fd['cuenta_cts'], 'maxlength="30"') ?>
                <?= campo('CCI de cuenta CTS', 'cci_cts', $fd['cci_cts'], 'maxlength="20" inputmode="numeric" pattern="\d{20}" title="20 dígitos"') ?>
            </div>
        </div>

        <!-- Sección: sistema de pensiones -->
        <div class="card">
            <h2>Sistema de pensiones</h2>
            <div class="ficha-grid">
                <div class="form-grupo"><label>Tipo de AFP</label><select name="tipo_afp"><?= opciones($AFPS, $fd['tipo_afp']) ?></select></div>
                <?= campo('CUSPP', 'cuspp', $fd['cuspp'], 'maxlength="12" pattern="[A-Za-z0-9]{12}" title="12 caracteres alfanuméricos" style="text-transform:uppercase"') ?>
            </div>
        </div>

        <!-- Sección: nivel educativo y profesión -->
        <div class="card">
            <h2>Nivel educativo y profesión</h2>
            <div class="ficha-grid">
                <div class="form-grupo"><label>Nivel educativo</label><select name="nivel_educativo"><?= opciones($EDUCACION, $fd['nivel_educativo']) ?></select></div>
                <?= campo('Profesión (técnica, superior y/o universitaria)', 'profesion', $fd['profesion'], 'maxlength="255"') ?>
            </div>
        </div>

        <!-- Sección: contactos de emergencia (filas dinámicas) -->
        <div class="card">
            <h2>En caso de emergencia llamar a</h2>
            <div id="lista-emergencia">
                <?php foreach ($emergencia as $i => $r) { echo filaEmergencia($i, $r, $editable); } ?>
            </div>
            <?php if ($editable): ?><button type="button" class="btn-sec" id="add-emergencia">+ Agregar contacto</button><?php endif; ?>
        </div>

        <!-- Sección: derechohabiente cónyuge o concubino(a) -->
        <div class="card">
            <h2>Información de derechohabientes · Cónyuge o concubino(a)</h2>
            <div class="form-grupo" style="max-width:260px"><label>Tipo</label><select name="conyuge[tipo]"><?= opciones($TIPOS_DH, $conyuge['tipo'], '— Ninguno —') ?></select></div>
            <?= bloquePersona('conyuge', $conyuge, $SEXOS) ?>
        </div>

        <!-- Sección: hijos (filas dinámicas) -->
        <div class="card">
            <h2>Información de los hijos</h2>
            <div id="lista-hijos">
                <?php foreach ($hijos as $i => $h) { echo filaHijo($i, $h, $SEXOS, $editable); } ?>
            </div>
            <?php if ($editable): ?><button type="button" class="btn-sec" id="add-hijo">+ Agregar hijo(a)</button><?php endif; ?>
        </div>

        <?php /* Botón guardar solo si es editable */ ?>
        <?php if ($editable): ?>
        <div class="no-imprimir"><button type="submit">Guardar ficha</button></div>
        <?php endif; ?>
    </fieldset>
</form>

<?php /* Plantillas de filas nuevas y script para agregar/quitar hijos y contactos (solo editable) */ ?>
<?php if ($editable): ?>
<template id="tpl-hijo"><?= filaHijo('__I__', [], $SEXOS, true) ?></template>
<template id="tpl-emergencia"><?= filaEmergencia('__I__', [], true) ?></template>
<script>
    // Configura una lista dinámica: agrega filas desde la plantilla, las quita y respeta el máximo
    function configurar(listaId, tplId, btnId, max) {
        const lista = document.getElementById(listaId);
        const tpl = document.getElementById(tplId);
        const btn = document.getElementById(btnId);
        let n = lista.children.length + 100; // índice alto para no chocar con las filas existentes
        const refrescar = () => { btn.hidden = lista.children.length >= max; };
        btn.addEventListener("click", () => {
            if (lista.children.length >= max) return;
            lista.insertAdjacentHTML("beforeend", tpl.innerHTML.replaceAll("__I__", n++));
            refrescar();
        });
        lista.addEventListener("click", (ev) => {
            const q = ev.target.closest(".btn-quitar");
            if (q) { q.closest(".ficha-item").remove(); refrescar(); }
        });
        refrescar();
    }
    // Inicializa las listas de hijos y de contactos con sus límites
    configurar("lista-hijos", "tpl-hijo", "add-hijo", <?= MAX_HIJOS ?>);
    configurar("lista-emergencia", "tpl-emergencia", "add-emergencia", <?= MAX_EMERGENCIA ?>);
</script>
<?php endif; ?>

<?php /* Pie común del layout */ ?>
<?php require __DIR__ . '/layout_fin.php'; ?>
