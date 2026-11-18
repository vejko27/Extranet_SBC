<?php
$paginaActiva = 'rrhh_documentos_vencidos';
$tituloPagina = 'Documentos vencidos';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';
require_once __DIR__ . '/rrhh_campos_extra.php';

exigirPermiso('Recursos Humanos', 'ver');
$puedeEditar = puede('Recursos Humanos', 'editar');
$conexion->set_charset('utf8mb4');
garantizarColumnasExtra($conexion, $CAMPOS_EXTRA);
garantizarTablaContratos($conexion);
garantizarColumnasVigenciaDocumentos($conexion);
date_default_timezone_set('America/Lima');

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fechaDocumento(string $valor): ?DateTimeImmutable
{
    if ($valor === '') {
        return null;
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone('America/Lima'));
    return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
}

function fechaDocumentoDisponible($valor): string
{
    $fecha = trim((string)$valor);
    return in_array($fecha, ['', '0000-00-00'], true) ? '' : $fecha;
}

$consulta = $conexion->query("
    SELECT d.id, d.personal_id, d.tipo AS unidad, d.fecha_inicio, d.fecha_vencimiento,
           d.sin_vencimiento, p.apellido_paterno, p.apellido_materno, p.nombres, p.sede_labora AS flota,
           p.fecha_vencimiento_doc, p.tipo_contrato AS personal_tipo_contrato,
           p.contrato_inicio AS personal_contrato_inicio, p.contrato_fin AS personal_contrato_fin,
           p.periodo_inicio AS personal_periodo_inicio, pc.id AS contrato_tabla_id,
           pc.tipo_contrato AS tabla_tipo_contrato, pc.contrato_inicio AS tabla_contrato_inicio,
           pc.contrato_fin AS tabla_contrato_fin, pc.periodo_inicio AS tabla_periodo_inicio
    FROM personal_documentos d
    INNER JOIN personal p ON p.id = d.personal_id
    LEFT JOIN personal_contratos pc ON pc.id = (
        SELECT pc_actual.id
        FROM personal_contratos pc_actual
        WHERE pc_actual.personal_id = p.id
        ORDER BY
            (pc_actual.contrato_inicio IS NULL OR pc_actual.contrato_inicio = '0000-00-00'),
            pc_actual.contrato_inicio DESC,
            pc_actual.id DESC
        LIMIT 1
    )
    WHERE d.tipo <> 'Contrato'
       OR d.id = (
            SELECT d_reciente.id
            FROM personal_documentos d_reciente
            WHERE d_reciente.personal_id = d.personal_id
              AND d_reciente.tipo = 'Contrato'
            ORDER BY d_reciente.subido DESC, d_reciente.id DESC
            LIMIT 1
       )
    ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres, d.tipo, d.id
");
if (!$consulta) {
    error_log('Extranet_SBC: no se pudo consultar vencimientos de documentos: ' . $conexion->error);
    http_response_code(500);
    exit('No se pudo cargar el seguimiento de documentos. Contacte al administrador.');
}

$filas = $consulta->fetch_all(MYSQLI_ASSOC);
$hoy = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
$conteos = ['vigente' => 0, 'sin-vencimiento' => 0, 'por-vencer' => 0, 'vencido' => 0, 'sin-fecha' => 0, 'revisar' => 0];
$trabajadores = [];
$unidades = [];
$documentosPorTrabajador = [];

foreach ($filas as &$fila) {
    $fila['nombre'] = trim(implode(' ', array_filter([
        $fila['apellido_paterno'],
        $fila['apellido_materno'],
        $fila['nombres'],
    ], static fn($valor) => trim((string)$valor) !== '')));
    $fechaInicioRaw = fechaDocumentoDisponible($fila['fecha_inicio'] ?? '');
    $fechaVencimientoRaw = fechaDocumentoDisponible($fila['fecha_vencimiento'] ?? '');
    if ($fila['unidad'] === 'Contrato') {
        $usaContratoTabla = !empty($fila['contrato_tabla_id'])
            && contratoAdicionalEsMasActualizado(
                $fila['personal_contrato_inicio'],
                $fila['tabla_contrato_inicio']
            );
        $tipoContrato = (string)($usaContratoTabla ? $fila['tabla_tipo_contrato'] : $fila['personal_tipo_contrato']);
        $fechaInicioContrato = fechaDocumentoDisponible($usaContratoTabla ? $fila['tabla_contrato_inicio'] : $fila['personal_contrato_inicio']);
        $fechaFinContrato = fechaDocumentoDisponible($usaContratoTabla ? $fila['tabla_contrato_fin'] : $fila['personal_contrato_fin']);
        $periodoInicioContrato = fechaDocumentoDisponible($usaContratoTabla ? $fila['tabla_periodo_inicio'] : $fila['personal_periodo_inicio']);
        $contratoTieneDatos = trim($tipoContrato) !== '' || $fechaInicioContrato !== '' || $fechaFinContrato !== '' || $periodoInicioContrato !== '';
        if ($contratoTieneDatos && $fechaInicioRaw === '' && $fechaVencimientoRaw === '' && empty($fila['sin_vencimiento'])) {
            $fechaInicioRaw = $fechaInicioContrato;
            $fechaVencimientoRaw = $fechaFinContrato;
            $fila['sin_vencimiento'] = $fechaFinContrato === '' ? 1 : 0;
        }
    } elseif ($fila['unidad'] === 'DNI') {
        $fechaVencimientoPersonal = fechaDocumentoDisponible($fila['fecha_vencimiento_doc'] ?? '');
        if ($fechaVencimientoPersonal !== '') {
            $fechaVencimientoRaw = $fechaVencimientoPersonal;
            $fila['sin_vencimiento'] = 0;
        }
    }
    $fila['fecha_inicio_obj'] = fechaDocumento($fechaInicioRaw);
    $fila['fecha_vencimiento_obj'] = fechaDocumento($fechaVencimientoRaw);
    $fila['dias_disponibles'] = null;

    if (!empty($fila['sin_vencimiento'])) {
        $fila['estado_key'] = 'sin-vencimiento';
        $fila['estado'] = 'Vigente';
    } elseif ($fechaVencimientoRaw === '') {
        $fila['estado_key'] = 'sin-fecha';
        $fila['estado'] = 'Sin fecha';
    } elseif ($fila['fecha_vencimiento_obj'] === null) {
        $fila['estado_key'] = 'revisar';
        $fila['estado'] = 'Revisar fecha';
    } else {
        $fila['dias_disponibles'] = (int)$hoy->diff($fila['fecha_vencimiento_obj'])->format('%r%a');
        if ($fila['dias_disponibles'] <= 0) {
            $fila['estado_key'] = 'vencido';
            $fila['estado'] = 'Vencido';
        } elseif ($fila['dias_disponibles'] <= 6) {
            $fila['estado_key'] = 'por-vencer';
            $fila['estado'] = 'Por vencer';
        } else {
            $fila['estado_key'] = 'vigente';
            $fila['estado'] = 'Vigente';
        }
    }
    $conteos[$fila['estado_key']]++;
    $trabajadores[(int)$fila['personal_id']] = $fila['nombre'];
    $unidades[$fila['unidad']] = $fila['unidad'];
    $documentosPorTrabajador[(int)$fila['personal_id']][] = $fila;
}
unset($fila);
asort($trabajadores, SORT_NATURAL | SORT_FLAG_CASE);
asort($unidades, SORT_NATURAL | SORT_FLAG_CASE);

require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Documentos vencidos</h1>
        <p class="subtitulo">Seguimiento de vigencia de los documentos del personal.</p>
    </div>
</div>

<?php if (!$puedeEditar): ?>
    <div class="alerta err">Tu rol no tiene permiso para editar documentos. Solicita al administrador el permiso «Editar» de Recursos Humanos.</div>
<?php endif; ?>

<div class="contratos-resumen" aria-label="Estados de vencimiento de documentos">
    <div class="contratos-resumen-titulo">Estados</div>
    <div class="contratos-resumen-item vigente"><strong>7 o más</strong><span>Vigente · <?= $conteos['vigente'] ?></span></div>
    <div class="contratos-resumen-item vigente"><strong>Sin vencimiento</strong><span>Vigente · <?= $conteos['sin-vencimiento'] ?></span></div>
    <div class="contratos-resumen-item por-vencer"><strong>1 a 6</strong><span>Por vencer · <?= $conteos['por-vencer'] ?></span></div>
    <div class="contratos-resumen-item vencido"><strong>0 o menos</strong><span>Vencido · <?= $conteos['vencido'] ?></span></div>
    <div class="contratos-resumen-item sin-contrato"><strong>Sin fecha</strong><span><?= $conteos['sin-fecha'] ?></span></div>
</div>

<div class="card contratos-card">
    <div class="documentos-vencidos-controles">
        <label>Trabajador
            <select id="filtro-trabajador">
                <option value="">Todos</option>
                <?php foreach ($trabajadores as $id => $nombre): ?>
                    <option value="<?= (int)$id ?>"><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Unidad
            <select id="filtro-unidad">
                <option value="">Todas</option>
                <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= e($unidad) ?>"><?= e($unidad) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="btn-sec" id="filtrar-documentos-proximos" aria-pressed="false">Próximo a vencer</button>
        <button type="button" class="btn-sec" id="alternar-colores-documentos" aria-pressed="true" title="Activar o desactivar los colores de estado">Color</button>
    </div>
    <p class="contratos-contador" id="contador-documentos" aria-live="polite"></p>
    <div id="tabla-documentos-vencidos">
        <?php foreach ($documentosPorTrabajador as $personalId => $documentos): ?>
        <section class="documentos-grupo" aria-labelledby="trabajador-<?= $personalId ?>">
            <div class="documentos-grupo-trabajador">
                <span>Trabajador</span>
                <a id="trabajador-<?= $personalId ?>" href="rrhh_ficha.php?id=<?= $personalId ?>&amp;modo=ver"><?= e($documentos[0]['nombre'] ?: 'Sin nombre') ?></a>
            </div>
            <div class="tabla-wrap contratos-tabla-wrap">
        <table class="contratos-tabla">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Flota</th>
                    <th>Fecha de inicio</th>
                    <th>Fecha de vencimiento</th>
                    <th>Días disponibles</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentos as $fila):
                    $fechaInicio = $fila['fecha_inicio_obj']?->format('d/m/Y') ?? '—';
                    $fechaVencimiento = !empty($fila['sin_vencimiento'])
                        ? 'NO CADUCA'
                        : ($fila['fecha_vencimiento_obj']?->format('d/m/Y') ?? '—');
                    $dias = $fila['dias_disponibles'] === null ? '—' : (string)$fila['dias_disponibles'];
                ?>
                <tr class="contratos-fila estado-<?= e($fila['estado_key']) ?>"
                    data-trabajador="<?= (int)$fila['personal_id'] ?>"
                    data-unidad="<?= e($fila['unidad']) ?>"
                    data-estado="<?= e($fila['estado_key']) ?>">
                    <td data-label="Documento"><a href="rrhh_documentos.php?id=<?= (int)$fila['personal_id'] ?>&amp;tipo=<?= urlencode($fila['unidad']) ?>"><?= e($fila['unidad']) ?></a></td>
                    <td data-label="Flota"><?= e($fila['flota'] ?: '—') ?></td>
                    <td data-label="Fecha de inicio"><?= e($fechaInicio) ?></td>
                    <td data-label="Fecha de vencimiento"><?= e($fechaVencimiento) ?></td>
                    <td data-label="Días disponibles"><?= e($dias) ?></td>
                    <td data-label="Estado"><span class="contratos-estado"><?= e($fila['estado']) ?></span></td>
                    <td data-label="Acciones">
                        <a class="btn btn-sec" href="rrhh_documentos.php?id=<?= (int)$fila['personal_id'] ?>&amp;tipo=<?= urlencode($fila['unidad']) ?>&amp;doc=<?= (int)$fila['id'] ?>#vigencia-<?= (int)$fila['id'] ?>">
                            <?= $puedeEditar ? 'Editar vigencia' : 'Ver documento' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
            </div>
        </section>
        <?php endforeach; ?>
        <p id="documentos-sin-resultados" <?= $filas ? 'hidden' : '' ?>><?= $filas ? 'No se encontraron documentos con esos filtros.' : 'No hay documentos registrados.' ?></p>
    </div>
</div>

<script>
    (() => {
        const tabla = document.getElementById("tabla-documentos-vencidos");
        const filas = Array.from(tabla.querySelectorAll(".contratos-fila"));
        const grupos = Array.from(tabla.querySelectorAll(".documentos-grupo"));
        const trabajador = document.getElementById("filtro-trabajador");
        const unidad = document.getElementById("filtro-unidad");
        const proximos = document.getElementById("filtrar-documentos-proximos");
        const colores = document.getElementById("alternar-colores-documentos");
        const sinResultados = document.getElementById("documentos-sin-resultados");
        const contador = document.getElementById("contador-documentos");
        let soloProximos = false;

        const actualizar = () => {
            let visibles = 0;
            filas.forEach((fila) => {
                const coincideTrabajador = trabajador.value === "" || fila.dataset.trabajador === trabajador.value;
                const coincideUnidad = unidad.value === "" || fila.dataset.unidad === unidad.value;
                const coincideEstado = !soloProximos || fila.dataset.estado === "por-vencer";
                fila.hidden = !coincideTrabajador || !coincideUnidad || !coincideEstado;
                if (!fila.hidden) visibles++;
            });
            grupos.forEach((grupo) => {
                grupo.hidden = !Array.from(grupo.querySelectorAll(".contratos-fila")).some((fila) => !fila.hidden);
            });
            sinResultados.hidden = visibles > 0;
            sinResultados.textContent = filas.length === 0
                ? "No hay documentos registrados."
                : "No se encontraron documentos con esos filtros.";
            contador.textContent = `Mostrando ${visibles} de ${filas.length} documentos`;
        };

        trabajador.addEventListener("change", actualizar);
        unidad.addEventListener("change", actualizar);
        proximos.addEventListener("click", () => {
            soloProximos = !soloProximos;
            proximos.setAttribute("aria-pressed", String(soloProximos));
            proximos.textContent = soloProximos ? "Ver todos" : "Próximo a vencer";
            actualizar();
        });
        colores.addEventListener("click", () => {
            const activar = colores.getAttribute("aria-pressed") !== "true";
            colores.setAttribute("aria-pressed", String(activar));
            tabla.querySelectorAll(".contratos-tabla").forEach((elemento) => {
                elemento.classList.toggle("sin-colores", !activar);
            });
        });
        actualizar();
    })();
</script>

<?php require __DIR__ . '/layout_fin.php'; ?>
