<?php
$paginaActiva = 'rrhh_boletas';
$tituloPagina = 'Entrega de boletas';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/bd_conexion.php';

exigirPermiso('Recursos Humanos', 'ver');
$puedeEditar = puede('Recursos Humanos', 'editar');
$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/Lima');
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$periodos = [
    'ene' => 'Enero', 'feb' => 'Febrero', 'mar' => 'Marzo', 'abr' => 'Abril',
    'may' => 'Mayo', 'jun' => 'Junio', 'grat_jul' => 'Grat. julio',
    'jul' => 'Julio', 'ago' => 'Agosto', 'set' => 'Setiembre',
    'oct' => 'Octubre', 'nov' => 'Noviembre', 'dic' => 'Diciembre',
    'grat_dic' => 'Grat. diciembre', 'vacaciones' => 'Vacaciones',
];
$estados = ['pendiente' => 'Pendiente', 'entregado' => 'Entregado', 'no_aplica' => 'No aplica'];
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function responderBoletas(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function errorBoletas(mysqli $conexion, string $operacion, bool $esPost): void
{
    error_log('Extranet_SBC: error en entrega de boletas (' . $operacion . '): ' . $conexion->error);
    if ($esPost) {
        responderBoletas(500, 'No se pudo guardar el cambio. Inténtalo nuevamente.');
    }
    http_response_code(500);
    exit('No se pudo cargar el checklist de boletas. Contacte al administrador.');
}

if ($esPost) {
    if (!$puedeEditar) {
        responderBoletas(403, 'No tienes permiso para modificar el checklist.');
    }
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        responderBoletas(403, 'Sesión inválida. Recarga la página.');
    }
}

$anioRaw = $esPost ? ($_POST['anio'] ?? '') : ($_GET['anio'] ?? date('Y'));
if (!is_string($anioRaw) || !preg_match('/^\d{4}$/D', $anioRaw) || (int)$anioRaw < 1900) {
    if ($esPost) {
        responderBoletas(400, 'El año no es válido.');
    }
    http_response_code(400);
    exit('El año no es válido.');
}
$anio = (int)$anioRaw;

if ($esPost) {
    $personalRaw = $_POST['personal_id'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    $estado = $_POST['estado'] ?? '';
    if (!is_string($personalRaw) || !preg_match('/^[1-9]\d{0,9}$/D', $personalRaw)
        || (float)$personalRaw > 2147483647 || !is_string($periodo) || !isset($periodos[$periodo])
        || !is_string($estado) || !isset($estados[$estado])) {
        responderBoletas(400, 'Los datos del checklist no son válidos.');
    }
    $personalId = (int)$personalRaw;
}

$tablaExiste = $conexion->query("SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'personal_boletas_entrega'");
if (!$tablaExiste) {
    errorBoletas($conexion, 'verificar tabla', $esPost);
}
if (!$tablaExiste->fetch_row()) {
    if (!$conexion->query("CREATE TABLE IF NOT EXISTS personal_boletas_entrega (
        personal_id INT NOT NULL,
        anio SMALLINT UNSIGNED NOT NULL,
        periodo VARCHAR(20) NOT NULL,
        estado ENUM('entregado', 'no_aplica') NOT NULL,
        PRIMARY KEY (personal_id, anio, periodo),
        CONSTRAINT fk_boletas_entrega_personal FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        errorBoletas($conexion, 'crear tabla', $esPost);
    }
}

if ($esPost) {
    $consultaPersona = $conexion->prepare('SELECT id FROM personal WHERE id = ?');
    if (!$consultaPersona) {
        errorBoletas($conexion, 'consultar personal', true);
    }
    $consultaPersona->bind_param('i', $personalId);
    if (!$consultaPersona->execute() || !($resultadoPersona = $consultaPersona->get_result())) {
        errorBoletas($conexion, 'consultar personal', true);
    }
    if (!$resultadoPersona->fetch_assoc()) {
        responderBoletas(404, 'El trabajador ya no existe. Recarga la página.');
    }
    $consultaPersona->close();

    if ($estado === 'pendiente') {
        $guardar = $conexion->prepare('DELETE FROM personal_boletas_entrega WHERE personal_id = ? AND anio = ? AND periodo = ?');
        if (!$guardar) {
            errorBoletas($conexion, 'preparar cambio', true);
        }
        $guardar->bind_param('iis', $personalId, $anio, $periodo);
    } else {
        $guardar = $conexion->prepare('INSERT INTO personal_boletas_entrega (personal_id, anio, periodo, estado)
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE estado = VALUES(estado)');
        if (!$guardar) {
            errorBoletas($conexion, 'preparar cambio', true);
        }
        $guardar->bind_param('iiss', $personalId, $anio, $periodo, $estado);
    }
    if (!$guardar->execute()) {
        errorBoletas($conexion, 'guardar cambio', true);
    }
    $guardar->close();
    responderBoletas(200, 'Cambio guardado.');
}

$consulta = $conexion->prepare("SELECT p.id, p.apellido_paterno, p.apellido_materno, p.nombres,
    p.estado AS estado_personal, b.periodo, b.estado AS estado_boleta
    FROM personal p
    LEFT JOIN personal_boletas_entrega b ON b.personal_id = p.id AND b.anio = ?
    ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres, p.id");
if (!$consulta) {
    errorBoletas($conexion, 'preparar listado', false);
}
$consulta->bind_param('i', $anio);
if (!$consulta->execute() || !($resultado = $consulta->get_result())) {
    errorBoletas($conexion, 'consultar listado', false);
}
$trabajadores = [];
while ($fila = $resultado->fetch_assoc()) {
    $id = (int)$fila['id'];
    if (!isset($trabajadores[$id])) {
        $trabajadores[$id] = [
            'nombre' => trim(implode(' ', array_filter([$fila['apellido_paterno'], $fila['apellido_materno'], $fila['nombres']]))),
            'estado' => $fila['estado_personal'],
            'periodos' => [],
        ];
    }
    if ($fila['periodo'] !== null) {
        if (!isset($periodos[$fila['periodo']]) || !isset($estados[$fila['estado_boleta']])) {
            errorBoletas($conexion, 'datos de checklist inválidos', false);
        }
        $trabajadores[$id]['periodos'][$fila['periodo']] = $fila['estado_boleta'];
    }
}
$consulta->close();

require __DIR__ . '/layout_inicio.php';
?>
<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Entrega de boletas</h1>
        <p class="subtitulo">Checklist anual de entrega al personal.</p>
    </div>
</div>

<div class="card boletas-card">
    <div class="boletas-controles">
        <form method="get" class="boletas-anio">
            <label for="boletas-anio">Año</label>
            <input id="boletas-anio" name="anio" type="number" min="1900" max="9999" step="1" value="<?= $anio ?>" required>
            <button type="submit" class="btn-sec">Consultar</button>
        </form>
        <label class="boletas-buscar">Buscar trabajador
            <input type="search" id="boletas-buscar" placeholder="Buscar por nombre">
        </label>
    </div>
    <p class="boletas-ayuda">Selecciona el estado de cada entrega. El total cuenta únicamente las boletas entregadas. El estado del trabajador corresponde a su situación actual en Personal.</p>
    <?php if (!$puedeEditar): ?>
        <p class="alerta">Solo tienes permiso de consulta; se requiere el permiso Editar de Recursos Humanos para modificar el checklist.</p>
    <?php else: ?>
        <noscript><p class="alerta err">Activa JavaScript para guardar cambios en el checklist.</p></noscript>
    <?php endif; ?>
    <p id="boletas-mensaje" class="boletas-mensaje" role="status" aria-live="polite"></p>
    <div class="tabla-wrap boletas-tabla-wrap">
        <table class="boletas-tabla" id="boletas-tabla" data-anio="<?= $anio ?>" data-csrf="<?= e($_SESSION['csrf']) ?>" data-editar="<?= $puedeEditar ? '1' : '0' ?>">
            <thead>
                <tr>
                    <th>Trabajador</th>
                    <?php foreach ($periodos as $etiqueta): ?><th><?= e($etiqueta) ?></th><?php endforeach; ?>
                    <th>Entregadas</th>
                    <th>Estado actual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trabajadores as $id => $trabajador):
                    $nombre = $trabajador['nombre'] ?: 'Sin nombre';
                    $total = count(array_filter($trabajador['periodos'], static fn($valor) => $valor === 'entregado'));
                ?>
                <tr class="boletas-trabajador" data-personal="<?= $id ?>" data-nombre="<?= e($nombre) ?>">
                    <td class="boletas-nombre" data-label="Trabajador"><a href="rrhh_ficha.php?id=<?= $id ?>&amp;modo=ver"><?= e($nombre) ?></a></td>
                    <?php foreach ($periodos as $clave => $etiqueta):
                        $valor = $trabajador['periodos'][$clave] ?? 'pendiente'; ?>
                    <td class="boletas-periodo" data-label="<?= e($etiqueta) ?>">
                        <select class="boletas-estado" data-periodo="<?= e($clave) ?>" data-guardado="<?= e($valor) ?>" data-valor="<?= e($valor) ?>" aria-label="<?= e($etiqueta . ', ' . $nombre) ?>" disabled>
                            <?php foreach ($estados as $codigo => $texto): ?>
                                <option value="<?= e($codigo) ?>" <?= $codigo === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="boletas-celda-error" aria-live="polite"></small>
                    </td>
                    <?php endforeach; ?>
                    <td class="boletas-total" data-label="Entregadas"><strong><?= $total ?></strong></td>
                    <td class="boletas-personal-estado" data-label="Estado actual"><span class="badge <?= $trabajador['estado'] === 'baja' ? 'badge-off' : 'badge-ok' ?>"><?= $trabajador['estado'] === 'baja' ? 'Inactivo' : 'Activo' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p id="boletas-sin-resultados" class="boletas-ayuda" <?= $trabajadores ? 'hidden' : '' ?>><?= $trabajadores ? 'No se encontraron trabajadores.' : 'No hay trabajadores registrados.' ?></p>
</div>

<script>
    (() => {
        const tabla = document.getElementById('boletas-tabla');
        const filas = Array.from(tabla.querySelectorAll('.boletas-trabajador'));
        const mensaje = document.getElementById('boletas-mensaje');
        const buscar = document.getElementById('boletas-buscar');
        const sinResultados = document.getElementById('boletas-sin-resultados');
        let guardando = 0;

        buscar.addEventListener('input', () => {
            const texto = buscar.value.trim().toLocaleLowerCase('es');
            filas.forEach(fila => { fila.hidden = !fila.dataset.nombre.toLocaleLowerCase('es').includes(texto); });
            sinResultados.hidden = filas.some(fila => !fila.hidden);
        });

        if (tabla.dataset.editar !== '1') return;
        tabla.querySelectorAll('.boletas-estado').forEach(select => {
            select.disabled = false;
            select.addEventListener('change', async () => {
                const fila = select.closest('.boletas-trabajador');
                const anterior = select.dataset.guardado;
                const nuevo = select.value;
                const datos = new URLSearchParams({
                    csrf: tabla.dataset.csrf,
                    anio: tabla.dataset.anio,
                    personal_id: fila.dataset.personal,
                    periodo: select.dataset.periodo,
                    estado: nuevo
                });
                select.disabled = true;
                guardando++;
                const errorCelda = select.parentElement.querySelector('.boletas-celda-error');
                errorCelda.textContent = '';
                mensaje.textContent = 'Guardando cambio...';
                mensaje.classList.remove('boletas-error');
                try {
                    const respuesta = await fetch(window.location.href, { method: 'POST', body: datos, credentials: 'same-origin' });
                    const resultado = await respuesta.json();
                    if (!respuesta.ok) throw new Error(resultado.mensaje || 'No se pudo guardar el cambio.');
                    select.dataset.guardado = nuevo;
                    select.dataset.valor = nuevo;
                    fila.querySelector('.boletas-total strong').textContent =
                        fila.querySelectorAll('.boletas-estado[data-guardado="entregado"]').length;
                    mensaje.textContent = 'Cambio guardado.';
                } catch (error) {
                    select.value = anterior;
                    errorCelda.textContent = 'No se guardó. Inténtalo nuevamente.';
                    mensaje.textContent = error.message || 'No se pudo guardar el cambio. Inténtalo nuevamente.';
                    mensaje.classList.add('boletas-error');
                } finally {
                    guardando--;
                    select.disabled = false;
                }
            });
        });
        window.addEventListener('beforeunload', evento => {
            if (guardando > 0) {
                evento.preventDefault();
                evento.returnValue = '';
            }
        });
    })();
</script>
<?php require __DIR__ . '/layout_fin.php'; ?>
