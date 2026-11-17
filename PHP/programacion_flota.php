<?php
require_once __DIR__ . '/auth.php';

if (!puede('Tractos', 'ver') || !puede('Carretas', 'ver')) {
    http_response_code(403);
    exit('Acceso denegado');
}

date_default_timezone_set('America/Lima');
$conexion->set_charset('utf8mb4');
$paginaActiva = 'programacion_flota';
$tituloPagina = 'Programación de Tractos y Carretas';
$puedeAdministrar = (int)($_SESSION['rol_id'] ?? 0) === 1;
$zonaHoraria = new DateTimeZone('America/Lima');

function programacionFalloBD(string $detalle): void
{
    error_log('Programación de flota: ' . $detalle);
    http_response_code(500);
    exit('No se pudo acceder a la programación de flota. Contacte al administrador.');
}

function programacionPreparar(mysqli $conexion, string $sql): mysqli_stmt
{
    $st = $conexion->prepare($sql);
    if (!$st) {
        programacionFalloBD($conexion->error);
    }
    return $st;
}

function programacionEjecutar(mysqli_stmt $st): void
{
    if (!$st->execute()) {
        programacionFalloBD($st->error);
    }
}

function programacionEscapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function programacionFecha(string $valor, DateTimeZone $zona): ?DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $zona);
    $errores = DateTimeImmutable::getLastErrors();
    if (!$fecha || ($errores && ($errores['warning_count'] || $errores['error_count'])) || $fecha->format('Y-m-d') !== $valor) {
        return null;
    }
    return $fecha;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$semanaSolicitada = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['semana'] ?? '')
    : ($_GET['semana'] ?? '');
if (!is_string($semanaSolicitada)) {
    http_response_code(400);
    exit('Semana no válida.');
}
$fechaSolicitada = $semanaSolicitada === ''
    ? new DateTimeImmutable('today', $zonaHoraria)
    : programacionFecha($semanaSolicitada, $zonaHoraria);
if (!$fechaSolicitada) {
    http_response_code(400);
    exit('Semana no válida.');
}
$inicioSemana = $fechaSolicitada->modify('-' . ((int)$fechaSolicitada->format('N') - 1) . ' days');
$finSemana = $inicioSemana->modify('+6 days');
$fechaInicio = $inicioSemana->format('Y-m-d');
$fechaFin = $finSemana->format('Y-m-d');
$dias = [];
$nombresDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
for ($i = 0; $i < 7; $i++) {
    $fecha = $inicioSemana->modify('+' . $i . ' days');
    $dias[] = [
        'fecha' => $fecha->format('Y-m-d'),
        'nombre' => $nombresDias[$i],
        'fecha_corta' => $fecha->format('d/m'),
    ];
}

$error = '';
$datosFormulario = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puedeAdministrar) {
        http_response_code(403);
        exit('Acceso denegado');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        exit('Token inválido.');
    }

    $tractosStmt = programacionPreparar($conexion, "SELECT id FROM inventario_flota WHERE tipo = 'tractos'");
    programacionEjecutar($tractosStmt);
    $tractoIds = array_map('intval', array_column($tractosStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    $tractosStmt->close();

    $carretasStmt = programacionPreparar($conexion, "SELECT id FROM inventario_flota WHERE tipo = 'carretas'");
    programacionEjecutar($carretasStmt);
    $carretaIds = array_map('intval', array_column($carretasStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    $carretasStmt->close();

    $asignaciones = $_POST['asignacion'] ?? [];
    $notas = $_POST['nota'] ?? [];
    if (!is_array($asignaciones) || !is_array($notas)) {
        http_response_code(400);
        exit('Datos de programación no válidos.');
    }
    $datosFormulario = ['asignacion' => $asignaciones, 'nota' => $notas];

    $registros = [];
    $carretasPorDia = [];
    foreach ($tractoIds as $tractoId) {
        if (!isset($asignaciones[$tractoId], $notas[$tractoId]) || !is_array($asignaciones[$tractoId]) || !is_array($notas[$tractoId])) {
            $error = 'El formulario llegó incompleto. Recarga la semana e inténtalo de nuevo.';
            break;
        }
        foreach ($dias as $dia) {
            $fecha = $dia['fecha'];
            if (!array_key_exists($fecha, $asignaciones[$tractoId]) || !array_key_exists($fecha, $notas[$tractoId])) {
                $error = 'El formulario llegó incompleto. Recarga la semana e inténtalo de nuevo.';
                break 2;
            }
            $valorCarreta = $asignaciones[$tractoId][$fecha];
            $nota = $notas[$tractoId][$fecha];
            if (!is_string($valorCarreta) || !is_string($nota) || !preg_match('/^.{0,500}$/usD', $nota)) {
                $error = 'Revisa las asignaciones y asegúrate de que las notas no excedan 500 caracteres.';
                break 2;
            }

            $carretaId = null;
            if ($valorCarreta !== '') {
                $carretaId = filter_var($valorCarreta, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$carretaId || !in_array($carretaId, $carretaIds, true)) {
                    $error = 'Una de las carretas seleccionadas ya no está disponible.';
                    break 2;
                }
                if (isset($carretasPorDia[$fecha][$carretaId])) {
                    $error = 'No puedes asignar la misma carreta a más de un tracto el mismo día.';
                    break 2;
                }
                $carretasPorDia[$fecha][$carretaId] = true;
            }

            $nota = trim($nota);
            if ($carretaId !== null || $nota !== '') {
                $registros[] = [
                    'tracto_id' => $tractoId,
                    'fecha' => $fecha,
                    'carreta_id' => $carretaId,
                    'nota' => $nota,
                ];
            }
        }
    }

    if ($error === '') {
        if (!$conexion->begin_transaction()) {
            programacionFalloBD($conexion->error);
        }
        $borrar = programacionPreparar($conexion, 'DELETE FROM programacion_flota WHERE fecha BETWEEN ? AND ?');
        $borrar->bind_param('ss', $fechaInicio, $fechaFin);
        if (!$borrar->execute()) {
            $conexion->rollback();
            programacionFalloBD($borrar->error);
        }
        $borrar->close();

        $guardar = programacionPreparar($conexion, 'INSERT INTO programacion_flota (tracto_id, fecha, carreta_id, nota) VALUES (?, ?, ?, ?)');
        foreach ($registros as $registro) {
            $tractoId = $registro['tracto_id'];
            $fecha = $registro['fecha'];
            $carretaId = $registro['carreta_id'];
            $nota = $registro['nota'];
            $guardar->bind_param('isis', $tractoId, $fecha, $carretaId, $nota);
            if (!$guardar->execute()) {
                $codigoError = $guardar->errno;
                $detalleError = $guardar->error;
                $guardar->close();
                $conexion->rollback();
                if ($codigoError === 1062) {
                    $error = 'No puedes asignar la misma carreta a más de un tracto el mismo día.';
                    break;
                }
                programacionFalloBD($detalleError);
            }
        }
        if ($error === '') {
            $guardar->close();
            if (!$conexion->commit()) {
                programacionFalloBD($conexion->error);
            }
            $_SESSION['programacion_mensaje'] = 'Programación semanal guardada correctamente.';
            header('Location: programacion_flota.php?semana=' . $fechaInicio);
            exit;
        }
    }
}

$tractosResultado = $conexion->query("SELECT id, nombre, placa FROM inventario_flota WHERE tipo = 'tractos' ORDER BY nombre, placa");
if (!$tractosResultado) {
    programacionFalloBD($conexion->error);
}
$tractos = $tractosResultado->fetch_all(MYSQLI_ASSOC);

$carretasResultado = $conexion->query("SELECT id, placa FROM inventario_flota WHERE tipo = 'carretas' ORDER BY placa");
if (!$carretasResultado) {
    programacionFalloBD($conexion->error);
}
$carretas = $carretasResultado->fetch_all(MYSQLI_ASSOC);

$asignacionesActuales = [];
$consulta = programacionPreparar($conexion, 'SELECT tracto_id, fecha, carreta_id, nota FROM programacion_flota WHERE fecha BETWEEN ? AND ?');
$consulta->bind_param('ss', $fechaInicio, $fechaFin);
programacionEjecutar($consulta);
foreach ($consulta->get_result() as $fila) {
    $asignacionesActuales[(int)$fila['tracto_id']][$fila['fecha']] = [
        'carreta_id' => $fila['carreta_id'] === null ? '' : (string)$fila['carreta_id'],
        'nota' => $fila['nota'],
    ];
}
$consulta->close();

$mensaje = $_SESSION['programacion_mensaje'] ?? '';
unset($_SESSION['programacion_mensaje']);
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Programación de Tractos y Carretas</h1>
        <p class="subtitulo">Asigna una carreta y registra notas para cada tracto y día.</p>
    </div>
</div>

<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= programacionEscapar($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err" role="alert"><?= programacionEscapar($error) ?></div><?php endif; ?>

<div class="card">
    <div class="programacion-controles">
        <a class="btn btn-sec" href="programacion_flota.php?semana=<?= programacionEscapar($inicioSemana->modify('-7 days')->format('Y-m-d')) ?>" aria-label="Semana anterior">‹ Semana anterior</a>
        <h2>Semana del <?= programacionEscapar($inicioSemana->format('d/m/Y')) ?> al <?= programacionEscapar($finSemana->format('d/m/Y')) ?></h2>
        <a class="btn btn-sec" href="programacion_flota.php?semana=<?= programacionEscapar($inicioSemana->modify('+7 days')->format('Y-m-d')) ?>" aria-label="Semana siguiente">Semana siguiente ›</a>
    </div>

    <?php if (!$tractos): ?>
        <p>No hay tractos registrados para programar.</p>
    <?php elseif (!$carretas): ?>
        <p>No hay carretas registradas. Registra carretas en el inventario antes de asignarlas.</p>
    <?php else: ?>
    <div class="tabla-wrap programacion-tabla-wrap">
        <form id="programacion-formulario" method="post" action="programacion_flota.php?semana=<?= programacionEscapar($fechaInicio) ?>">
            <input type="hidden" name="csrf" value="<?= programacionEscapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="semana" value="<?= programacionEscapar($fechaInicio) ?>">
            <table class="programacion-tabla">
                <thead>
                    <tr>
                        <th scope="col">Tracto</th>
                        <?php foreach ($dias as $dia): ?>
                        <th scope="col"><?= programacionEscapar($dia['nombre']) ?><br><span><?= programacionEscapar($dia['fecha_corta']) ?></span></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tractos as $tracto): ?>
                    <tr>
                        <th scope="row"><?= programacionEscapar(trim($tracto['nombre'] . ' ' . $tracto['placa'])) ?></th>
                        <?php foreach ($dias as $dia):
                            $actual = $asignacionesActuales[(int)$tracto['id']][$dia['fecha']] ?? ['carreta_id' => '', 'nota' => ''];
                            if ($error !== ''
                                && isset($datosFormulario['asignacion'][(int)$tracto['id']][$dia['fecha']])
                                && is_string($datosFormulario['asignacion'][(int)$tracto['id']][$dia['fecha']])
                                && isset($datosFormulario['nota'][(int)$tracto['id']][$dia['fecha']])
                                && is_string($datosFormulario['nota'][(int)$tracto['id']][$dia['fecha']])) {
                                $actual = [
                                    'carreta_id' => $datosFormulario['asignacion'][(int)$tracto['id']][$dia['fecha']],
                                    'nota' => $datosFormulario['nota'][(int)$tracto['id']][$dia['fecha']],
                                ];
                            }
                        ?>
                        <td data-label="<?= programacionEscapar($dia['nombre'] . ' ' . $dia['fecha_corta']) ?>">
                            <?php if ($puedeAdministrar): ?>
                            <label class="sr-only" for="carreta-<?= (int)$tracto['id'] ?>-<?= programacionEscapar($dia['fecha']) ?>">Carreta para <?= programacionEscapar($tracto['placa']) ?> el <?= programacionEscapar($dia['nombre']) ?></label>
                            <select id="carreta-<?= (int)$tracto['id'] ?>-<?= programacionEscapar($dia['fecha']) ?>" name="asignacion[<?= (int)$tracto['id'] ?>][<?= programacionEscapar($dia['fecha']) ?>]"<?= $error === '' ? ' disabled' : '' ?>>
                                <option value="">Sin Asignar Tracto</option>
                                <?php foreach ($carretas as $carreta): ?>
                                <option value="<?= (int)$carreta['id'] ?>"<?= $actual['carreta_id'] === (string)$carreta['id'] ? ' selected' : '' ?>><?= programacionEscapar($carreta['placa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="nota-<?= (int)$tracto['id'] ?>-<?= programacionEscapar($dia['fecha']) ?>">Nota para <?= programacionEscapar($tracto['placa']) ?> el <?= programacionEscapar($dia['nombre']) ?></label>
                            <textarea id="nota-<?= (int)$tracto['id'] ?>-<?= programacionEscapar($dia['fecha']) ?>" name="nota[<?= (int)$tracto['id'] ?>][<?= programacionEscapar($dia['fecha']) ?>]" maxlength="500" rows="2" placeholder="Nota (opcional)"<?= $error === '' ? ' disabled' : '' ?>><?= programacionEscapar($actual['nota']) ?></textarea>
                            <?php else: ?>
                                <?php
                                $placaCarreta = '';
                                foreach ($carretas as $carreta) {
                                    if ((string)$carreta['id'] === $actual['carreta_id']) {
                                        $placaCarreta = $carreta['placa'];
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($placaCarreta !== ''): ?><strong><?= programacionEscapar($placaCarreta) ?></strong><?php endif; ?>
                                <?php if ($actual['nota'] !== ''): ?><span><?= nl2br(programacionEscapar($actual['nota'])) ?></span><?php endif; ?>
                                <?php if ($placaCarreta === '' && $actual['nota'] === ''): ?><span class="programacion-vacio">—</span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($puedeAdministrar): ?>
            <div class="form-fila programacion-acciones">
                <div class="programacion-botones">
                    <button type="button" class="btn-sec" id="programacion-editar"<?= $error !== '' ? ' disabled' : '' ?>>Editar</button>
                    <button type="submit" id="programacion-guardar"<?= $error === '' ? ' disabled' : '' ?>>Guardar semana</button>
                </div>
                <span>Solo el Administrador puede editar la programación.</span>
            </div>
            <noscript><p>Activa JavaScript para editar la programación.</p></noscript>
            <script>
                document.getElementById('programacion-editar').addEventListener('click', function () {
                    document.querySelectorAll('#programacion-formulario select, #programacion-formulario textarea').forEach(function (campo) {
                        campo.disabled = false;
                    });
                    this.disabled = true;
                    document.getElementById('programacion-guardar').disabled = false;
                });
            </script>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
