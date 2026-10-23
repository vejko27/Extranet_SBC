<?php
// Documentos del personal: lista, filtra por tipo, sube (PDF/JPG/PNG) y elimina
// los documentos escaneados asociados a una ficha de personal.
// Variables de la página para el layout (menú activo y título)
$paginaActiva = 'rrhh';
$tituloPagina = 'Documentos del personal';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

// Permisos: "ver" para entrar, "editar" para subir y "eliminar" para borrar documentos
exigirPermiso('Recursos Humanos', 'ver');
$puedeSubir = puede('Recursos Humanos', 'editar');
$puedeEliminar = puede('Recursos Humanos', 'eliminar');

// Codificación, zona horaria y token CSRF de la sesión
$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/Lima');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
// Escapa texto para imprimirlo de forma segura en HTML
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Configuración: tipos permitidos, tamaño máximo (20 MB) y carpeta de destino
$TIPOS = ['Contrato', 'Adenda', 'DNI', 'Ficha de datos', 'Currículum', 'Certificado de estudios',
    'Antecedentes', 'Examen médico', 'Boleta de pago', 'Carta / Memorando', 'Otro'];
$MAX = 20 * 1024 * 1024;
$dir = dirname(__DIR__) . '/Documentos_Personal/';

// Carga la persona indicada por id; si no existe responde 404
$pid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = $conexion->prepare("SELECT apellido_paterno, apellido_materno, nombres, dni, carnet_extranjeria, pasaporte FROM personal WHERE id = ?");
$s->bind_param('i', $pid);
$s->execute();
$persona = $s->get_result()->fetch_assoc();
if (!$persona) {
    http_response_code(404);
    exit('Persona no encontrada');
}

// Procesamiento POST: valida CSRF y atiende las acciones "subir" y "eliminar"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = $err = '';
    $accion = $_POST['accion'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $err = 'Sesión inválida, recarga la página.';
    } elseif ($accion === 'subir') {
        // Subida: valida permiso, tipo, descripción y tamaño del archivo
        $tipo = $_POST['tipo'] ?? '';
        $desc = trim($_POST['descripcion'] ?? '');
        $f = $_FILES['archivo'] ?? null;
        if (!$puedeSubir) {
            $err = 'No tienes permiso para realizar esta acción.';
        } elseif (!in_array($tipo, $TIPOS, true) || mb_strlen($desc) > 150) {
            $err = 'Selecciona el tipo de documento (descripción de hasta 150 caracteres).';
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] <= 0 || $f['size'] > $MAX) {
            $err = 'Selecciona un archivo válido de hasta 20 MB.';
        } else {
            // Detecta el formato real por los bytes iniciales (firma), no por la extensión
            $cab = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
            if (str_starts_with($cab, '%PDF-')) {
                $ext = 'pdf'; $mime = 'application/pdf';
            } elseif (str_starts_with($cab, "\xFF\xD8\xFF")) {
                $ext = 'jpg'; $mime = 'image/jpeg';
            } elseif (str_starts_with($cab, "\x89PNG\r\n\x1a\n")) {
                $ext = 'png'; $mime = 'image/png';
            } else {
                $ext = null;
            }
            if (!$ext) {
                $err = 'Solo se admiten archivos PDF, JPG o PNG.';
            } else {
                // Nombre aleatorio en disco; se conserva el nombre original solo para mostrarlo
                $archivo = bin2hex(random_bytes(12)) . '.' . $ext;
                $orig = mb_substr(basename(str_replace('\\', '/', $f['name'])), 0, 200);
                if (!move_uploaded_file($f['tmp_name'], $dir . $archivo)) {
                    $err = 'No se pudo guardar el archivo.';
                } else {
                    // Registra el documento en BD; si falla, borra el archivo recién guardado
                    $por = (string)($_SESSION['usuario'] ?? $_SESSION['nombre'] ?? '');
                    $tam = (int)$f['size'];
                    $i = $conexion->prepare("INSERT INTO personal_documentos (personal_id,tipo,descripcion,archivo,nombre_original,mime,tamano,subido_por) VALUES (?,?,?,?,?,?,?,?)");
                    $i->bind_param('isssssis', $pid, $tipo, $desc, $archivo, $orig, $mime, $tam, $por);
                    if ($i->execute()) {
                        $ok = 'Documento subido correctamente.';
                    } else {
                        @unlink($dir . $archivo);
                        $err = 'No se pudo registrar el documento.';
                    }
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        // Eliminación: solo documentos de esta persona; borra el registro y el archivo físico
        $did = (int)($_POST['doc'] ?? 0);
        if (!$puedeEliminar) {
            $err = 'No tienes permiso para realizar esta acción.';
        } else {
            $q = $conexion->prepare("SELECT archivo FROM personal_documentos WHERE id = ? AND personal_id = ?");
            $q->bind_param('ii', $did, $pid);
            $q->execute();
            if ($fila = $q->get_result()->fetch_assoc()) {
                $del = $conexion->prepare("DELETE FROM personal_documentos WHERE id = ?");
                $del->bind_param('i', $did);
                $del->execute();
                if (preg_match('/^[a-f0-9]{24}\.(pdf|jpg|png)$/', $fila['archivo'])) {
                    @unlink($dir . $fila['archivo']);
                }
                $ok = 'Documento eliminado.';
            }
        }
    }
    // Guarda el mensaje y redirige (patrón PRG) para evitar reenvíos del formulario
    $_SESSION['flash'] = ['ok' => $ok, 'err' => $err];
    header('Location: rrhh_documentos.php?id=' . $pid);
    exit;
}

// Recupera y consume el mensaje flash de la operación anterior
$flash = $_SESSION['flash'] ?? ['ok' => '', 'err' => ''];
unset($_SESSION['flash']);
$ok = $flash['ok'];
$err = $flash['err'];

// Consulta de documentos de la persona (más recientes primero)
$s = $conexion->prepare("SELECT id, tipo, descripcion, nombre_original, mime, tamano, subido, subido_por FROM personal_documentos WHERE personal_id = ? ORDER BY subido DESC, id DESC");
$s->bind_param('i', $pid);
$s->execute();
$docs = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Filtro opcional por tipo y datos de presentación de la persona
$filtro = $_GET['tipo'] ?? '';
if ($filtro !== '') {
    $docs = array_values(array_filter($docs, fn($d) => $d['tipo'] === $filtro));
}
$nombre = trim($persona['apellido_paterno'] . ' ' . $persona['apellido_materno'] . ', ' . $persona['nombres']);
$documento = $persona['dni'] ?: ($persona['carnet_extranjeria'] ?: $persona['pasaporte']);

// Convierte bytes a un texto legible (KB / MB)
function tamanoLegible(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}

// Cabecera común del layout; desde aquí comienza el HTML
require __DIR__ . '/layout_inicio.php';
?>
<!-- Encabezado: nombre de la persona y botón Volver -->

<div class="prov-cabecera">
    <div>
        <h1>Documentos del personal</h1>
        <p class="subtitulo"><?= e($nombre) ?> · Documento: <?= e($documento) ?></p>
    </div>
    <div>
        <a class="btn btn-sec" href="rrhh.php">Volver</a>
    </div>
</div>

<!-- Alertas de resultado (éxito / error) -->
<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alerta err"><?= e($err) ?></div><?php endif; ?>

<?php /* Formulario de subida (solo con permiso de editar) */ ?>
<?php if ($puedeSubir): ?>
<form method="POST" enctype="multipart/form-data" class="card ficha">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="accion" value="subir">
    <input type="hidden" name="id" value="<?= $pid ?>">
    <h2>Subir documento escaneado</h2>
    <div class="ficha-grid">
        <div class="form-grupo"><label>Tipo de documento</label>
            <select name="tipo" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($TIPOS as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-grupo"><label>Descripción (opcional)</label><input type="text" name="descripcion" maxlength="150" placeholder="Ej.: Contrato a plazo fijo 2026"></div>
        <div class="form-grupo"><label>Archivo (PDF, JPG o PNG, máx. 20 MB)</label><input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></div>
    </div>
    <button type="submit">Subir documento</button>
</form>
<?php endif; ?>

<!-- Filtro por tipo de documento (se envía al cambiar la selección) -->
<div class="prov-barra">
    <form method="GET" class="form-inline prov-anio">
        <input type="hidden" name="id" value="<?= $pid ?>">
        <label for="tipo">Filtrar por tipo:</label>
        <select name="tipo" id="tipo" class="select-celda" onchange="this.form.submit()">
            <option value="">Todos</option>
            <?php foreach ($TIPOS as $t): ?><option<?= $t === $filtro ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Tabla de documentos cargados -->
<div class="card tabla-scroll">
    <table>
        <tr><th>Tipo</th><th>Descripción</th><th>Archivo</th><th>Tamaño</th><th>Subido</th><th>Acciones</th></tr>
        <?php if (!$docs): ?>
            <tr><td colspan="6">No hay documentos<?= $filtro !== '' ? ' de este tipo' : ' cargados' ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($docs as $d): ?>
        <tr>
            <td data-label="Tipo"><?= e($d['tipo']) ?></td>
            <td data-label="Descripción"><?= e($d['descripcion']) ?></td>
            <td data-label="Archivo"><?= e($d['nombre_original']) ?></td>
            <td data-label="Tamaño"><?= e(tamanoLegible((int)$d['tamano'])) ?></td>
            <td data-label="Subido"><?= e(date('d/m/Y H:i', strtotime($d['subido']))) ?><?= $d['subido_por'] !== '' ? ' · ' . e($d['subido_por']) : '' ?></td>
            <td data-label="Acciones" class="celda-acciones">
                <?php /* Acciones: ver, descargar y eliminar (con permiso y confirmación) */ ?>
                <a class="btn" href="ver_documento.php?d=<?= (int)$d['id'] ?>" target="_blank" rel="noopener">Ver</a>
                <a class="btn btn-sec" href="ver_documento.php?d=<?= (int)$d['id'] ?>&descargar=1">Descargar</a>
                <?php if ($puedeEliminar): ?>
                <form method="POST" class="form-inline" onsubmit="return confirm('¿Eliminar este documento?');">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <input type="hidden" name="doc" value="<?= (int)$d['id'] ?>">
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
