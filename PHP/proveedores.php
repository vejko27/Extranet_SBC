<?php
// Módulo de proveedores homologados: listado por año, alta/edición/baja, cambio rápido de estados,
// importación desde Excel (solo administrador) y búsqueda en vivo. Procesa los POST y muestra la página.
// Variables de la página (usadas por el layout) y dependencias de sesión/BD
$paginaActiva = 'proveedores';
$tituloPagina = 'Proveedores';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Permisos: sin 'ver' se bloquea el acceso; el resto habilita cada acción
exigirPermiso('Proveedores', 'ver');
$puedeCrear = puede('Proveedores', 'crear');
$puedeEditar = puede('Proveedores', 'editar');
$puedeEliminar = puede('Proveedores', 'eliminar');
$esAdmin = (int)($_SESSION['rol_id'] ?? 0) === 1;

// Charset de la conexión y token CSRF de la sesión
$conexion->set_charset('utf8mb4');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
// Escapa texto para imprimirlo de forma segura en HTML
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Listas de valores permitidos (también sirven para validar los POST)
$MESES = ['', 'ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
$TRI = ['SI','NO','NO APLICA'];
$STATUS = ['PENDIENTE','HOMOLOGADO'];
$SINO = ['', 'SI','NO'];
$BANCOS = [
    'Banco de Crédito del Perú', 'BBVA', 'Interbank', 'Scotiabank',
    'Pichincha', 'Banbif', 'Banco GNB', 'Mibanco',
];
$BANCO_DETRACCIONES = 'Banco de la Nación';

// Procesamiento POST: valida CSRF y ejecuta la acción; luego redirige (patrón PRG con mensaje flash)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = $err = '';
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif ($accion === 'guardar') {
        // Alta (id = 0) o edición: exige el permiso correspondiente
        $nuevo = $id === 0;
        if (($nuevo && !$puedeCrear) || (!$nuevo && !$puedeEditar)) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            // Lectura y limpieza de los campos del formulario
            $razon = trim($_POST['razon_social'] ?? '');
            $ruc = trim($_POST['ruc'] ?? '');
            $tipo = mb_strtoupper(trim($_POST['tipo'] ?? ''));
            $cert = mb_strtoupper(trim($_POST['certificado'] ?? ''));
            $banco = trim($_POST['banco'] ?? '');
            $numeroCuenta = trim($_POST['numero_cuenta'] ?? '');
            $cci = trim($_POST['cci'] ?? '');
            $banco2 = trim($_POST['banco2'] ?? '');
            $numeroCuenta2 = trim($_POST['numero_cuenta2'] ?? '');
            $cci2 = trim($_POST['cci2'] ?? '');
            $banco3 = trim($_POST['banco3'] ?? '');
            $numeroCuenta3 = trim($_POST['numero_cuenta3'] ?? '');
            $cci3 = trim($_POST['cci3'] ?? '');
            $bancoDetracciones = trim($_POST['banco_detracciones'] ?? '');
            $cuentaDetracciones = trim($_POST['cuenta_detracciones'] ?? '');
            $mes = $_POST['mes'] ?? '';
            $ac = $_POST['acuerdo_seguridad'] ?? '';
            $dj = $_POST['ddjj'] ?? '';
            $fi = $_POST['filtros_aplicados'] ?? '';
            $stt = $_POST['status'] ?? '';
            $reg = $_POST['registrado_sistema'] ?? '';
            // Las altas exigen los datos identificadores; en ediciones se permiten vacíos por completar.
            if ($razon === '' || ($ruc === '' ? $nuevo : !preg_match('/^\d{11}$/', $ruc)) || $tipo === '' || mb_strlen($razon) > 200
                || mb_strlen($tipo) > 40 || mb_strlen($cert) > 60
                || ($banco !== '' && !in_array($banco, $BANCOS, true))
                || mb_strlen($numeroCuenta) > 30
                || ($cci !== '' && !preg_match('/^\d{20}$/', $cci))
                || ($banco2 !== '' && !in_array($banco2, $BANCOS, true)) || mb_strlen($numeroCuenta2) > 30 || ($cci2 !== '' && !preg_match('/^\d{20}$/', $cci2))
                || ($banco3 !== '' && !in_array($banco3, $BANCOS, true)) || mb_strlen($numeroCuenta3) > 30 || ($cci3 !== '' && !preg_match('/^\d{20}$/', $cci3))
                || ($bancoDetracciones !== '' && $bancoDetracciones !== $BANCO_DETRACCIONES)
                || mb_strlen($cuentaDetracciones) > 30
                || !in_array($mes, $MESES, true) || ($nuevo && $mes === '')
                || !in_array($ac, $TRI, true) || !in_array($dj, $TRI, true)
                || !in_array($fi, $TRI, true) || !in_array($stt, $STATUS, true)
                || !in_array($reg, $SINO, true) || ($nuevo && $reg === '')) {
                $err = 'Revisa los datos: razón social y tipo son obligatorios; el RUC debe tener 11 dígitos cuando se indica, el banco debe estar en la lista y el CCI debe tener 20 dígitos.';
            } elseif ($nuevo) {
                // INSERT de un proveedor nuevo (consulta preparada)
                $s = $conexion->prepare("INSERT INTO proveedores (razon_social,ruc,tipo,certificado,banco,numero_cuenta,cci,banco2,numero_cuenta2,cci2,banco3,numero_cuenta3,cci3,banco_detracciones,cuenta_detracciones,mes,acuerdo_seguridad,ddjj,filtros_aplicados,status,registrado_sistema) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $s->bind_param('sssssssssssssssssssss', $razon, $ruc, $tipo, $cert, $banco, $numeroCuenta, $cci, $banco2, $numeroCuenta2, $cci2, $banco3, $numeroCuenta3, $cci3, $bancoDetracciones, $cuentaDetracciones, $mes, $ac, $dj, $fi, $stt, $reg);
                $s->execute();
                $ok = 'Proveedor agregado correctamente.';
            } else {
                // UPDATE completo del proveedor existente
                $s = $conexion->prepare("UPDATE proveedores SET razon_social=?,ruc=?,tipo=?,certificado=?,banco=?,numero_cuenta=?,cci=?,banco2=?,numero_cuenta2=?,cci2=?,banco3=?,numero_cuenta3=?,cci3=?,banco_detracciones=?,cuenta_detracciones=?,mes=?,acuerdo_seguridad=?,ddjj=?,filtros_aplicados=?,status=?,registrado_sistema=? WHERE id=?");
                $s->bind_param('sssssssssssssssssssssi', $razon, $ruc, $tipo, $cert, $banco, $numeroCuenta, $cci, $banco2, $numeroCuenta2, $cci2, $banco3, $numeroCuenta3, $cci3, $bancoDetracciones, $cuentaDetracciones, $mes, $ac, $dj, $fi, $stt, $reg, $id);
                $s->execute();
                $ok = 'Proveedor actualizado correctamente.';
            }
        }
    } elseif ($accion === 'acuerdo') {
        // Cambio rápido de un solo campo; la lista blanca evita columnas/valores arbitrarios
        $campo = $_POST['campo'] ?? '';
        $permitidos = ['acuerdo_seguridad' => $TRI, 'ddjj' => $TRI, 'filtros_aplicados' => $TRI, 'registrado_sistema' => $SINO, 'status' => $STATUS];
        $valor = $_POST['valor'] ?? '';
        if (!$puedeEditar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } elseif ($id <= 0 || !isset($permitidos[$campo]) || !in_array($valor, $permitidos[$campo], true)) {
            $err = 'Valor no válido.';
        } else {
            $s = $conexion->prepare("UPDATE proveedores SET `$campo`=? WHERE id=?");
            $s->bind_param('si', $valor, $id);
            $s->execute();
            $ok = 'Dato actualizado.';
        }
    } elseif ($accion === 'importar') {
        // Importación de Excel: solo admin, año válido y archivo .xlsx de hasta 10 MB
        $anioImp = (int)($_POST['anio'] ?? 0);
        $f = $_FILES['excel'] ?? null;
        if (!$esAdmin) {
            $err = 'Solo el administrador puede importar un Excel.';
        } elseif ($anioImp < 2000 || $anioImp > 2100) {
            $err = 'Año no válido.';
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10000000
            || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $err = 'Selecciona un archivo Excel (.xlsx) de hasta 10 MB.';
        } else {
            try {
                // Lee y valida todo el Excel antes de tocar la base de datos
                require_once __DIR__ . '/excel_proveedores.php';
                $res = proveedores_desde_excel(file_get_contents($f['tmp_name']));
                if ($res['errores']) {
                    $err = 'No se importó nada. ' . implode(' · ', array_slice($res['errores'], 0, 5))
                        . (count($res['errores']) > 5 ? ' · (y ' . (count($res['errores']) - 5) . ' más)' : '');
                } else {
                    // Transacción: borra los proveedores del año y los reemplaza por los del Excel
                    $creado = $anioImp . '-01-01 00:00:00';
                    $conexion->begin_transaction();
                    try {
                        $d = $conexion->prepare("DELETE FROM proveedores WHERE YEAR(creado) = ?");
                        $d->bind_param('i', $anioImp);
                        $d->execute();
                        $s = $conexion->prepare("INSERT INTO proveedores (razon_social,ruc,tipo,certificado,mes,acuerdo_seguridad,ddjj,filtros_aplicados,status,registrado_sistema,creado) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                        foreach ($res['filas'] as $p) {
                            $s->bind_param('sssssssssss', $p['razon_social'], $p['ruc'], $p['tipo'], $p['certificado'], $p['mes'],
                                $p['acuerdo_seguridad'], $p['ddjj'], $p['filtros_aplicados'], $p['status'], $p['registrado_sistema'], $creado);
                            $s->execute();
                        }
                        $conexion->commit();
                        $_SESSION['anio_prov'] = $anioImp;
                        $ok = 'Excel importado: ' . count($res['filas']) . ' proveedores en ' . $anioImp . '.';
                    } catch (Throwable $ex) {
                        $conexion->rollback();
                        $err = 'No se pudo guardar la información; no se hicieron cambios.';
                    }
                }
            } catch (Throwable $ex) {
                $err = $ex->getMessage();
            }
        }
    } elseif ($accion === 'eliminar') {
        // Baja de un proveedor por id (requiere permiso 'eliminar')
        if (!$puedeEliminar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $s = $conexion->prepare("DELETE FROM proveedores WHERE id=?");
            $s->bind_param('i', $id);
            $s->execute();
            $ok = 'Proveedor eliminado.';
        }
    }
    // Guarda el mensaje y redirige conservando la búsqueda actual
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: proveedores.php' . (!empty($_SESSION['q_prov']) ? '?q=' . urlencode($_SESSION['q_prov']) : ''));
    exit;
}

// Recupera (y consume) el mensaje flash del POST anterior
$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

// Año seleccionado (GET o sesión) y lista de años disponibles para el selector
if (isset($_GET['anio']) && preg_match('/^\d{4}$/', $_GET['anio'])) {
    $_SESSION['anio_prov'] = (int)$_GET['anio'];
}
$anio = $_SESSION['anio_prov'] ?? (int)date('Y');
$anios = array_map('intval', array_column(
    $conexion->query("SELECT DISTINCT YEAR(creado) a FROM proveedores")->fetch_all(MYSQLI_ASSOC), 'a'));
$anios[] = (int)date('Y');
$anios[] = $anio;
$anios = array_unique($anios);
rsort($anios);
// Consulta de los proveedores del año y cálculo de totales/porcentaje de homologados
$st = $conexion->prepare("SELECT * FROM proveedores WHERE YEAR(creado) = ? ORDER BY id");
$st->bind_param('i', $anio);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($filas);
$buscar = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$_SESSION['q_prov'] = $buscar;
$homologados = count(array_filter($filas, fn($p) => $p['status'] === 'HOMOLOGADO'));
$porcentaje = $total ? (int)round($homologados * 100 / $total) : 0;
// El filtrado de la búsqueda se hace en el navegador (JS) mientras se escribe
$visibles = $filas;
// Fecha mostrada y bandera para mostrar la columna de acciones
$fecha = date('d/m/Y');
$puedeModificar = $puedeEditar || $puedeEliminar;

// Devuelve la clase CSS (badge) según el valor
function clase($v) {
    return match ($v) {
        'SI' => 'b-si', 'NO' => 'b-no', 'NO APLICA' => 'b-na',
        'HOMOLOGADO' => 'b-hom', 'PENDIENTE' => 'b-pen', default => ''
    };
}
// Imprime las <option> de un select marcando la seleccionada
function opciones($filas, $sel = '') {
    foreach ($filas as $o) {
        $etiqueta = $o === '' ? 'Por completar' : $o;
        echo '<option value="' . e($o) . '"' . ($o === $sel ? ' selected' : '') . '>' . e($etiqueta) . '</option>';
    }
}

// Imprime una celda: select con envío automático si se puede editar; si no, solo texto
function celdaSelect($etiqueta, $campo, $opts, $p, $editable) {
    echo '<td data-label="' . e($etiqueta) . '" class="' . clase($p[$campo]) . '">';
    if ($editable) {
        echo '<form method="POST" class="form-inline">'
           . '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'
           . '<input type="hidden" name="accion" value="acuerdo">'
           . '<input type="hidden" name="campo" value="' . e($campo) . '">'
           . '<input type="hidden" name="id" value="' . (int)$p['id'] . '">'
           . '<select name="valor" class="select-celda" aria-label="' . e($etiqueta) . '" onchange="this.form.submit()">';
        opciones($opts, $p[$campo]);
        echo '</select></form>';
    } else {
        echo e($p[$campo]);
    }
    echo '</td>';
}

// Cabecera común de la página (menú, estilos)
require __DIR__ . '/layout_inicio.php';
?>

<?php /* Título, fecha y porcentaje de homologados */ ?>
<div class="prov-cabecera">
    <div class="prov-identidad">
        <img class="prov-logo" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
        <div>
            <h1>Listado de proveedores homologados <?= (int)$anio ?></h1>
            <p class="subtitulo">Control interno · Fecha: <?= e($fecha) ?> · Versión: 001</p>
        </div>
    </div>
    <div class="prov-porcentaje">
        <span>% de proveedores homologados:</span>
        <strong><?= $porcentaje ?>%</strong>
    </div>
</div>

<?php /* Alertas de éxito o error */ ?>
<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<?php /* Barra: selector de año, buscador y botón de alta */ ?>
<div class="prov-barra">
    <form method="GET" class="form-inline prov-anio">
        <?php if ($buscar !== ''): ?><input type="hidden" name="q" value="<?= e($buscar) ?>"><?php endif; ?>
        <label for="anio">Año de registro:</label>
        <select name="anio" id="anio" class="select-celda" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?>
                <option value="<?= $a ?>"<?= $a === $anio ? ' selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <form method="GET" class="form-inline prov-anio prov-buscar" role="search">
        <input type="hidden" name="anio" value="<?= (int)$anio ?>">
        <input type="search" name="q" value="<?= e($buscar) ?>" maxlength="100" placeholder="Buscar por razón social, RUC o tipo" aria-label="Buscar proveedor">
        <button type="submit" class="btn-sec">Buscar</button>
        <?php if ($buscar !== ''): ?><a class="btn btn-sec" href="proveedores.php?anio=<?= (int)$anio ?>">Limpiar</a><?php endif; ?>
    </form>
    <?php if ($puedeCrear): ?>
    <button type="button" id="btn-nuevo">+ Agregar proveedor</button>
    <?php endif; ?>
</div>

<?php /* Tabla de proveedores: encabezados, mensaje de vacío y una fila por proveedor */ ?>
<div class="card tabla-scroll">
    <table class="tabla-prov">
        <tr>
            <th>N°</th><th>Razón social</th><th>RUC</th><th>Tipo</th><th>Certificado</th><th>Mes</th>
            <th>Acuerdo de seguridad</th><th>DDJJ</th><th>Filtros aplicados</th><th>Status</th><th>Registrado en el sistema</th>
            <th>Acciones</th>
        </tr>
        <?php if (!$visibles): ?>
            <tr><td colspan="12"><?= $buscar             !== '' ? 'Sin resultados para «' . e($buscar) . '».' : 'No hay proveedores registrados en ' . (int)$anio . '.' ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($visibles as $i => $p): ?>
                    <tr>
            <td data-label="N°"><?= $i + 1 ?></td>
            <td data-label="Razón social"><?= e($p['razon_social']) ?></td>
            <td data-label="RUC"><?= e($p['ruc'] !== '' ? $p['ruc'] : 'Por completar') ?></td>
            <td data-label="Tipo"><?= e($p['tipo']) ?></td>
            <td data-label="Certificado"><?= e($p['certificado'] !== '' ? $p['certificado'] : 'Por completar') ?></td>
            <td data-label="Mes"><?= e($p['mes'] !== '' ? $p['mes'] : 'Por completar') ?></td>
            <?php celdaSelect('Acuerdo de seguridad', 'acuerdo_seguridad', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('DDJJ', 'ddjj', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('Filtros aplicados', 'filtros_aplicados', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('Status', 'status', $STATUS, $p, $puedeEditar); ?>
            <?php celdaSelect('Registrado en el sistema', 'registrado_sistema', $SINO, $p, $puedeEditar); ?>
            <td data-label="Acciones" class="celda-acciones">
                <button type="button" class="btn-sec btn-ver-proveedor" data-p="<?= e(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Ver proveedor <?= e($p['razon_social']) ?>" title="Ver proveedor">
                    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
                <?php if ($puedeEditar): ?>
                <button type="button" class="btn-sec btn-editar" data-p="<?= e(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>">Editar</button>
                <?php endif; ?>
                <?php if ($puedeEliminar): ?>
                <form method="POST" class="form-inline" onsubmit="return confirm('¿Eliminar este proveedor?');">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-peligro">Eliminar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php /* Modal de alta/edición (solo con permiso de crear o editar) */ ?>
<?php if ($puedeCrear || $puedeEditar): ?>
<dialog id="modal-prov">
    <header class="vista-prov-cabecera">
        <div>
            <p class="vista-prov-etiqueta">Gestión de proveedores</p>
            <h2 id="m_titulo">Proveedor</h2>
        </div>
        <span class="vista-prov-estado">Formulario</span>
    </header>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="form-prov-secciones">
            <section class="form-prov-seccion">
                <h3>Identificación del proveedor</h3>
                <div class="form-prov-grid">
                    <div class="form-grupo form-prov-ancho"><label for="f_razon_social">Razón social</label><input type="text" name="razon_social" id="f_razon_social" maxlength="200" required></div>
                    <div class="form-grupo"><label for="f_ruc">RUC</label><input type="text" name="ruc" id="f_ruc" inputmode="numeric" pattern="\d{11}" maxlength="11" title="11 dígitos"></div>
                    <div class="form-grupo"><label for="f_tipo">Tipo</label><input type="text" name="tipo" id="f_tipo" list="tipos" maxlength="40" required>
                        <datalist id="tipos"><option value="TRANSPORTE"><option value="RESGUARDO"></datalist></div>
                    <div class="form-grupo"><label for="f_certificado">Certificado</label><input type="text" name="certificado" id="f_certificado" maxlength="60"></div>
                </div>
            </section>
            <section class="form-prov-seccion">
                <h3>Información bancaria</h3>
                <div class="form-prov-grid">
                    <h4 class="form-prov-subtitulo">Banco principal</h4>
                    <div class="form-grupo form-prov-ancho"><label for="f_banco">Banco</label><select name="banco" id="f_banco"><option value="">— Seleccionar —</option><?php opciones($BANCOS); ?></select></div>
                    <div class="form-grupo"><label for="f_numero_cuenta">Número de cuenta</label><input type="text" name="numero_cuenta" id="f_numero_cuenta" maxlength="30"></div>
                    <div class="form-grupo"><label for="f_cci">CCI</label><input type="text" name="cci" id="f_cci" inputmode="numeric" pattern="\d{20}" maxlength="20" title="20 dígitos"></div>
                    <h4 class="form-prov-subtitulo">Banco adicional 1</h4>
                    <div class="form-grupo form-prov-ancho"><label for="f_banco2">Banco</label><select name="banco2" id="f_banco2"><option value="">— Seleccionar —</option><?php opciones($BANCOS); ?></select></div>
                    <div class="form-grupo"><label for="f_numero_cuenta2">Número de cuenta</label><input type="text" name="numero_cuenta2" id="f_numero_cuenta2" maxlength="30"></div>
                    <div class="form-grupo"><label for="f_cci2">CCI</label><input type="text" name="cci2" id="f_cci2" inputmode="numeric" pattern="\d{20}" maxlength="20" title="20 dígitos"></div>
                    <h4 class="form-prov-subtitulo">Banco adicional 2</h4>
                    <div class="form-grupo form-prov-ancho"><label for="f_banco3">Banco</label><select name="banco3" id="f_banco3"><option value="">— Seleccionar —</option><?php opciones($BANCOS); ?></select></div>
                    <div class="form-grupo"><label for="f_numero_cuenta3">Número de cuenta</label><input type="text" name="numero_cuenta3" id="f_numero_cuenta3" maxlength="30"></div>
                    <div class="form-grupo"><label for="f_cci3">CCI</label><input type="text" name="cci3" id="f_cci3" inputmode="numeric" pattern="\d{20}" maxlength="20" title="20 dígitos"></div>
                </div>
            </section>
            <section class="form-prov-seccion">
                <h3>Cuenta de detracciones</h3>
                <div class="form-prov-grid">
                    <div class="form-grupo"><label for="f_banco_detracciones">Banco de la Nación</label><select name="banco_detracciones" id="f_banco_detracciones"><option value="">— Seleccionar —</option><option value="<?= e($BANCO_DETRACCIONES) ?>"><?= e($BANCO_DETRACCIONES) ?></option></select></div>
                    <div class="form-grupo"><label for="f_cuenta_detracciones">Número de cuenta de detracciones</label><input type="text" name="cuenta_detracciones" id="f_cuenta_detracciones" maxlength="30"></div>
                </div>
            </section>
            <section class="form-prov-seccion">
                <h3>Homologación</h3>
                <div class="form-prov-grid">
                    <div class="form-grupo"><label for="f_mes">Mes</label><select name="mes" id="f_mes"><?php opciones($MESES); ?></select></div>
                    <div class="form-grupo"><label for="f_acuerdo_seguridad">Acuerdo de seguridad</label><select name="acuerdo_seguridad" id="f_acuerdo_seguridad"><?php opciones($TRI); ?></select></div>
                    <div class="form-grupo"><label for="f_ddjj">DDJJ</label><select name="ddjj" id="f_ddjj"><?php opciones($TRI); ?></select></div>
                    <div class="form-grupo"><label for="f_filtros_aplicados">Filtros aplicados</label><select name="filtros_aplicados" id="f_filtros_aplicados"><?php opciones($TRI); ?></select></div>
                    <div class="form-grupo"><label for="f_status">Status</label><select name="status" id="f_status"><?php opciones($STATUS); ?></select></div>
                    <div class="form-grupo"><label for="f_registrado_sistema">Registrado en el sistema</label><select name="registrado_sistema" id="f_registrado_sistema"><?php opciones($SINO); ?></select></div>
                </div>
            </section>
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn-sec" id="f_cancelar">Cancelar</button>
            <button type="submit">Guardar</button>
        </div>
    </form>
</dialog>
<script>
    // Control del modal: abrir() lo rellena con el proveedor (editar) o con valores por defecto (nuevo)
    const modal = document.getElementById("modal-prov");
    const campos = ["razon_social","ruc","tipo","certificado","banco","numero_cuenta","cci","banco2","numero_cuenta2","cci2","banco3","numero_cuenta3","cci3","banco_detracciones","cuenta_detracciones","mes","acuerdo_seguridad","ddjj","filtros_aplicados","status","registrado_sistema"];
    function abrir(p) {
        document.getElementById("m_titulo").textContent = p ? "Editar proveedor" : "Agregar proveedor";
        document.getElementById("f_id").value = p ? p.id : 0;
        document.getElementById("f_ruc").required = !p;
        campos.forEach((c) => {
            const el = document.getElementById("f_" + c);
            el.value = p ? p[c] : (c === "tipo" ? "TRANSPORTE" : (el.tagName === "SELECT" ? (c === "status" ? "PENDIENTE" : (c === "mes" ? "ENE" : (c === "banco" || c === "banco2" || c === "banco3" || c === "banco_detracciones" ? "" : "NO"))) : ""));
        });
        modal.showModal();
    }
    // Enlaza los botones Editar, Agregar y Cancelar
    document.querySelectorAll(".btn-editar").forEach((b) => b.addEventListener("click", () => abrir(JSON.parse(b.dataset.p))));
    const nuevo = document.getElementById("btn-nuevo");
    if (nuevo) nuevo.addEventListener("click", () => abrir(null));
    document.getElementById("f_cancelar").addEventListener("click", () => modal.close());
</script>
<?php endif; ?>
<dialog id="modal-ver-prov">
    <header class="vista-prov-cabecera">
        <div>
            <p class="vista-prov-etiqueta">Ficha del proveedor</p>
            <h2 id="titulo-ver-prov">Datos del proveedor</h2>
        </div>
        <span id="estado-ver-prov" class="vista-prov-estado"></span>
    </header>
    <div id="datos-ver-prov" class="vista-prov-secciones"></div>
    <div class="modal-acciones">
        <button type="button" class="btn-sec" id="cerrar-ver-prov">Cerrar</button>
    </div>
</dialog>
<script>
    const modalVerProveedor = document.getElementById("modal-ver-prov");
    const camposVistaProveedor = [
        {
            seccion: "Identificación",
            campos: [
                ["Razón social", "razon_social"],
                ["RUC", "ruc"],
                ["Tipo de proveedor", "tipo"],
                ["Certificado", "certificado"],
            ],
        },
        {
            seccion: "Información bancaria",
            campos: [
                ["Entidad bancaria", "banco"],
                ["Número de cuenta", "numero_cuenta"],
                ["CCI", "cci"],
                ["Banco 2", "banco2"],
                ["Número de cuenta 2", "numero_cuenta2"],
                ["CCI 2", "cci2"],
                ["Banco 3", "banco3"],
                ["Número de cuenta 3", "numero_cuenta3"],
                ["CCI 3", "cci3"],
                ["Banco de detracciones", "banco_detracciones"],
                ["Cuenta de detracciones", "cuenta_detracciones"],
            ],
        },
        {
            seccion: "Homologación",
            campos: [
                ["Mes de registro", "mes"],
                ["Acuerdo de seguridad", "acuerdo_seguridad"],
                ["DDJJ", "ddjj"],
                ["Filtros aplicados", "filtros_aplicados"],
                ["Estado", "status"],
                ["Registrado en el sistema", "registrado_sistema"],
            ],
        },
        {
            seccion: "Control del registro",
            campos: [
                ["N° de registro", "id"],
                ["Fecha de registro", "creado"],
                ["Última actualización", "actualizado"],
            ],
        },
    ];
    document.querySelectorAll(".btn-ver-proveedor").forEach((boton) => {
        boton.addEventListener("click", () => {
            const proveedor = JSON.parse(boton.dataset.p);
            const lista = document.getElementById("datos-ver-prov");
            lista.replaceChildren();
            document.getElementById("titulo-ver-prov").textContent = proveedor.razon_social || "Datos del proveedor";
            const estado = document.getElementById("estado-ver-prov");
            estado.textContent = proveedor.status || "Sin estado";
            estado.className = "vista-prov-estado" + (proveedor.status === "HOMOLOGADO" ? " homologado" : " pendiente");
            camposVistaProveedor.forEach(({ seccion, campos }) => {
                const bloque = document.createElement("section");
                bloque.className = "vista-prov-seccion";
                const encabezado = document.createElement("h3");
                encabezado.textContent = seccion;
                const datos = document.createElement("dl");
                datos.className = "datos-ver-prov";
                campos.forEach(([etiqueta, campo]) => {
                    const titulo = document.createElement("dt");
                    titulo.textContent = etiqueta;
                    const valor = document.createElement("dd");
                    let texto = proveedor[campo] || "Sin registrar";
                    if (["creado", "actualizado"].includes(campo) && proveedor[campo]) {
                        const fecha = proveedor[campo].match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::\d{2})?)?$/);
                        if (fecha) {
                            texto = `${fecha[3]}/${fecha[2]}/${fecha[1]}${fecha[4] ? ` ${fecha[4]}:${fecha[5]}` : ""}`;
                        }
                    }
                    valor.textContent = texto;
                    datos.append(titulo, valor);
                });
                bloque.append(encabezado, datos);
                lista.append(bloque);
            });
            modalVerProveedor.showModal();
        });
    });
    document.getElementById("cerrar-ver-prov").addEventListener("click", () => modalVerProveedor.close());
</script>
<script>
    // Filtro en vivo mientras se escribe (razón social, RUC o tipo)
    (() => {
        const input = document.querySelector(".prov-buscar input[name=q]");
        const filas = [...document.querySelectorAll(".tabla-prov tr")].filter((tr) => tr.querySelector("td[data-label='RUC']"));
        if (!input) return;
        const norm = (s) => s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toUpperCase();
        const celdas = (tr) => ["Razón social", "RUC", "Tipo"].map((l) => tr.querySelector("td[data-label='" + l + "']").textContent).join(" ");
        const textos = filas.map((tr) => norm(celdas(tr)));
        const vacio = document.createElement("tr");
        vacio.innerHTML = '<td colspan="12"></td>';
        vacio.hidden = true;
        if (filas.length) filas[0].parentNode.appendChild(vacio);
        input.addEventListener("input", () => {
            const q = norm(input.value.trim());
            let n = 0;
            filas.forEach((tr, i) => {
                const ver = textos[i].includes(q);
                tr.hidden = !ver;
                if (ver) n++;
            });
            vacio.hidden = n > 0 || !filas.length;
            vacio.firstChild.textContent = "Sin resultados para «" + input.value.trim() + "».";
        });
        input.form.addEventListener("submit", (e) => e.preventDefault());
        if (input.value) input.dispatchEvent(new Event("input"));
    })();
</script>

<?php /* Cierre del layout */ ?>
<?php require __DIR__ . '/layout_fin.php'; ?>
