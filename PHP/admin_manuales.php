<?php
// Administración de manuales PDF: subir, editar y eliminar según los permisos del usuario.
$paginaActiva = 'admin_manuales';
$tituloPagina = 'Administrar manuales';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Permisos del usuario sobre el módulo Manuales; sin ninguno, acceso denegado
$puedeCrear = puede('Manuales', 'crear');
$puedeEditar = puede('Manuales', 'editar');
$puedeEliminar = puede('Manuales', 'eliminar');
if (!$puedeCrear && !$puedeEditar && !$puedeEliminar) {
    http_response_code(403);
    exit('Acceso denegado');
}

$conexion->set_charset('utf8mb4');
// Token CSRF de sesión y función e() para escapar HTML
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Carpeta de almacenamiento de los PDF
$dir = dirname(__DIR__) . '/Manuales/';
$ok = $err = '';

// Procesamiento POST: valida CSRF y permiso por acción (subir/editar/eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif (($_POST['accion'] ?? '') === 'subir' && !$puedeCrear
        || ($_POST['accion'] ?? '') === 'editar' && !$puedeEditar
        || ($_POST['accion'] ?? '') === 'eliminar' && !$puedeEliminar) {
        $err = 'No tienes permiso para realizar esta acción.';
    } elseif (($_POST['accion'] ?? '') === 'subir') {
        // Subir: valida título y que el archivo sea PDF real, lo guarda con nombre aleatorio y lo registra en BD
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        $f =  $_FILES['pdf'] ?? null;

        if ($titulo === '' || !$f || $f['error'] !== UPLOAD_ERR_OK) {
            $err = 'Completa el título y selecciona un PDF válido (máx. 40 MB).';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf'
            || file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            $err = 'El archivo debe ser un PDF.';
        } else {
            $archivo = bin2hex(random_bytes(8)) . '.pdf';
            if (move_uploaded_file($f['tmp_name'], $dir . $archivo)) {
                $st = $conexion->prepare("INSERT INTO manuales (titulo, descripcion, archivo) VALUES (?,?,?)");
                $st->bind_param('sss', $titulo, $desc, $archivo);
                $st->execute();
                $ok = 'Manual agregado correctamente.';
            } else {
                $err = 'No se pudo guardar el archivo.';
            }
        }
    } elseif (($_POST['accion'] ?? '') === 'eliminar') {
        // Eliminar: borra el registro y, si corresponde, el archivo físico
        $id = (int)($_POST['id'] ?? 0);
        $st = $conexion->prepare("SELECT archivo FROM manuales WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        if ($fila = $st->get_result()->fetch_assoc()) {
            $del = $conexion->prepare("DELETE FROM manuales WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            // Solo se borran los PDF subidos desde esta consola
            if (preg_match('/^[a-f0-9]{16}\.pdf$/', $fila['archivo'])) {
                @unlink($dir . $fila['archivo']);
            }
            $ok = 'Manual eliminado.';
        }
    } elseif (($_POST['accion'] ?? '') === 'editar') {
        // Editar: actualiza título y descripción del manual
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        if ($titulo === '' || $id <= 0) {
            $err = 'El título no puede estar vacío.';
        } else {
            $st = $conexion->prepare("UPDATE manuales SET titulo = ?, descripcion = ? WHERE id = ?");
            $st->bind_param('ssi', $titulo, $desc, $id);
            $st->execute();
            $ok = 'Manual actualizado correctamente.';
        }
    }

    // Post/Redirect/Get: evita reenviar el formulario al refrescar
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: admin_manuales.php');
    exit;
}

// Recupera el mensaje flash y carga el listado de manuales para la tabla
$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

require __DIR__ . '/layout_inicio.php';
$lista = $conexion->query("SELECT id, titulo, descripcion, archivo FROM manuales ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Administrar manuales</h1>
        <p class="subtitulo">Sube, edita o elimina los manuales en PDF según tus permisos.</p>
    </div>
</div>

<?php /* Alertas de resultado */ ?>
<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<!-- Formulario para subir un manual (solo con permiso de crear) -->
<?php if ($puedeCrear): ?>
<div class="card" style="max-width:520px; margin-bottom:24px">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="subir">
        <div class="form-grupo">
            <label>Título del manual</label>
            <input type="text" name="titulo" maxlength="200" required>
        </div>
        <div class="form-grupo">
            <label>Descripción</label>
            <input type="text" name="descripcion">
        </div>
        <div class="form-grupo">
            <label>Archivo PDF</label>
            <input type="file" name="pdf" accept="application/pdf,.pdf" required>
        </div>
        <button type="submit">Subir manual</button>
    </form>
</div>
<?php endif; ?>

<!-- Tabla de manuales con botones Editar y Eliminar según permisos -->
<div class="card">
    <table>
        <tr><th>Título</th><th>Acciones</th></tr>
        <?php foreach ($lista as $m): ?>
            <tr>
                <td data-label="Título"><?= e($m['titulo']) ?></td>
                <td data-label="Acciones" class="celda-acciones">
                    <?php if ($puedeEditar): ?>
                    <button type="button" class="btn-sec btn-editar"
                            data-id="<?= (int)$m['id'] ?>"
                            data-titulo="<?= e($m['titulo']) ?>"
                            data-descripcion="<?= e($m['descripcion']) ?>">Editar</button>
                    <?php endif; ?>
                    <?php if ($puedeEliminar): ?>
                    <form method="POST" class="form-inline" onsubmit="return confirm('¿Eliminar este manual?');">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn-peligro">Eliminar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Ventana modal para editar título y descripción -->
<dialog id="modal-editar">
    <h2>Editar manual</h2>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" id="e_id">
        <div class="form-grupo">
            <label>Título del manual</label>
            <input type="text" name="titulo" id="e_titulo" maxlength="200" required>
        </div>
        <div class="form-grupo">
            <label>Descripción</label>
            <input type="text" name="descripcion" id="e_descripcion">
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn-sec" id="e_cancelar">Cancelar</button>
            <button type="submit">Guardar cambios</button>
        </div>
    </form>
</dialog>

<script>
    // Al pulsar Editar, rellena el modal con los datos de la fila y lo abre
    const modal = document.getElementById("modal-editar");
    document.querySelectorAll(".btn-editar").forEach((b) => {
        b.addEventListener("click", () => {
            document.getElementById("e_id").value = b.dataset.id;
            document.getElementById("e_titulo").value = b.dataset.titulo;
            document.getElementById("e_descripcion").value = b.dataset.descripcion;
            modal.showModal();
        });
    });
    // Cierra el modal con Cancelar
    document.getElementById("e_cancelar").addEventListener("click", () => modal.close());
</script>

<?php require __DIR__ . '/layout_fin.php'; ?>
