<?php
$paginaActiva = 'rrhh';
$tituloPagina = 'Recursos Humanos';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Recursos Humanos', 'ver');
$puedeCrear = puede('Recursos Humanos', 'crear');
$puedeEditar = puede('Recursos Humanos', 'editar');
$puedeEliminar = puede('Recursos Humanos', 'eliminar');

$conexion->set_charset('utf8mb4');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = $err = '';
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif (($_POST['accion'] ?? '') === 'eliminar') {
        if (!$puedeEliminar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $q = $conexion->prepare("SELECT archivo FROM personal_documentos WHERE personal_id = ?");
            $q->bind_param('i', $id);
            $q->execute();
            $archivos = array_column($q->get_result()->fetch_all(MYSQLI_ASSOC), 'archivo');
            $s = $conexion->prepare("DELETE FROM personal WHERE id = ?");
            $s->bind_param('i', $id);
            $s->execute();
            foreach ($archivos as $a) {
                if (preg_match('/^[a-f0-9]{24}\.(pdf|jpg|png)$/', $a)) {
                    @unlink(dirname(__DIR__) . '/Documentos_Personal/' . $a);
                }
            }
            $ok = 'Ficha eliminada.';
        }
    }
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: rrhh.php');
    exit;
}

$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

$buscar = trim($_GET['q'] ?? '');
$like = '%' . $buscar . '%';
$s = $conexion->prepare("SELECT id, apellido_paterno, apellido_materno, nombres, dni, carnet_extranjeria, pasaporte, puesto_trabajo, sede_labora, fecha_ingreso, celular
    FROM personal
    WHERE ? = '' OR CONCAT_WS(' ', apellido_paterno, apellido_materno, nombres) LIKE ? OR dni LIKE ? OR carnet_extranjeria LIKE ? OR pasaporte LIKE ?
    ORDER BY apellido_paterno, apellido_materno, nombres");
$s->bind_param('sssss', $buscar, $like, $like, $like, $like);
$s->execute();
$filas = $s->get_result()->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/layout_inicio.php';
?>

<h1>Recursos Humanos</h1>
<p class="subtitulo">Fichas de datos del personal.</p>

<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<div class="prov-barra">
    <form method="GET" class="form-inline prov-anio">
        <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Buscar por nombre o documento">
        <button type="submit" class="btn-sec">Buscar</button>
    </form>
    <?php if ($puedeCrear): ?>
        <a class="btn" href="rrhh_ficha.php">+ Nueva ficha</a>
    <?php endif; ?>
</div>

<div class="card tabla-scroll">
    <table>
        <tr>
            <th>N°</th><th>Apellidos y nombres</th><th>Documento</th><th>Puesto</th><th>Sede</th><th>Ingreso</th><th>Celular</th><th>Acciones</th>
        </tr>
        <?php if (!$filas): ?>
            <tr><td colspan="8">No hay fichas registradas.</td></tr>
        <?php endif; ?>
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
            <td data-label="Acciones" class="celda-acciones">
                <a class="btn" href="rrhh_documentos.php?id=<?= (int)$p['id'] ?>">Documentos</a>
                <a class="btn btn-sec" href="rrhh_ficha.php?id=<?= (int)$p['id'] ?>"><?= $puedeEditar ? 'Editar' : 'Ver' ?></a>
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

<?php require __DIR__ . '/layout_fin.php'; ?>
