<?php
// Visor de manuales PDF: muestra el manual elegido y permite cambiar entre ellos desde el menú.
$paginaActiva = 'manuales';
$tituloPagina = 'Manuales SBC Transport';
// Exige permiso de ver manuales y carga el layout (define $manuales)
require __DIR__ . '/auth_manuales.php';
require __DIR__ . '/layout_inicio.php';

// Manual a mostrar: el indicado en ?m= o, si no es válido, el primero
$clave = $_GET['m'] ?? array_key_first($manuales);
if (!isset($manuales[$clave])) {
    $clave = array_key_first($manuales);
}

if ($clave === null): ?>
<div class="cabecera-marca">
    <img class="logo-marca" src="../logo/SBC_TRANSPORT.jpg" alt="SMART Transporte de Carga Pesada">
    <div>
        <h1>Manuales</h1>
        <p class="subtitulo">Aún no hay manuales disponibles.</p>
    </div>
</div>
<?php /* Sin manuales: muestra aviso y termina la página */ ?>
<?php
    require __DIR__ . '/layout_fin.php';
    exit;
endif;

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

<!-- Contenedor donde pdf-reader.js dibuja el PDF servido por ver_manual.php -->
<div class="pdf-reader" data-pdf="ver_manual.php?f=<?= urlencode($clave) ?>" aria-label="Documento PDF">
    <p class="pdf-reader-status" role="status">Cargando documento...</p>
</div>

<script src="../pdfjs/pdf.min.js"></script>
<script src="../JS/pdf-reader.js?v=<?= filemtime(dirname(__DIR__) . '/JS/pdf-reader.js') ?>"></script>
<script>
    // Al elegir otro manual en el menú lateral, se cambia sin recargar la página
    const links = document.querySelectorAll(".sidebar a[data-pdf]");
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
