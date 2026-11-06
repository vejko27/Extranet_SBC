<?php
// Catálogo y visor de manuales PDF.
$esFormatos = defined('MODULO_FORMATOS') && MODULO_FORMATOS;
$paginaActiva = $esFormatos ? 'formatos_procedimientos' : 'manuales';
$tituloPagina = $esFormatos ? 'Formatos de Procedimientos internos' : 'Manuales SBC Transport';
$urlCatalogo = $esFormatos ? 'formatos_procedimientos.php' : 'manuales.php';
$urlPdf = $esFormatos ? 'ver_formato_procedimiento.php' : 'ver_manual.php';
$nombrePlural = $esFormatos ? 'formatos' : 'procedimientos';
// Exige permiso de ver manuales y carga el layout (define $manuales)
if ($esFormatos) {
    require_once __DIR__ . '/auth.php';
    exigirPermiso('Formatos de Procedimientos internos', 'ver');
} else {
    require __DIR__ . '/auth_manuales.php';
}
require __DIR__ . '/layout_inicio.php';
if ($esFormatos) $manuales = $formatos;

// Sin un manual seleccionado, mostrar el catálogo antes de abrir un PDF.
if (!isset($_GET['m'])):
    $buscar = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
    $coincidencias = 0;
?>
<div class="cabecera-marca manuales-cabecera">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1><?= $esFormatos ? 'Formatos de Procedimientos internos' : 'Manuales' ?></h1>
        <p class="subtitulo">Busca y consulta los <?= $nombrePlural ?> disponibles en PDF.</p>
    </div>
</div>

<div class="card">
    <form method="get" class="manuales-buscar" role="search">
        <label for="buscar-procedimientos">Buscar <?= $nombrePlural ?> por título o descripción</label>
        <div class="manuales-buscar-controles">
            <input id="buscar-procedimientos" type="search" name="q" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Escribe un título o una descripción">
            <button type="submit">Buscar</button>
        </div>
    </form>
</div>

<section class="card" aria-labelledby="procedimientos-titulo">
    <h2 id="procedimientos-titulo"><?= $esFormatos ? 'Formatos disponibles' : 'Procedimientos disponibles' ?></h2>
    <?php if (!$manuales): ?>
        <p>Aún no hay <?= $nombrePlural ?> disponibles.</p>
    <?php else: ?>
        <div class="manuales-lista">
            <?php foreach ($manuales as $id => [$tituloManual, $descripcionManual]):
                $tituloManual = (string)$tituloManual;
                $descripcionManual = (string)$descripcionManual;
                $visible = $buscar === '' || mb_stripos($tituloManual, $buscar, 0, 'UTF-8') !== false
                    || mb_stripos($descripcionManual, $buscar, 0, 'UTF-8') !== false;
                if ($visible) $coincidencias++;
            ?>
                <article class="manuales-item<?= $esFormatos ? ' manuales-item-formato' : '' ?>"<?= $visible ? '' : ' hidden' ?>>
                    <div>
                        <?php if ($esFormatos): ?><span class="manuales-campo-titulo">Nombre de Formato</span><?php endif; ?>
                        <h3><a href="<?= $urlCatalogo ?>?m=<?= urlencode($id) ?>"><?= htmlspecialchars($tituloManual, ENT_QUOTES, 'UTF-8') ?></a></h3>
                        <?php if (!$esFormatos): ?><p><?= htmlspecialchars($descripcionManual, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    </div>
                    <?php if ($esFormatos): ?>
                        <div>
                            <span class="manuales-campo-titulo">Descripción</span>
                            <p><?= htmlspecialchars($descripcionManual, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <a class="btn manuales-descargar" href="<?= htmlspecialchars($urlPdf, ENT_QUOTES, 'UTF-8') ?>?f=<?= urlencode($id) ?>&amp;descargar=1" aria-label="Descargar PDF: <?= htmlspecialchars($tituloManual, ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <p id="manuales-sin-resultados" role="status"<?= $coincidencias ? ' hidden' : '' ?>>No se encontraron <?= $nombrePlural ?>.</p>
    <?php endif; ?>
</section>

<script>
    (() => {
        const input = document.getElementById("buscar-procedimientos");
        const items = document.querySelectorAll(".manuales-item");
        const sinResultados = document.getElementById("manuales-sin-resultados");
        if (!sinResultados) return;
        const filtrar = () => {
            const busqueda = input.value.trim().toLocaleLowerCase();
            let encontrados = 0;
            items.forEach((item) => {
                const titulo = item.querySelector("h3").textContent.toLocaleLowerCase();
                const descripcion = item.querySelector("p").textContent.toLocaleLowerCase();
                const visible = titulo.includes(busqueda) || descripcion.includes(busqueda);
                item.hidden = !visible;
                if (visible) encontrados++;
            });
            sinResultados.hidden = encontrados > 0;
        };
        input.addEventListener("input", filtrar);
        input.form.addEventListener("submit", (event) => {
            event.preventDefault();
            filtrar();
        });
        filtrar();
    })();
</script>
<?php
    require __DIR__ . '/layout_fin.php';
    exit;
endif;

// Conserva el comportamiento anterior para enlaces a manuales inválidos.
$clave = is_string($_GET['m']) && isset($manuales[$_GET['m']]) ? $_GET['m'] : array_key_first($manuales);
if ($clave === null) {
    echo '<div class="card"><p>Aún no hay ' . $nombrePlural . ' disponibles.</p><p><a href="' . $urlCatalogo . '">Volver a ' . $nombrePlural . '</a></p></div>';
    require __DIR__ . '/layout_fin.php';
    exit;
}

// Título y descripción del manual seleccionado
[$titulo, $descripcion] = $manuales[$clave];
?>

<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1 id="manual-title"><?= htmlspecialchars($titulo) ?></h1>
        <p id="manual-description" class="subtitulo"><?= htmlspecialchars($descripcion) ?></p>
    </div>
</div>

<p class="manuales-volver"><a href="<?= $urlCatalogo ?>">← Volver a <?= $nombrePlural ?></a></p>

<!-- Contenedor donde pdf-reader.js dibuja el PDF servido por ver_manual.php -->
<div class="pdf-reader" data-pdf="<?= $urlPdf ?>?f=<?= urlencode($clave) ?>" aria-label="Documento PDF">
    <p class="pdf-reader-status" role="status">Cargando documento...</p>
</div>

<script src="../pdfjs/pdf.min.js"></script>
<script src="../JS/pdf-reader.js?v=<?= filemtime(dirname(__DIR__) . '/JS/pdf-reader.js') ?>"></script>
<script>
    // Al elegir otro manual en el menú lateral, se cambia sin recargar la página
    const links = document.querySelectorAll('.sidebar a[data-modulo="<?= $esFormatos ? 'formatos' : 'manuales' ?>"]');
    const reader = document.querySelector(".pdf-reader");

    links.forEach((link) => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            // Actualiza título, descripción, enlace activo y URL, y vuelve a renderizar el PDF
            document.getElementById("manual-title").textContent = link.dataset.title;
            document.getElementById("manual-description").textContent = link.dataset.description;
            reader.dataset.pdf = link.dataset.pdf;
            links.forEach((l) => l.classList.toggle("activo", l === link));
            history.replaceState(null, "", link.getAttribute("href"));
            window.renderPdfReader(reader);
        });
    });
</script>

<?php require __DIR__ . '/layout_fin.php'; ?>
