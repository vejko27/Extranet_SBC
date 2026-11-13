<?php
$paginaActiva = 'calculo_detraccion';
$tituloPagina = 'Cálculo de detracción';
require_once __DIR__ . '/auth.php';
exigirPermiso('Cálculo de detracción', 'ver');
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
    <strong>Transporte: 4 % desde S/ 400 sobre la base seleccionada.</strong>
    Selecciona la base y el redondeo según las indicaciones de la contadora. El caso con chofer adicional aún está pendiente de confirmación.
    Para S/ 400 exactos, ten en cuenta que la <a href="https://orientacion.sunat.gob.pe/detracciones-en-el-transporte-de-bienes-por-via-terrestre" target="_blank" rel="noopener noreferrer">SUNAT indica un importe mayor a S/ 400</a>; verifica antes de realizar el depósito.
</div>

<form class="card detraccion-form" id="detraccion-form" onsubmit="return false" novalidate>
    <div class="detraccion-cabecera">
        <div>
            <h2>Datos de la operación</h2>
            <p>Los datos se ingresan manualmente y no se guardan.</p>
        </div>
        <div class="detraccion-acciones">
            <button type="submit" id="detraccion-calcular" disabled>Calcular</button>
            <button type="reset" class="btn-sec">Limpiar</button>
        </div>
    </div>
    <div class="detraccion-campos">
        <label>Moneda de la factura
            <select name="moneda" id="detraccion-moneda">
                <option value="PEN">Soles (S/)</option>
                <option value="USD">Dólares (US$)</option>
            </select>
        </label>
        <label>Importe de la factura
            <input type="number" name="importe" id="detraccion-importe" min="0" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" required>
        </label>
        <label>Valor referencial (S/)
            <input type="number" name="referencial" id="detraccion-referencial" min="0" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="0.00">
        </label>
        <label>Tipo de cambio venta
            <input type="number" name="tipo_cambio" id="detraccion-tipo-cambio" min="0.001" max="9999.999" step="0.001" inputmode="decimal" placeholder="0.000">
        </label>
        <label>Base para el 4 %
            <select name="base" id="detraccion-base" required>
                <option value="">Seleccionar</option>
                <option value="factura">Importe de factura en soles</option>
                <option value="referencial">Valor referencial en soles</option>
            </select>
        </label>
        <label>¿Incluye chofer adicional?
            <select name="chofer" id="detraccion-chofer" required>
                <option value="">Seleccionar</option>
                <option value="no">No</option>
                <option value="si">Sí (regla pendiente)</option>
            </select>
        </label>
        <label>Redondeo de la detracción en soles
            <select name="redondeo" id="detraccion-redondeo" required>
                <option value="">Seleccionar</option>
                <option value="entero">Sol entero más cercano</option>
                <option value="centimos">Dos decimales</option>
            </select>
        </label>
    </div>
    <noscript><p class="alerta err">Activa JavaScript para calcular la detracción.</p></noscript>
    <p id="detraccion-mensaje" class="detraccion-mensaje" role="status" aria-live="polite"></p>
    <p class="detraccion-nota">Consulta el tipo de cambio correspondiente a la fecha de emisión de la factura en
        <a href="https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias" target="_blank" rel="noopener noreferrer">SUNAT: tipo de cambio</a>.
        El tipo de cambio es obligatorio para facturas en dólares y opcional para mostrar la detracción en dólares cuando la factura está en soles.
    </p>
</form>

<div class="card detraccion-resultados">
    <h2>Resultados</h2>
    <p>El neto se muestra en la moneda de la factura. Los dólares se redondean a dos decimales.</p>
    <div class="detraccion-resultados-grid">
        <div><span>Factura en soles</span><strong id="resultado-factura">—</strong></div>
        <div><span>Detracción en soles</span><strong id="resultado-soles">—</strong></div>
        <div><span>Detracción en dólares</span><strong id="resultado-dolares">—</strong></div>
        <div><span id="resultado-neto-etiqueta">Neto por pagar</span><strong id="resultado-neto">—</strong></div>
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

<script>
    (() => {
        const formulario = document.getElementById('detraccion-form');
        const moneda = document.getElementById('detraccion-moneda');
        const importe = document.getElementById('detraccion-importe');
        const referencial = document.getElementById('detraccion-referencial');
        const tipoCambio = document.getElementById('detraccion-tipo-cambio');
        const base = document.getElementById('detraccion-base');
        const chofer = document.getElementById('detraccion-chofer');
        const redondeo = document.getElementById('detraccion-redondeo');
        const mensaje = document.getElementById('detraccion-mensaje');
        const facturaResultado = document.getElementById('resultado-factura');
        const solesResultado = document.getElementById('resultado-soles');
        const dolaresResultado = document.getElementById('resultado-dolares');
        const netoResultado = document.getElementById('resultado-neto');
        const netoEtiqueta = document.getElementById('resultado-neto-etiqueta');

        const limpiarResultados = () => {
            [facturaResultado, solesResultado, dolaresResultado, netoResultado].forEach(elemento => { elemento.textContent = '—'; });
            netoEtiqueta.textContent = 'Neto por pagar';
            mensaje.textContent = '';
            mensaje.classList.remove('detraccion-error');
        };
        const error = texto => {
            mensaje.textContent = texto;
            mensaje.classList.add('detraccion-error');
        };
        const unidades = (valor, decimales, enterosMaximos) => {
            const partes = /^(\d+)(?:\.(\d+))?$/.exec(valor);
            if (!partes || partes[1].length > enterosMaximos || (partes[2] || '').length > decimales) return null;
            return BigInt(partes[1]) * (10n ** BigInt(decimales)) + BigInt((partes[2] || '').padEnd(decimales, '0'));
        };
        const dividirRedondeado = (dividendo, divisor) => (dividendo + divisor / 2n) / divisor;
        const mostrarImporte = (centimos, simbolo) => {
            const negativo = centimos < 0n;
            const valor = negativo ? -centimos : centimos;
            const entero = (valor / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return (negativo ? '- ' : '') + simbolo + ' ' + entero + '.' + (valor % 100n).toString().padStart(2, '0');
        };

        formulario.addEventListener('input', limpiarResultados);
        formulario.addEventListener('change', limpiarResultados);
        formulario.addEventListener('reset', limpiarResultados);
        formulario.addEventListener('submit', evento => {
            evento.preventDefault();
            limpiarResultados();

            const importeCentimos = unidades(importe.value, 2, 10);
            if (importeCentimos === null) {
                error('Ingresa un importe de factura válido, con hasta dos decimales.');
                return;
            }
            if (!base.value) {
                error('Selecciona la base de cálculo.');
                return;
            }
            const valorReferencial = referencial.value === '' ? null : unidades(referencial.value, 2, 10);
            if ((referencial.value !== '' && valorReferencial === null) || (base.value === 'referencial' && valorReferencial === null)) {
                error('Ingresa un valor referencial válido en soles, con hasta dos decimales.');
                return;
            }
            const cambio = tipoCambio.value === '' ? null : unidades(tipoCambio.value, 3, 4);
            if ((tipoCambio.value !== '' && (cambio === null || cambio === 0n)) || (moneda.value === 'USD' && cambio === null)) {
                error('Ingresa un tipo de cambio mayor que cero, con hasta tres decimales.');
                return;
            }
            if (!chofer.value) {
                error('Indica si el servicio incluye chofer adicional.');
                return;
            }
            if (chofer.value === 'si') {
                error('El cálculo con chofer adicional está pendiente de confirmación por la contadora.');
                return;
            }
            if (!redondeo.value) {
                error('Selecciona cómo redondear la detracción en soles.');
                return;
            }

            const facturaSoles = moneda.value === 'USD' ? dividirRedondeado(importeCentimos * cambio, 1000n) : importeCentimos;
            const baseSoles = base.value === 'referencial' ? valorReferencial : facturaSoles;
            const aplica = baseSoles >= 40000n;
            const detraccionSoles = !aplica ? 0n : redondeo.value === 'entero'
                ? dividirRedondeado(baseSoles * 4n, 10000n) * 100n
                : dividirRedondeado(baseSoles * 4n, 100n);
            const detraccionDolares = cambio === null ? null : dividirRedondeado(detraccionSoles * 1000n, cambio);
            const neto = moneda.value === 'USD' ? importeCentimos - detraccionDolares : importeCentimos - detraccionSoles;

            facturaResultado.textContent = mostrarImporte(facturaSoles, 'S/');
            solesResultado.textContent = mostrarImporte(detraccionSoles, 'S/');
            dolaresResultado.textContent = detraccionDolares === null ? 'Ingresa tipo de cambio' : mostrarImporte(detraccionDolares, 'US$');
            netoEtiqueta.textContent = moneda.value === 'USD' ? 'Neto por pagar (US$)' : 'Neto por pagar (S/)';
            netoResultado.textContent = mostrarImporte(neto, moneda.value === 'USD' ? 'US$' : 'S/');
            if (neto < 0n) {
                error('La detracción supera el importe de la factura. Verifica la base seleccionada con la contadora.');
            } else if (baseSoles === 40000n) {
                error('Se aplicó el 4 % a S/ 400 exactos según la regla indicada. La SUNAT señala un importe mayor a S/ 400; confirma antes de realizar el depósito.');
            } else {
                mensaje.textContent = aplica ? 'Se aplicó el 4 % sobre la base seleccionada.' : 'La base seleccionada es menor a S/ 400; no se aplicó detracción.';
            }
        });
        document.getElementById('detraccion-calcular').disabled = false;
    })();
</script>

<?php require __DIR__ . '/layout_fin.php'; ?>
