<?php
$configuracionesActivos = [
    'muebles' => [
        'modulo' => 'Muebles',
        'pagina' => 'activos_muebles',
        'ruta' => 'activos_muebles.php',
        'tabla' => 'cuadro_muebles',
        'nombre' => 'Muebles',
        'singular' => 'mueble',
        'seccion' => 'Muebles y enseres',
        'mensaje' => 'muebles_mensaje',
        'estados' => ['En uso' => 'En Uso', 'Dado de Baja' => 'Dado de Baja'],
    ],
    'computo' => [
        'modulo' => 'Equipos de Cómputo',
        'pagina' => 'activos_computo',
        'ruta' => 'activos_computo.php',
        'tabla' => 'cuadro_equipos_computo',
        'nombre' => 'Equipos de Cómputo',
        'singular' => 'equipo de cómputo',
        'seccion' => 'Equipos de cómputo',
        'mensaje' => 'computo_mensaje',
        'estados' => ['En uso' => 'En Uso', 'Dado de Baja' => 'Dado de Baja'],
    ],
    'diversos' => [
        'modulo' => 'Equipos Diversos',
        'pagina' => 'activos_diversos',
        'ruta' => 'activos_diversos.php',
        'tabla' => 'cuadro_equipos_diversos',
        'nombre' => 'Equipos Diversos',
        'singular' => 'equipo diverso',
        'seccion' => 'Equipos diversos',
        'mensaje' => 'diversos_mensaje',
        'estados' => ['En uso' => 'En Uso', 'Dado de Baja' => 'Dado de Baja'],
    ],
    'monitores' => [
        'modulo' => 'Monitores',
        'pagina' => 'activos_monitores',
        'ruta' => 'activos_monitores.php',
        'tabla' => 'cuadro_monitores',
        'nombre' => 'Monitores',
        'singular' => 'monitor',
        'seccion' => 'Equipos monitores',
        'mensaje' => 'monitores_mensaje',
        'estados' => ['En uso' => 'En Uso', 'Dado de Baja' => 'Dado de Baja'],
    ],
];
if (!isset($tipoActivo, $configuracionesActivos[$tipoActivo])) {
    http_response_code(404);
    exit('Cuadro de activos no válido.');
}
$activo = $configuracionesActivos[$tipoActivo];
$paginaActiva = $activo['pagina'];
$tituloPagina = 'Activos - ' . $activo['nombre'];
require_once __DIR__ . '/auth.php';
exigirPermiso($activo['modulo'], 'ver');
$puedeCrear = puede($activo['modulo'], 'crear');
$puedeEditar = puede($activo['modulo'], 'editar');
$puedeEliminar = puede($activo['modulo'], 'eliminar');
$conexion->set_charset('utf8mb4');

function activosFalloBD(string $detalle): void
{
    error_log('Cuadro de activos: ' . $detalle);
    http_response_code(500);
    exit('No se pudo acceder al cuadro de activos. Contacte al administrador.');
}

function activosEscapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function activosEstados(array $etiquetas, string $actual = ''): array
{
    $estados = array_merge([''], array_keys($etiquetas));
    if (!in_array($actual, $estados, true)) {
        $estados[] = $actual;
    }
    return $estados;
}

$resultado = $conexion->query('SELECT version, datos FROM ' . $activo['tabla'] . ' WHERE id = 1');
if (!$resultado || !($cuadro = $resultado->fetch_assoc())) {
    activosFalloBD($conexion->error ?: 'No existe el registro del cuadro.');
}
$version = (int)$cuadro['version'];
$filas = $cuadro['datos'] === null ? null : json_decode($cuadro['datos'], true);
if ($cuadro['datos'] !== null && (!is_array($filas) || ($filas && array_keys($filas) !== range(0, count($filas) - 1)))) {
    activosFalloBD('Los datos del cuadro no tienen el formato esperado.');
}
if ($filas !== null) {
    $formatoAnterior = $tipoActivo === 'muebles' && count($filas) === 14 && isset($filas[0]) && is_array($filas[0]) && count($filas[0]) === 10;
    $grupoTerminado = false;
    foreach ($filas as $i => &$fila) {
        $totalCampos = $formatoAnterior ? 10 : 11;
        if (!is_array($fila) || array_keys($fila) !== range(0, $totalCampos - 1) || count(array_filter($fila, 'is_string')) !== $totalCampos || !in_array($fila[9], ['', '1', '2', '3', '4'], true)) {
            activosFalloBD('Una fila del cuadro no tiene el formato esperado.');
        }
        if ($formatoAnterior) {
            $fila[] = $i >= 2 && $i <= 8 ? 'compra-2026' : '';
        }
        if (!in_array($fila[10], ['', 'compra-2026'], true) || ($fila[10] !== '' && $grupoTerminado)) {
            activosFalloBD('El grupo de compra del cuadro no tiene el formato esperado.');
        }
        if ($fila[10] === '' && $i > 0 && $filas[$i - 1][10] !== '') {
            $grupoTerminado = true;
        }
    }
    unset($fila);
}

$camposCompartidos = in_array($tipoActivo, ['diversos', 'monitores'], true) ? [3, 4, 5, 6, 7, 8] : [3, 4, 5, 6, 8];
$nombresCampos = ['N°', 'Código', 'Detalle', 'RUC', 'N° FT', 'Fecha de adquisición', 'Importe activo inc. IGV', 'Estado de activo', 'Comentarios'];
$limitesCampos = [10, 100, 500, 20, 50, 20, 50, 100, 500];
function activosEsContinuacion(array $filas, int $i): bool
{
    return $i > 0 && $filas[$i][10] !== '' && $filas[$i][10] === $filas[$i - 1][10];
}

function activosTamanoGrupo(array $filas, int $i): int
{
    $tamano = 1;
    if ($filas[$i][10] !== '') {
        while (isset($filas[$i + $tamano]) && $filas[$i + $tamano][10] === $filas[$i][10]) {
            $tamano++;
        }
    }
    return $tamano;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$editando = isset($_GET['editar']) && !empty($filas);
$agregando = isset($_GET['agregar']);
if ($editando) {
    exigirPermiso($activo['modulo'], 'editar');
}
if ($agregando) {
    exigirPermiso($activo['modulo'], 'crear');
}
if ($editando && $agregando) {
    http_response_code(400);
    exit('Selecciona una sola acción.');
}

$numeroMaximo = 0;
foreach ($filas ?? [] as $fila) {
    if (preg_match('/^\d{1,10}$/D', $fila[0])) {
        $numeroMaximo = max($numeroMaximo, (int)$fila[0]);
    }
}
$nuevaFila = array_fill(0, 10, '');
if ($numeroMaximo < 9999999999) {
    $nuevaFila[0] = str_pad((string)($numeroMaximo + 1), 2, '0', STR_PAD_LEFT);
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        exit('Token inválido.');
    }
    $accion = $_POST['accion'] ?? '';
    if (!is_string($accion) || !in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
        http_response_code(400);
        exit('Acción no válida.');
    }
    exigirPermiso($activo['modulo'], $accion);
    $versionEnviada = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$versionEnviada || $versionEnviada !== $version || ($accion !== 'crear' && $filas === null)) {
        http_response_code(409);
        exit('El cuadro cambió o fue eliminado. Recarga la página antes de continuar.');
    }

    if ($accion === 'editar') {
        $editando = true;
        if (isset($_POST['filas_json'])) {
            $entrada = is_string($_POST['filas_json']) ? json_decode($_POST['filas_json'], true) : null;
        } else {
            $entrada = $_POST['filas'] ?? null;
        }
        if (!is_array($entrada) || count($entrada) !== count($filas)) {
            http_response_code(400);
            exit('Los datos del cuadro están incompletos.');
        }
        foreach ($filas as $i => &$fila) {
            if (!isset($entrada[$i]) || !is_array($entrada[$i])) {
                http_response_code(400);
                exit('Los datos del cuadro están incompletos.');
            }
            foreach ($limitesCampos as $campo => $limite) {
                if (activosEsContinuacion($filas, $i) && in_array($campo, $camposCompartidos, true)) {
                    continue;
                }
                $valor = $entrada[$i][$campo] ?? null;
                if (!is_string($valor) || !preg_match('/^.{0,' . $limite . '}$/usD', $valor)) {
                    http_response_code(400);
                    exit('Hay un campo inválido o demasiado largo en el cuadro.');
                }
                if ($campo === 7 && !in_array($valor, activosEstados($activo['estados'], $fila[7]), true)) {
                    http_response_code(400);
                    exit('El estado del ' . $activo['singular'] . ' no es válido.');
                }
                $fila[$campo] = trim($valor);
            }
            $tipo = $entrada[$i][9] ?? null;
            if (!is_string($tipo) || !in_array($tipo, ['', '1', '2', '3', '4'], true)) {
                http_response_code(400);
                exit('El tipo de semáforo no es válido.');
            }
            $fila[9] = $tipo;
        }
        unset($fila);
    } elseif ($accion === 'eliminar') {
        if (isset($_POST['filas_borrar_json'])) {
            $seleccion = is_string($_POST['filas_borrar_json']) ? json_decode($_POST['filas_borrar_json'], true) : null;
        } elseif (isset($_POST['filas_borrar'])) {
            $seleccion = $_POST['filas_borrar'];
        } elseif (isset($_POST['fila'])) {
            $seleccion = [$_POST['fila']];
        } else {
            $seleccion = null;
        }
        if (!is_array($seleccion) || !$seleccion || count($seleccion) > count($filas)) {
            http_response_code(400);
            exit('Selecciona filas válidas para borrar.');
        }
        $indices = [];
        foreach ($seleccion as $valor) {
            $indice = is_string($valor) ? filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
            if ($indice === false || !isset($filas[$indice]) || isset($indices[$indice])) {
                http_response_code(400);
                exit('Selecciona filas válidas para borrar.');
            }
            $indices[$indice] = $indice;
        }
        rsort($indices, SORT_NUMERIC);
        foreach ($indices as $indice) {
            if (!activosEsContinuacion($filas, $indice) && activosTamanoGrupo($filas, $indice) > 1) {
                foreach ($camposCompartidos as $campo) {
                    $filas[$indice + 1][$campo] = $filas[$indice][$campo];
                }
            }
            array_splice($filas, $indice, 1);
        }
    } else {
        $agregando = true;
        $editando = false;
        $entrada = $_POST['nueva_fila'] ?? null;
        if (!is_array($entrada)) {
            http_response_code(400);
            exit('Los datos de la nueva fila están incompletos.');
        }
        foreach ($limitesCampos as $campo => $limite) {
            $valor = $entrada[$campo] ?? null;
            if (!is_string($valor) || !preg_match('/^.{0,' . $limite . '}$/usD', $valor)) {
                http_response_code(400);
                exit('Hay un campo inválido o demasiado largo en la nueva fila.');
            }
            if ($campo === 7 && !in_array($valor, activosEstados($activo['estados']), true)) {
                http_response_code(400);
                exit('El estado del ' . $activo['singular'] . ' no es válido.');
            }
            $nuevaFila[$campo] = trim($valor);
        }
        $tipo = $entrada[9] ?? null;
        if (!is_string($tipo) || !in_array($tipo, ['', '1', '2', '3', '4'], true)) {
            http_response_code(400);
            exit('El tipo de semáforo no es válido.');
        }
        $nuevaFila[9] = $tipo;
        if ($nuevaFila[0] === '') {
            $error = 'Ingresa el número de la fila.';
        } else {
            $filas = $filas ?? [];
            $nuevaFila[] = '';
            $filas[] = $nuevaFila;
        }
    }
    if ($error === '') {
        $datosNuevos = json_encode($filas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($datosNuevos === false) {
            http_response_code(400);
            exit('Los datos del cuadro no se pudieron codificar.');
        }

        $sql = 'UPDATE ' . $activo['tabla'] . ' SET datos = ?, version = version + 1 WHERE id = 1 AND version = ?';
        if ($accion !== 'crear') {
            $sql .= ' AND datos IS NOT NULL';
        }
        $st = $conexion->prepare($sql);
        if (!$st) {
            activosFalloBD($conexion->error);
        }
        $st->bind_param('si', $datosNuevos, $versionEnviada);
        if (!$st->execute()) {
            activosFalloBD($st->error);
        }
        if ($st->affected_rows !== 1) {
            $st->close();
            http_response_code(409);
            exit('El cuadro cambió o fue eliminado. Recarga la página antes de continuar.');
        }
        $st->close();
        $mensajes = ['crear' => 'Fila agregada correctamente.', 'editar' => 'Cuadro actualizado correctamente.', 'eliminar' => count($indices ?? []) > 1 ? 'Filas eliminadas correctamente.' : 'Fila eliminada correctamente.'];
        $_SESSION[$activo['mensaje']] = $mensajes[$accion];
        header('Location: ' . $activo['ruta']);
        exit;
    }
}

$mensaje = $_SESSION[$activo['mensaje']] ?? '';
unset($_SESSION[$activo['mensaje']]);
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Activos - <?= activosEscapar($activo['nombre']) ?></h1>
        <p class="subtitulo">Cuadro de depreciación 2026 SBC Transport SAC.</p>
    </div>
</div>

<?php if ($mensaje): ?><div class="alerta ok" role="status"><?= activosEscapar($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta err" role="alert"><?= activosEscapar($error) ?></div><?php endif; ?>

<section class="card">
    <div class="activos-cabecera">
        <div class="activos-titulo">
            <h2>Cuadro de depreciación 2026 SBC Transport SAC</h2>
            <?php if ($puedeCrear || $agregando || $editando || (!empty($filas) && ($puedeEditar || $puedeEliminar))): ?>
            <div class="activos-acciones">
                <?php if ($editando): ?>
                    <button type="submit" form="formulario-muebles">Guardar cambios</button>
                    <a class="btn btn-sec" href="<?= activosEscapar($activo['ruta']) ?>">Cancelar</a>
                <?php elseif ($agregando): ?>
                    <a class="btn btn-sec" href="<?= activosEscapar($activo['ruta']) ?>">Cancelar</a>
                <?php else: ?>
                    <?php if ($puedeCrear): ?><a class="btn" href="<?= activosEscapar($activo['ruta']) ?>?agregar=1#agregar-mueble">Agregar</a><?php endif; ?>
                    <?php if ($puedeEditar && !empty($filas)): ?><a class="btn" href="<?= activosEscapar($activo['ruta']) ?>?editar=1">Editar</a><?php endif; ?>
                    <?php if ($puedeEliminar && !empty($filas)): ?>
                    <form method="post" id="formulario-borrar-mueble" data-seleccion-multiple>
                        <input type="hidden" name="csrf" value="<?= activosEscapar($_SESSION['csrf']) ?>">
                        <input type="hidden" name="version" value="<?= $version ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="btn-peligro">Borrar</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="tabla-wrap activos-tabla-wrap activos-semaforo-wrap">
            <table class="activos-semaforo">
                <thead>
                    <tr><th>Semáforo</th><th>Significa</th></tr>
                </thead>
                <tbody>
                    <tr class="activos-tip1">
                        <td>Tip1</td>
                        <td>Este tipo de escritura es porque son temas fuera de mi existencia</td>
                    </tr>
                    <tr class="activos-tip2">
                        <td>Tip2</td>
                        <td>Adquisición con información en mano</td>
                    </tr>
                    <tr class="activos-tip3">
                        <td>Tip3</td>
                        <td>Hay comentarios que son los escenarios de activos existentes que se trabajaron con cambios y que se tiene data sobre los cambios, mas no del activo antiguo</td>
                    </tr>
                    <tr class="activos-tip4">
                        <td>Tip4</td>
                        <td>En el caso de los muebles son los que yo mandé a realizar</td>
                    </tr>
                    <tr class="activos-sin-clasificar">
                        <td>Sin clasificar</td>
                        <td>Pendiente de asignar un tipo de semáforo</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($agregando): ?>
    <div class="activos-formulario" id="agregar-mueble">
        <h3>Agregar <?= activosEscapar($activo['singular']) ?></h3>
        <p class="activos-ayuda">Solo el número es obligatorio; puedes completar los demás datos después con Editar.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= activosEscapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="version" value="<?= $version ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="activos-formulario-campos<?= $tipoActivo !== 'muebles' ? ' activos-formulario-campos-computo' : '' ?>">
                <?php foreach ($nombresCampos as $campo => $nombreCampo): ?>
                    <div class="form-grupo<?= in_array($campo, [2, 8], true) ? ' activos-formulario-ancho' : '' ?>">
                        <label for="mueble-<?= $campo ?>"><?= activosEscapar($nombreCampo) ?><?= $campo === 0 ? ' *' : '' ?></label>
                        <?php if ($campo === 7): ?>
                            <select id="mueble-<?= $campo ?>" name="nueva_fila[<?= $campo ?>]">
                                <?php foreach (activosEstados($activo['estados']) as $valor): ?>
                                    <option value="<?= activosEscapar($valor) ?>"<?= $nuevaFila[$campo] === $valor ? ' selected' : '' ?>><?= $valor === '' ? 'Sin registrar' : activosEscapar($activo['estados'][$valor]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (in_array($campo, [2, 8], true)): ?>
                            <textarea id="mueble-<?= $campo ?>" name="nueva_fila[<?= $campo ?>]" maxlength="<?= $limitesCampos[$campo] ?>" rows="2"><?= activosEscapar($nuevaFila[$campo]) ?></textarea>
                        <?php else: ?>
                            <input id="mueble-<?= $campo ?>" name="nueva_fila[<?= $campo ?>]" maxlength="<?= $limitesCampos[$campo] ?>" value="<?= activosEscapar($nuevaFila[$campo]) ?>"<?= $campo === 0 ? ' required' : '' ?><?= $campo === 5 ? ' placeholder="dd/mm/aaaa"' : '' ?><?= $campo === 6 ? ' placeholder="S/ 0.00"' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="form-grupo">
                    <label for="mueble-tipo">Semáforo</label>
                    <select id="mueble-tipo" name="nueva_fila[9]">
                        <option value="">Sin clasificar</option>
                        <?php foreach (['1', '2', '3', '4'] as $tipo): ?>
                            <option value="<?= $tipo ?>"<?= $nuevaFila[9] === $tipo ? ' selected' : '' ?>>Tip<?= $tipo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit">Guardar fila</button>
        </form>
    </div>
    <?php endif; ?>
    <?php if ($filas === null): ?>
        <p>El cuadro de <?= activosEscapar($activo['nombre']) ?> ha sido eliminado.</p>
    <?php elseif (!$filas): ?>
        <p>No quedan filas en el cuadro de <?= activosEscapar($activo['nombre']) ?>.</p>
    <?php else: ?>
    <?php if ($editando): ?>
    <p class="activos-ayuda">Edita el cuadro completo. Los datos de compra agrupados se comparten y se editan una sola vez.</p>
    <form method="post" id="formulario-muebles">
        <input type="hidden" name="csrf" value="<?= activosEscapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="version" value="<?= $version ?>">
        <input type="hidden" name="accion" value="editar">
    <?php elseif ($puedeEliminar && !$agregando): ?>
    <p class="activos-ayuda">Selecciona una o varias filas en la columna N° y pulsa Borrar.</p>
    <?php endif; ?>
    <div class="tabla-wrap activos-tabla-wrap">
        <table class="activos-tabla<?= $tipoActivo !== 'muebles' ? ' activos-tabla-computo' : '' ?><?= $editando ? ' activos-tabla-edicion' : ($puedeEliminar && !$agregando ? ' activos-tabla-seleccion' : '') ?>">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Código</th>
                    <th>Detalle</th>
                    <th>RUC</th>
                    <th>N° FT</th>
                    <th>Fecha de adquisición</th>
                    <th>Importe activo inc. IGV</th>
                    <th>Estado de activo</th>
                    <th>Comentarios</th>
                    <?php if ($editando): ?><th>Semáforo</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <tr class="activos-seccion">
                    <th colspan="<?= $editando ? 10 : 9 ?>"><?= activosEscapar($activo['seccion']) ?></th>
                </tr>
                <?php foreach ($filas as $i => $fila): ?>
                    <tr class="<?= $fila[9] === '' ? 'activos-sin-clasificar' : 'activos-tip' . activosEscapar($fila[9]) ?>">
                        <?php foreach ($nombresCampos as $campo => $nombreCampo): ?>
                            <?php if (activosEsContinuacion($filas, $i) && in_array($campo, $camposCompartidos, true)) continue; ?>
                            <?php $tamanoGrupo = activosTamanoGrupo($filas, $i); ?>
                            <td<?= $tamanoGrupo > 1 && in_array($campo, $camposCompartidos, true) ? ' rowspan="' . $tamanoGrupo . '"' : '' ?>>
                                <?php if ($editando): ?>
                                    <?php if ($campo === 7): ?>
                                        <select name="filas[<?= $i ?>][<?= $campo ?>]" aria-label="<?= activosEscapar($nombreCampo . ', fila ' . $fila[0]) ?>">
                                            <?php foreach (activosEstados($activo['estados'], $fila[$campo]) as $valor): ?>
                                                <option value="<?= activosEscapar($valor) ?>"<?= $fila[$campo] === $valor ? ' selected' : '' ?>><?= $valor === '' ? 'Sin registrar' : activosEscapar($activo['estados'][$valor] ?? $valor . ' (actual)') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 2 || $campo === 8): ?>
                                        <textarea name="filas[<?= $i ?>][<?= $campo ?>]" maxlength="<?= $limitesCampos[$campo] ?>" aria-label="<?= activosEscapar($nombreCampo . ', fila ' . $fila[0]) ?>" rows="3"><?= activosEscapar($fila[$campo]) ?></textarea>
                                    <?php else: ?>
                                        <input name="filas[<?= $i ?>][<?= $campo ?>]" maxlength="<?= $limitesCampos[$campo] ?>" aria-label="<?= activosEscapar($nombreCampo . ', fila ' . $fila[0]) ?>" value="<?= activosEscapar($fila[$campo]) ?>">
                                    <?php endif; ?>
                                <?php elseif ($campo === 0 && $puedeEliminar && !$agregando): ?>
                                    <label class="activos-seleccion">
                                        <input type="checkbox" name="filas_borrar[]" value="<?= $i ?>" form="formulario-borrar-mueble" aria-label="Seleccionar fila <?= activosEscapar($fila[0]) ?>">
                                        <span><?= activosEscapar($fila[0]) ?></span>
                                    </label>
                                <?php else: ?>
                                    <?= activosEscapar($fila[$campo]) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($editando): ?>
                        <td>
                            <select name="filas[<?= $i ?>][9]" aria-label="Semáforo, fila <?= activosEscapar($fila[0]) ?>">
                                <option value=""<?= $fila[9] === '' ? ' selected' : '' ?>>Sin clasificar</option>
                                <?php foreach (['1', '2', '3', '4'] as $tipo): ?>
                                    <option value="<?= $tipo ?>"<?= $fila[9] === $tipo ? ' selected' : '' ?>>Tip<?= $tipo ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($editando): ?>
    </form>
    <?php endif; ?>
    <?php if ($editando || ($puedeEliminar && !$agregando)): ?>
    <script src="../JS/activos_muebles.js?v=<?= filemtime(dirname(__DIR__) . '/JS/activos_muebles.js') ?>"></script>
    <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout_fin.php'; ?>
