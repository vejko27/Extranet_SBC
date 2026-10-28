<?php
// Listado de Recursos Humanos: muestra las fichas del personal con búsqueda,
// y permite crear/editar/ver fichas, abrir documentos y eliminar fichas según permisos.
// Variables de la página para el layout (menú activo y título)
$paginaActiva = 'rrhh';
$tituloPagina = 'Recursos Humanos';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Permisos: se exige "ver" para entrar y se calculan los permisos de las demás acciones
exigirPermiso('Recursos Humanos', 'ver');
$puedeCrear = puede('Recursos Humanos', 'crear');
$puedeEditar = puede('Recursos Humanos', 'editar');
$puedeEliminar = puede('Recursos Humanos', 'eliminar');

// Codificación de la conexión y token CSRF de la sesión
$conexion->set_charset('utf8mb4');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function garantizarColumnasPersonal(mysqli $conexion): void
{
    $res = $conexion->query("SHOW COLUMNS FROM personal");
    if (!$res) {
        return;
    }

    $columnas = [];
    while ($col = $res->fetch_assoc()) {
        $columnas[$col['Field']] = true;
    }

    if (!isset($columnas['estado'])) {
        $conexion->query("ALTER TABLE personal ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'activo'");
    }

    if (!isset($columnas['fecha_baja'])) {
        $conexion->query("ALTER TABLE personal ADD COLUMN fecha_baja DATE NULL");
    }
}

function fechaValida(string $f): bool
{
    $fd = DateTime::createFromFormat('Y-m-d', $f);
    return $fd && $fd->format('Y-m-d') === $f;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

garantizarColumnasPersonal($conexion);

// Procesamiento POST: valida CSRF y atiende la acción "toggle_baja" y "eliminar"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = $err = '';
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif (($_POST['accion'] ?? '') === 'toggle_baja') {
        if (!$puedeEditar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $estado = (($_POST['estado'] ?? 'activo') === 'baja') ? 'baja' : 'activo';
            $fechaBaja = trim((string)($_POST['fecha_baja'] ?? ''));

            if ($estado === 'baja') {
                if ($fechaBaja === '') {
                    $fechaBaja = date('Y-m-d');
                }
                if (!fechaValida($fechaBaja)) {
                    $err = 'La fecha de baja no es válida.';
                } else {
                    $s = $conexion->prepare("UPDATE personal SET estado = ?, fecha_baja = ? WHERE id = ?");
                    $s->bind_param('ssi', $estado, $fechaBaja, $id);
                    $s->execute();
                    $ok = 'Se marcó al personal como dado de baja.';
                }
            } else {
                $s = $conexion->prepare("UPDATE personal SET estado = ?, fecha_baja = NULL WHERE id = ?");
                $s->bind_param('si', $estado, $id);
                $s->execute();
                $ok = 'Se reactivó al personal.';
            }
        }
    } elseif (($_POST['accion'] ?? '') === 'eliminar') {
        if (!$puedeEliminar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            // Obtiene los archivos adjuntos de la ficha para borrarlos del disco
            $q =  $conexion->prepare("SELECT archivo FROM personal_documentos WHERE personal_id = ?");
            $q->bind_param('i', $id);
            $q->execute();
            $archivos = array_column($q->get_result()->fetch_all(MYSQLI_ASSOC), 'archivo');
            // Elimina la ficha del personal
            $s = $conexion->prepare("DELETE FROM personal WHERE id = ?");
            $s->bind_param('i', $id);
            $s->execute();
            // Borra los archivos físicos solo si el nombre tiene el formato esperado
            foreach ($archivos as $a) {
                if (preg_match('/^[a-f0-9]{24}\.(pdf|jpg|png)$/', $a)) {
                    @unlink(dirname(__DIR__) . '/Documentos_Personal/' . $a);
                }
            }
            $ok = 'Ficha eliminada.';
        }
    }
    // Guarda el mensaje y redirige (patrón PRG) para evitar reenvíos del formulario
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: rrhh.php');
    exit;
}

// Recupera y consume el mensaje flash de la operación anterior
$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

// Consulta del listado: filtra por nombre completo o número de documento (sin filtro si está vacío)
$buscar = trim($_GET['q'] ?? '');
$like = '%' . $buscar . '%';
$s = $conexion->prepare("SELECT id, apellido_paterno, apellido_materno, nombres, dni, carnet_extranjeria, pasaporte, puesto_trabajo, sede_labora, fecha_ingreso, celular, estado, fecha_baja
    FROM personal
    WHERE ? = '' OR CONCAT_WS(' ', apellido_paterno, apellido_materno, nombres) LIKE ? OR dni LIKE ? OR carnet_extranjeria LIKE ? OR pasaporte LIKE ?
    ORDER BY apellido_paterno, apellido_materno, nombres");
$s->bind_param('sssss', $buscar, $like, $like, $like, $like);
$s->execute();
$filas = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Cabecera común del layout; desde aquí comienza el HTML
require __DIR__ . '/layout_inicio.php';
?>
<!-- Título de la página -->

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Recursos Humanos</h1>
        <p class="subtitulo">Fichas de datos del personal.</p>
    </div>
</div>

<!-- Alertas de resultado (éxito / error) -->
<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<!-- Barra superior: buscador y botón de nueva ficha (solo si puede crear) -->
<div class="prov-barra">
    <form method="GET" class="form-inline prov-anio">
        <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Buscar por nombre o documento">
        <button type="submit" class="btn-sec">Buscar</button>
    </form>
    <?php if ($puedeCrear): ?>
        <a class="btn" href="rrhh_ficha.php">+ Nueva ficha</a>
    <?php endif; ?>
</div>

<!-- Tabla de fichas del personal -->
<div class="card tabla-scroll">
    <table>
        <tr>
            <th>N°</th><th>Apellidos y nombres</th><th>Documento</th><th>Puesto</th><th>Sede</th><th>Ingreso</th><th>Celular</th><th>Estado</th><th>Acciones</th>
        </tr>
        <?php if (!$filas): ?>
            <tr><td colspan="9">No hay fichas registradas.</td></tr>
        <?php endif; ?>
        <?php /* Una fila por persona; el documento usa DNI, o carnet de extranjería, o pasaporte */ ?>
        <?php foreach ($filas as $i => $p):
            $doc = $p['dni'] ?: ($p['carnet_extranjeria'] ?: $p['pasaporte']); ?>
        <tr>
            <td data-label="N°"><?= $i + 1 ?></td>
            <td data-label="Apellidos y nombres"><?= e(trim($p['apellido_paterno'] . ' ' . $p['apellido_materno'] . ', ' . $p['nombres'])) ?></td>
            <td data-label="Documento"><?= e($doc) ?></td>
            <td data-label="Puesto"><?= e($p['puesto_trabajo']) ?></td>
            <td data-label="Sede"><?= e($p['sede_labora']) ?></td>
            <td data-label="Ingreso"><?= $p['fecha_ingreso'] ? e(date('d/m/Y', strtotime($p['fecha_ingreso']))) : '' ?></td>
            <td data-label="Celular"><?= e($p['celular']) ?></td>
            <td data-label="Estado">
                <div class="rrhh-status-box">
                    <span class="badge <?= ($p['estado'] ?? 'activo') === 'baja' ? 'badge-off' : 'badge-ok' ?>"><?= e(($p['estado'] ?? 'activo') === 'baja' ? 'Dado de baja' : 'Activo') ?></span>
                    <?php if (!empty($p['fecha_baja'])): ?>
                        <small><?= e(date('d/m/Y', strtotime($p['fecha_baja']))) ?></small>
                    <?php endif; ?>
                </div>
            </td>
            <td data-label="Acciones" class="celda-acciones">
                <?php /* Acciones: documentos, vista de solo lectura, edición y eliminación según permiso */ ?>
                <a class="btn" href="rrhh_documentos.php?id=<?= (int)$p['id'] ?>">Documentos</a>
                <a class="btn btn-sec btn-ver-ficha" href="rrhh_ficha.php?id=<?= (int)$p['id'] ?>&amp;modo=ver" aria-label="Ver ficha de <?= e(trim($p['apellido_paterno'] . ' ' . $p['apellido_materno'] . ' ' . $p['nombres'])) ?>" title="Ver ficha">
                    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </a>
                <?php if ($puedeEditar): ?>
                    <a class="btn btn-sec" href="rrhh_ficha.php?id=<?= (int)$p['id'] ?>">Editar</a>
                    <form method="POST" class="form-inline rrhh-toggle-form">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="accion" value="toggle_baja">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="estado" value="<?= (($p['estado'] ?? 'activo') === 'baja') ? 'activo' : 'baja' ?>">
                        <input type="date" name="fecha_baja" value="<?= e($p['fecha_baja'] ?? '') ?>" class="input-fecha-baja" aria-label="Fecha de baja">
                        <button type="submit" class="btn <?= (($p['estado'] ?? 'activo') === 'baja') ? 'btn-ok' : 'btn-peligro' ?>">
                            <?= (($p['estado'] ?? 'activo') === 'baja') ? 'Reactivar' : 'Dar de baja' ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($puedeEliminar): ?>
                <form method="POST" class="form-inline" onsubmit="return confirm('¿Eliminar esta ficha y sus datos asociados?');">
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

<?php /* Pie común del layout */ ?>
<?php require __DIR__ . '/layout_fin.php'; ?>
