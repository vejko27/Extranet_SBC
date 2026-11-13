<?php
$paginaActiva = 'calculo_detraccion';
$tituloPagina = 'Cálculo de detracción';
require_once __DIR__ . '/auth.php';
if (!puedeVerDetraccion()) {
    http_response_code(403);
    exit('Acceso denegado');
}
require __DIR__ . '/layout_inicio.php';
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Cálculo de detracción</h1>
        <p class="subtitulo">Datos para el cálculo de detracciones.</p>
    </div>
</div>

<div class="alerta detraccion-aviso" role="status">
    <strong>Cálculo pendiente de validación.</strong>
    La fórmula se habilitará cuando la contadora confirme la base de cálculo y las reglas aplicables. Por ahora no se generan importes.
</div>

<form class="card detraccion-form" onsubmit="return false">
    <div class="detraccion-cabecera">
        <div>
            <h2>Datos de la operación</h2>
            <p>Los datos se ingresan manualmente y no se guardan.</p>
        </div>
        <button type="reset" class="btn-sec">Limpiar</button>
    </div>
    <div class="detraccion-campos">
        <label>Moneda de la factura
            <select name="moneda">
                <option value="PEN">Soles (S/)</option>
                <option value="USD">Dólares (US$)</option>
            </select>
        </label>
        <label>Importe de la factura
            <input type="number" name="importe" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
        </label>
        <label>Valor referencial (S/)
            <input type="number" name="referencial" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
        </label>
        <label>Tipo de cambio venta
            <input type="number" name="tipo_cambio" min="0" step="0.001" inputmode="decimal" placeholder="0.000">
        </label>
        <label>Detracción (%)
            <input type="number" name="porcentaje" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0.00">
        </label>
        <label>Redondeo
            <select name="redondeo">
                <option value="">Seleccionar</option>
                <option value="entero">Sol entero más cercano</option>
                <option value="centimos">Dos decimales</option>
            </select>
        </label>
    </div>
    <p class="detraccion-nota">Consulta el tipo de cambio correspondiente a la fecha de emisión de la factura en
        <a href="https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias" target="_blank" rel="noopener noreferrer">SUNAT: tipo de cambio</a>.
    </p>
</form>

<div class="card detraccion-resultados">
    <h2>Resultados</h2>
    <p>Se mostrarán cuando se habilite el cálculo.</p>
    <div class="detraccion-resultados-grid">
        <div><span>Factura en soles</span><strong>No disponible</strong></div>
        <div><span>Detracción en soles</span><strong>No disponible</strong></div>
        <div><span>Detracción en dólares</span><strong>No disponible</strong></div>
        <div><span>Neto por pagar</span><strong>No disponible</strong></div>
    </div>
</div>

<div class="card detraccion-referencias">
    <h2>Códigos de referencia</h2>
    <p>Según la imagen proporcionada. Confirma la tasa aplicable con la contadora antes de utilizarla.</p>
    <ul>
        <li><span>027 · Transporte de carga</span><strong>4 %</strong></li>
        <li><span>037 · Demás servicios gravados con IGV</span><strong>12 %</strong></li>
        <li><span>019 · Alquileres</span><strong>10 %</strong></li>
        <li><span>021 · Movimiento de carga</span><strong>10 %</strong></li>
    </ul>
</div>

<?php require __DIR__ . '/layout_fin.php'; ?>
