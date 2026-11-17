<?php
require_once __DIR__ . '/auth.php';
if (!puedeVerFlota()) {
    http_response_code(403);
    exit('Acceso denegado');
}

$tipo = $_GET['tipo'] ?? '';
if (!is_string($tipo) || !in_array($tipo, ['tractos', 'carretas'], true)) {
    http_response_code(400);
    exit('Tipo de inventario no válido.');
}
$conexion->set_charset('utf8mb4');
$paginaActiva = $tipo;
$tituloPagina = $tipo === 'tractos' ? 'Inventario de tractos' : 'Inventario de carretas';
$nombreRequerido = $tipo === 'tractos';
$error = '';
$nombre = '';
$placa = '';
$id = 0;

function flotaFalloBD(string $detalle): void
{
    error_log('Inventario de flota: ' . $detalle);
    http_response_code(500);
    exit('No se pudo acceder al inventario. Contacte al administrador.');
}

function flotaPreparar(mysqli $conexion, string $sql): mysqli_stmt
{
    $st = $conexion->prepare($sql);
    if (!$st) {
        flotaFalloBD($conexion->error);
    }
    return $st;
}

function flotaEjecutar(mysqli_stmt $st): void
{
    if (!$st->execute()) {
        flotaFalloBD($st->error);
    }
}

function flotaEscapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        exit('Token inválido');
    }
    $accion = $_POST['accion'] ?? '';
    $nombre = $nombreRequerido && is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
    $placa = is_string($_POST['placa'] ?? null) ? strtoupper(trim($_POST['placa'])) : '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

    if (!in_array($accion, ['crear', 'editar'], true) || ($accion === 'editar' && !$id)) {
        http_response_code(400);
        exit('Acción no válida.');
    }
    if ($accion === 'crear') {
        $id = 0;
    }
    if (($nombreRequerido && $nombre === '') || !preg_match('/^.{0,100}$/usD', $nombre) || !preg_match('/^[A-Z0-9-]{1,15}$/D', $placa)) {
        $error = $nombreRequerido
            ? 'Ingresa un nombre válido y una placa de hasta 15 letras, números o guiones.'
            : 'Ingresa una placa de hasta 15 letras, números o guiones.';
    } else {
        if ($accion === 'editar') {
            $comprobar = flotaPreparar($conexion, 'SELECT id FROM inventario_flota WHERE id = ? AND tipo = ?');
            $comprobar->bind_param('is', $id, $tipo);
            flotaEjecutar($comprobar);
            $resultado = $comprobar->get_result();
            if (!$resultado) {
                flotaFalloBD($comprobar->error);
            }
            if (!$resultado->fetch_assoc()) {
                http_response_code(404);
                exit('Unidad no encontrada.');
            }
            $comprobar->close();
            if ($nombreRequerido) {
                $st = flotaPreparar($conexion, 'UPDATE inventario_flota SET nombre = ?, placa = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ? AND tipo = ?');
                $st->bind_param('ssis', $nombre, $placa, $id, $tipo);
            } else {
                $st = flotaPreparar($conexion, 'UPDATE inventario_flota SET placa = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ? AND tipo = ?');
                $st->bind_param('sis', $placa, $id, $tipo);
            }
        } else {
            $st = flotaPreparar($conexion, 'INSERT INTO inventario_flota (tipo, nombre, placa, actualizado_en) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
            $st->bind_param('sss', $tipo, $nombre, $placa);
        }
        if (!$st->execute()) {
            if ($st->errno === 1062) {
                $error = 'La placa ya existe en el inventario.';
            } else {
                flotaFalloBD($st->error);
            }
        } else {
            $st->close();
            $_SESSION['flota_mensaje'] = 'Inventario actualizado correctamente.';
            header('Location: flota.php?tipo=' . $tipo);
            exit;
        }
        $st->close();
    }
} elseif (isset($_GET['editar'])) {
    $id = filter_var($_GET['editar'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if (!$id) {
        http_response_code(400);
        exit('ID no válido.');
    }
    $st = flotaPreparar($conexion, 'SELECT nombre, placa FROM inventario_flota WHERE id = ? AND tipo = ?');
    $st->bind_param('is', $id, $tipo);
    flotaEjecutar($st);
    $resultado = $st->get_result();
    if (!$resultado) {
        flotaFalloBD($st->error);
    }
    $unidad = $resultado->fetch_assoc();
    $st->close();
    if (!$unidad) {
        http_response_code(404);
        exit('Unidad no encontrada.');
    }
    $nombre = $unidad['nombre'];
    $placa = $unidad['placa'];
}

$st = flotaPreparar($conexion, 'SELECT id, nombre, placa, actualizado_en FROM inventario_flota WHERE tipo = ? ORDER BY id');
$st->bind_param('s', $tipo);
flotaEjecutar($st);
$resultado = $st->get_result();
if (!$resultado) {
    flotaFalloBD($st->error);
}
$unidades = $resultado->fetch_all(MYSQLI_ASSOC);
$st->close();
$mensaje = $_SESSION['flota_mensaje'] ?? '';
unset($_SESSION['flota_mensaje']);
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1><?= flotaEscapar($tituloPagina) ?></h1>
        <p class="subtitulo">Consulta y actualiza las unidades registradas.</p>
    </div>
</div>

<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= flotaEscapar($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err" role="alert"><?= flotaEscapar($error) ?></div><?php endif; ?>

<div class="card">
    <h2><?= flotaEscapar($tituloPagina) ?></h2>
    <div class="tabla-wrap">
        <table>
            <tr><th>ID</th><?php if ($nombreRequerido): ?><th>Nombre</th><?php endif; ?><th>Placa</th><th>Última actualización</th><th>Acciones</th></tr>
            <?php foreach ($unidades as $unidad): ?>
            <tr>
                <td data-label="ID"><?= (int)$unidad['id'] ?></td>
                <?php if ($nombreRequerido): ?><td data-label="Nombre"><?= $unidad['nombre'] !== '' ? flotaEscapar($unidad['nombre']) : 'Sin nombre' ?></td><?php endif; ?>
                <td data-label="Placa"><?= flotaEscapar($unidad['placa']) ?></td>
                <td data-label="Última actualización"><?= $unidad['actualizado_en'] ? flotaEscapar(date('d/m/Y H:i', strtotime($unidad['actualizado_en']))) : 'Sin registrar' ?></td>
                <td data-label="Acciones" class="celda-acciones"><a class="btn" href="flota.php?tipo=<?= flotaEscapar($tipo) ?>&amp;editar=<?= (int)$unidad['id'] ?>#formulario-flota">Editar</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$unidades): ?><p>No hay unidades registradas.</p><?php endif; ?>
    </div>
    <p>La fecha se registra automáticamente al crear o actualizar una unidad.</p>
</div>

<div class="card" id="formulario-flota">
    <h2><?= $id ? 'Editar unidad' : 'Agregar unidad' ?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= flotaEscapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="<?= $id ? 'editar' : 'crear' ?>">
        <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
        <?php if ($nombreRequerido): ?>
        <div class="form-grupo">
            <label for="flota-nombre">Nombre</label>
            <input id="flota-nombre" name="nombre" maxlength="100" value="<?= flotaEscapar($nombre) ?>" required>
        </div>
        <?php endif; ?>
        <div class="form-grupo">
            <label for="flota-placa">Placa</label>
            <input id="flota-placa" name="placa" maxlength="15" pattern="[A-Za-z0-9-]+" value="<?= flotaEscapar($placa) ?>" required>
        </div>
        <div class="form-fila flota-acciones">
            <button type="submit"><?= $id ? 'Guardar cambios' : 'Agregar' ?></button>
            <?php if ($id): ?><a class="btn btn-sec" href="flota.php?tipo=<?= flotaEscapar($tipo) ?>#formulario-flota">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
