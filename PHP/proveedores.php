<?php
$paginaActiva = 'proveedores';
$tituloPagina = 'Proveedores';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Proveedores', 'ver');
$puedeCrear = puede('Proveedores', 'crear');
$puedeEditar = puede('Proveedores', 'editar');
$puedeEliminar = puede('Proveedores', 'eliminar');

$conexion->set_charset('utf8mb4');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$MESES = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
$TRI = ['SI','NO','NO APLICA'];
$STATUS = ['PENDIENTE','HOMOLOGADO'];
$SINO = ['SI','NO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = $err = '';
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif ($accion === 'guardar') {
        $nuevo = $id === 0;
        if (($nuevo && !$puedeCrear) || (!$nuevo && !$puedeEditar)) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $razon = trim($_POST['razon_social'] ?? '');
            $ruc = trim($_POST['ruc'] ?? '');
            $tipo = mb_strtoupper(trim($_POST['tipo'] ?? ''));
            $cert = mb_strtoupper(trim($_POST['certificado'] ?? ''));
            $mes = $_POST['mes'] ?? '';
            $ac = $_POST['acuerdo_seguridad'] ?? '';
            $dj = $_POST['ddjj'] ?? '';
            $fi = $_POST['filtros_aplicados'] ?? '';
            $stt = $_POST['status'] ?? '';
            $reg = $_POST['registrado_sistema'] ?? '';
            if ($razon === '' || !preg_match('/^\d{11}$/', $ruc) || $tipo === '' || mb_strlen($razon) > 200
                || mb_strlen($tipo) > 40 || mb_strlen($cert) > 60
                || !in_array($mes, $MESES, true) || !in_array($ac, $TRI, true) || !in_array($dj, $TRI, true)
                || !in_array($fi, $TRI, true) || !in_array($stt, $STATUS, true) || !in_array($reg, $SINO, true)) {
                $err = 'Revisa los datos: razón social, RUC de 11 dígitos y tipo son obligatorios.';
            } elseif ($nuevo) {
                $s = $conexion->prepare("INSERT INTO proveedores (razon_social,ruc,tipo,certificado,mes,acuerdo_seguridad,ddjj,filtros_aplicados,status,registrado_sistema) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $s->bind_param('ssssssssss', $razon, $ruc, $tipo, $cert, $mes, $ac, $dj, $fi, $stt, $reg);
                $s->execute();
                $ok = 'Proveedor agregado correctamente.';
            } else {
                $s = $conexion->prepare("UPDATE proveedores SET razon_social=?,ruc=?,tipo=?,certificado=?,mes=?,acuerdo_seguridad=?,ddjj=?,filtros_aplicados=?,status=?,registrado_sistema=? WHERE id=?");
                $s->bind_param('ssssssssssi', $razon, $ruc, $tipo, $cert, $mes, $ac, $dj, $fi, $stt, $reg, $id);
                $s->execute();
                $ok = 'Proveedor actualizado correctamente.';
            }
        }
    } elseif ($accion === 'acuerdo') {
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
    } elseif ($accion === 'eliminar') {
        if (!$puedeEliminar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $s = $conexion->prepare("DELETE FROM proveedores WHERE id=?");
            $s->bind_param('i', $id);
            $s->execute();
            $ok = 'Proveedor eliminado.';
        }
    }
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: proveedores.php');
    exit;
}

$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

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
$st = $conexion->prepare("SELECT * FROM proveedores WHERE YEAR(creado) = ? ORDER BY id");
$st->bind_param('i', $anio);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($filas);
$homologados = count(array_filter($filas, fn($p) => $p['status'] === 'HOMOLOGADO'));
$porcentaje = $total ? (int)round($homologados * 100 / $total) : 0;
$ultima = $conexion->query("SELECT MAX(actualizado) f FROM proveedores WHERE YEAR(creado) = " . (int)$anio)->fetch_assoc()['f'];
$fecha = $ultima ? date('d/m/Y', strtotime($ultima)) : date('d/m/Y');
$puedeModificar = $puedeEditar || $puedeEliminar;

function clase($v) {
    return match ($v) {
        'SI' => 'b-si', 'NO' => 'b-no', 'NO APLICA' => 'b-na',
        'HOMOLOGADO' => 'b-hom', 'PENDIENTE' => 'b-pen', default => ''
    };
}
function opciones($filas, $sel = '') {
    foreach ($filas as $o) {
        echo '<option' . ($o === $sel ? ' selected' : '') . '>' . e($o) . '</option>';
    }
}

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

require __DIR__ . '/layout_inicio.php';
?>

<div class="prov-cabecera">
    <div>
        <h1>Listado de proveedores homologados <?= (int)$anio ?></h1>
        <p class="subtitulo">Control interno · Fecha: <?= e($fecha) ?> · Versión: 001</p>
    </div>
    <div class="prov-porcentaje">
        <span>% de proveedores homologados:</span>
        <strong><?= $porcentaje ?>%</strong>
    </div>
</div>

<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<div class="prov-barra">
    <form method="GET" class="form-inline prov-anio">
        <label for="anio">Año de registro:</label>
        <select name="anio" id="anio" class="select-celda" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?>
                <option value="<?= $a ?>"<?= $a === $anio ? ' selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($puedeCrear): ?>
    <button type="button" id="btn-nuevo">+ Agregar proveedor</button>
    <?php endif; ?>
</div>

<div class="card tabla-scroll">
    <table class="tabla-prov">
        <tr>
            <th>N°</th><th>Razón social</th><th>RUC</th><th>Tipo</th><th>Certificado</th><th>Mes</th>
            <th>Acuerdo de seguridad</th><th>DDJJ</th><th>Filtros aplicados</th><th>Status</th><th>Registrado en el sistema</th>
            <?php if ($puedeModificar): ?><th>Acciones</th><?php endif; ?>
        </tr>
        <?php if (!$filas): ?>
            <tr><td colspan="12">No hay proveedores registrados en <?= (int)$anio ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($filas as $i => $p): ?>
        <tr>
            <td data-label="N°"><?= $i + 1 ?></td>
            <td data-label="Razón social"><?= e($p['razon_social']) ?></td>
            <td data-label="RUC"><?= e($p['ruc']) ?></td>
            <td data-label="Tipo"><?= e($p['tipo']) ?></td>
            <td data-label="Certificado"><?= e($p['certificado']) ?></td>
            <td data-label="Mes"><?= e($p['mes']) ?></td>
            <?php celdaSelect('Acuerdo de seguridad', 'acuerdo_seguridad', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('DDJJ', 'ddjj', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('Filtros aplicados', 'filtros_aplicados', $TRI, $p, $puedeEditar); ?>
            <?php celdaSelect('Status', 'status', $STATUS, $p, $puedeEditar); ?>
            <?php celdaSelect('Registrado en el sistema', 'registrado_sistema', $SINO, $p, $puedeEditar); ?>
            <?php if ($puedeModificar): ?>
            <td data-label="Acciones" class="celda-acciones">
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
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php if ($puedeCrear || $puedeEditar): ?>
<dialog id="modal-prov">
    <h2 id="m_titulo">Proveedor</h2>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="form-grupo"><label>Razón social</label><input type="text" name="razon_social" id="f_razon_social" maxlength="200" required></div>
        <div class="form-grupo"><label>RUC</label><input type="text" name="ruc" id="f_ruc" inputmode="numeric" pattern="\d{11}" maxlength="11" title="11 dígitos" required></div>
        <div class="form-grupo"><label>Tipo</label><input type="text" name="tipo" id="f_tipo" list="tipos" maxlength="40" required>
            <datalist id="tipos"><option value="TRANSPORTE"><option value="RESGUARDO"></datalist></div>
        <div class="form-grupo"><label>Certificado</label><input type="text" name="certificado" id="f_certificado" maxlength="60"></div>
        <div class="form-grupo"><label>Mes</label><select name="mes" id="f_mes"><?php opciones($MESES); ?></select></div>
        <div class="form-grupo"><label>Acuerdo de seguridad</label><select name="acuerdo_seguridad" id="f_acuerdo_seguridad"><?php opciones($TRI); ?></select></div>
        <div class="form-grupo"><label>DDJJ</label><select name="ddjj" id="f_ddjj"><?php opciones($TRI); ?></select></div>
        <div class="form-grupo"><label>Filtros aplicados</label><select name="filtros_aplicados" id="f_filtros_aplicados"><?php opciones($TRI); ?></select></div>
        <div class="form-grupo"><label>Status</label><select name="status" id="f_status"><?php opciones($STATUS); ?></select></div>
        <div class="form-grupo"><label>Registrado en el sistema</label><select name="registrado_sistema" id="f_registrado_sistema"><?php opciones($SINO); ?></select></div>
        <div class="modal-acciones">
            <button type="button" class="btn-sec" id="f_cancelar">Cancelar</button>
            <button type="submit">Guardar</button>
        </div>
    </form>
</dialog>
<script>
    const modal = document.getElementById("modal-prov");
    const campos = ["razon_social","ruc","tipo","certificado","mes","acuerdo_seguridad","ddjj","filtros_aplicados","status","registrado_sistema"];
    function abrir(p) {
        document.getElementById("m_titulo").textContent = p ? "Editar proveedor" : "Agregar proveedor";
        document.getElementById("f_id").value = p ? p.id : 0;
        campos.forEach((c) => {
            const el = document.getElementById("f_" + c);
            el.value = p ? p[c] : (c === "tipo" ? "TRANSPORTE" : (el.tagName === "SELECT" ? (c === "status" ? "PENDIENTE" : (c === "mes" ? el.options[0].value : "NO")) : ""));
        });
        modal.showModal();
    }
    document.querySelectorAll(".btn-editar").forEach((b) => b.addEventListener("click", () => abrir(JSON.parse(b.dataset.p))));
    const nuevo = document.getElementById("btn-nuevo");
    if (nuevo) nuevo.addEventListener("click", () => abrir(null));
    document.getElementById("f_cancelar").addEventListener("click", () => modal.close());
</script>
<?php endif; ?>

<?php require __DIR__ . '/layout_fin.php'; ?>
