<?php
$paginaActiva = 'manuales';
$tituloPagina = 'Manuales SBC Transport';
require __DIR__ . '/auth_manuales.php';
require __DIR__ . '/layout_inicio.php';

$clave = $_GET['m'] ?? array_key_first($manuales);
if (!isset($manuales[$clave])) {
    $clave = array_key_first($manuales);
}

if ($clave === null): ?>
<h1>Manuales</h1>
<p class="subtitulo">Aún no hay manuales disponibles.</p>
<?php
    require __DIR__ . '/layout_fin.php';
    exit;
endif;

[$titulo, $descripcion] = $manuales[$clave];
?>

<h1 id="manual-title"><?= htmlspecialchars($titulo) ?></h1>
<p id="manual-description" class="subtitulo"><?= htmlspecialchars($descripcion) ?></p>

<div class="pdf-reader" data-pdf="ver_manual.php?f=<?= urlencode($clave) ?>" aria-label="Documento PDF">
    <p class="pdf-reader-status" role="status">Cargando documento...</p>
</div>

<script src="../pdfjs/pdf.min.js"></script>
<script src="../JS/pdf-reader.js?v=<?= filemtime(dirname(__DIR__) . '/JS/pdf-reader.js') ?>"></script>
<script>
    const links = document.querySelectorAll(".sidebar a[data-pdf]");
    const reader = document.querySelector(".pdf-reader");

    links.forEach((link) => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
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
