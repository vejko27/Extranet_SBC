<?php
$paginaActiva = 'rrhh_contratos';
$tituloPagina = 'Contratos';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';
require_once __DIR__ . '/rrhh_campos_extra.php';

exigirPermiso('Recursos Humanos', 'ver');
$conexion->set_charset('utf8mb4');
garantizarColumnasExtra($conexion, $CAMPOS_EXTRA);
garantizarTablaContratos($conexion);
date_default_timezone_set('America/Lima');

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fechaContrato(string $valor): ?DateTimeImmutable
{
    if ($valor === '') {
        return null;
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone('America/Lima'));
    return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
}

$consulta = $conexion->query("
    SELECT p.id AS personal_id, p.apellido_paterno, p.apellido_materno, p.nombres,
           p.sede_labora AS flota, p.tipo_contrato, p.contrato_inicio AS fecha_inicio,
           p.contrato_fin AS fecha_fin, p.periodo_inicio, 0 AS orden_contrato
    FROM personal p
    WHERE NOT EXISTS (
        SELECT 1 FROM personal_contratos pc WHERE pc.personal_id = p.id
    )
    UNION ALL
    SELECT p.id AS personal_id, p.apellido_paterno, p.apellido_materno, p.nombres,
           p.sede_labora AS flota, pc.tipo_contrato, pc.contrato_inicio AS fecha_inicio,
           pc.contrato_fin AS fecha_fin, pc.periodo_inicio, pc.id AS orden_contrato
    FROM personal_contratos pc
    INNER JOIN personal p ON p.id = pc.personal_id
    WHERE NOT EXISTS (
        SELECT 1
        FROM personal_contratos pc_reciente
        WHERE pc_reciente.personal_id = pc.personal_id
          AND (
              pc_reciente.contrato_inicio > pc.contrato_inicio
              OR (pc.contrato_inicio IS NULL AND pc_reciente.contrato_inicio IS NOT NULL)
              OR (
                  pc_reciente.contrato_inicio <=> pc.contrato_inicio
                  AND pc_reciente.id > pc.id
              )
          )
    )
    ORDER BY apellido_paterno, apellido_materno, nombres, orden_contrato
");
if (!$consulta) {
    error_log('Extranet_SBC: no se pudo consultar contratos de personal: ' . $conexion->error);
    http_response_code(500);
    exit('No se pudo cargar el listado de contratos. Contacte al administrador.');
}

$filas = $consulta->fetch_all(MYSQLI_ASSOC);
$hoy = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
$conteos = ['vigente' => 0, 'sin-vencimiento' => 0, 'por-vencer' => 0, 'vencido' => 0, 'sin-contrato' => 0, 'revisar' => 0];

foreach ($filas as &$fila) {
    $fila['nombre'] = trim(implode(' ', array_filter([
        $fila['apellido_paterno'],
        $fila['apellido_materno'],
        $fila['nombres'],
    ], static fn($valor) => trim((string)$valor) !== '')));
    $fila['fecha_inicio_obj'] = fechaContrato((string)($fila['fecha_inicio'] ?? ''));
    $fechaFinRaw = (string)($fila['fecha_fin'] ?? '');
    $fila['fecha_fin_obj'] = fechaContrato($fechaFinRaw);
    $fila['dias_disponibles'] = null;

    $tieneContrato = trim((string)$fila['tipo_contrato']) !== ''
        || $fila['fecha_inicio_obj'] !== null
        || $fila['fecha_fin_obj'] !== null
        || trim((string)($fila['periodo_inicio'] ?? '')) !== '';

    if (!$tieneContrato) {
        $fila['estado_key'] = 'sin-contrato';
        $fila['estado'] = 'Sin contrato';
    } elseif ($fechaFinRaw === '') {
        $fila['estado_key'] = 'sin-vencimiento';
        $fila['estado'] = 'Vigente';
    } elseif ($fila['fecha_fin_obj'] === null) {
        $fila['estado_key'] = 'revisar';
        $fila['estado'] = 'Revisar fecha';
    } else {
        $fila['dias_disponibles'] = (int)$hoy->diff($fila['fecha_fin_obj'])->format('%r%a');
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
}
unset($fila);

require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Contratos</h1>
        <p class="subtitulo">Seguimiento de contratos del personal registrado.</p>
    </div>
</div>

<div class="contratos-resumen" aria-label="Estados de vencimiento de contratos">
    <div class="contratos-resumen-titulo">Estados</div>
    <div class="contratos-resumen-item vigente"><strong>7 o más</strong><span>Vigente · <?= $conteos['vigente'] ?></span></div>
    <div class="contratos-resumen-item vigente"><strong>Sin vencimiento</strong><span>Vigente · <?= $conteos['sin-vencimiento'] ?></span></div>
    <div class="contratos-resumen-item por-vencer"><strong>1 a 6</strong><span>Por vencer · <?= $conteos['por-vencer'] ?></span></div>
    <div class="contratos-resumen-item vencido"><strong>0 o menos</strong><span>Vencido · <?= $conteos['vencido'] ?></span></div>
    <div class="contratos-resumen-item sin-contrato"><strong>Sin contrato</strong><span><?= $conteos['sin-contrato'] ?> trabajador(es)</span></div>
</div>

<div class="card contratos-card">
    <div class="contratos-controles">
        <label class="sr-only" for="buscar-contrato">Buscar por trabajador o flota</label>
        <input type="search" id="buscar-contrato" placeholder="Buscar por trabajador o flota">
        <button type="button" class="btn-sec" id="filtrar-proximos" aria-pressed="false">Próximo a vencer</button>
        <button type="button" class="btn-sec" id="alternar-colores" aria-pressed="true" title="Activar o desactivar los colores de estado">Color</button>
    </div>
    <p class="contratos-contador" id="contador-contratos" aria-live="polite"></p>
    <div class="tabla-wrap contratos-tabla-wrap">
        <table class="contratos-tabla" id="tabla-contratos">
            <thead>
                <tr>
                    <th>Trabajador</th>
                    <th>Documento</th>
                    <th>Flota</th>
                    <th>Fecha de inicio</th>
                    <th>Fecha de vencimiento</th>
                    <th>Días disponibles</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila):
                    $tieneContrato = $fila['estado_key'] !== 'sin-contrato';
                    $fechaInicio = $fila['fecha_inicio_obj']?->format('d/m/Y') ?? '—';
                    $fechaFin = !$tieneContrato ? '—' : ($fila['fecha_fin_obj']?->format('d/m/Y') ?? ((string)($fila['fecha_fin'] ?? '') === '' ? 'NO CADUCA' : '—'));
                    $dias = $fila['dias_disponibles'] === null ? '—' : (string)$fila['dias_disponibles'];
                ?>
                <tr class="contratos-fila estado-<?= e($fila['estado_key']) ?>" data-estado="<?= e($fila['estado_key']) ?>">
                    <td data-label="Trabajador">
                        <?php if ((int)$fila['personal_id'] > 0): ?>
                            <a href="rrhh_ficha.php?id=<?= (int)$fila['personal_id'] ?>&amp;modo=ver"><?= e($fila['nombre'] ?: 'Sin nombre') ?></a>
                        <?php else: ?>
                            <?= e($fila['nombre'] ?: 'Sin nombre') ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Documento"><?= $tieneContrato ? 'CONTRATO' : 'SIN CONTRATO' ?></td>
                    <td data-label="Flota"><?= e($fila['flota'] ?: '—') ?></td>
                    <td data-label="Fecha de inicio"><?= e($fechaInicio) ?></td>
                    <td data-label="Fecha de vencimiento"><?= e($fechaFin) ?></td>
                    <td data-label="Días disponibles"><?= e($dias) ?></td>
                    <td data-label="Estado"><span class="contratos-estado"><?= e($fila['estado']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <tr id="contratos-sin-resultados" <?= $filas ? 'hidden' : '' ?>>
                    <td colspan="7"><?= $filas ? 'No se encontraron contratos con esos filtros.' : 'No hay trabajadores registrados.' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    (() => {
        const tabla = document.getElementById("tabla-contratos");
        const filas = Array.from(tabla.querySelectorAll(".contratos-fila"));
        const buscar = document.getElementById("buscar-contrato");
        const filtrarProximos = document.getElementById("filtrar-proximos");
        const alternarColores = document.getElementById("alternar-colores");
        const sinResultados = document.getElementById("contratos-sin-resultados");
        const contador = document.getElementById("contador-contratos");
        let soloProximos = false;

        const actualizar = () => {
            const termino = buscar.value.trim().toLocaleLowerCase("es");
            let visibles = 0;
            filas.forEach((fila) => {
                const coincideTexto = fila.textContent.toLocaleLowerCase("es").includes(termino);
                const coincideEstado = !soloProximos || fila.dataset.estado === "por-vencer";
                fila.hidden = !coincideTexto || !coincideEstado;
                if (!fila.hidden) visibles++;
            });
            sinResultados.hidden = visibles > 0;
            sinResultados.firstElementChild.textContent = filas.length === 0
                ? "No hay trabajadores registrados."
                : "No se encontraron contratos con esos filtros.";
            contador.textContent = `Mostrando ${visibles} de ${filas.length} registros`;
        };

        buscar.addEventListener("input", actualizar);
        filtrarProximos.addEventListener("click", () => {
            soloProximos = !soloProximos;
            filtrarProximos.setAttribute("aria-pressed", String(soloProximos));
            filtrarProximos.textContent = soloProximos ? "Ver todos" : "Próximo a vencer";
            actualizar();
        });
        alternarColores.addEventListener("click", () => {
            const activar = alternarColores.getAttribute("aria-pressed") !== "true";
            alternarColores.setAttribute("aria-pressed", String(activar));
            tabla.classList.toggle("sin-colores", !activar);
        });
        actualizar();
    })();
</script>

<?php require __DIR__ . '/layout_fin.php'; ?>
