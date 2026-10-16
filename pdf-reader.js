pdfjsLib.GlobalWorkerOptions.workerSrc = "../pdfjs/pdf.worker.min.js";

window.renderPdfReader = async (reader) => {
  const generation = (reader.renderGeneration || 0) + 1;
  reader.renderGeneration = generation;
  reader.querySelectorAll(".pdf-reader-page").forEach((page) => page.remove());
  let status = reader.querySelector(".pdf-reader-status");
  if (!status) {
    status = document.createElement("p");
    status.className = "pdf-reader-status";
    status.setAttribute("role", "status");
    reader.prepend(status);
  }

  status.textContent = "Cargando documento...";

  try {
    const pdf = await pdfjsLib.getDocument(reader.dataset.pdf).promise;
    if (reader.renderGeneration !== generation) return;

    status.textContent = `Cargando 1 de ${pdf.numPages} páginas...`;

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
      status.textContent = `Cargando ${pageNumber} de ${pdf.numPages} páginas...`;
      const page = await pdf.getPage(pageNumber);
      if (reader.renderGeneration !== generation) return;

      const baseViewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.min(reader.clientWidth, 900);
      const scale = availableWidth / baseViewport.width;
      const viewport = page.getViewport({ scale });
      const pageContainer = document.createElement("figure");
      const canvas = document.createElement("canvas");
      const context = canvas.getContext("2d");

      if (!context) {
        throw new Error("No se pudo preparar el área de lectura del PDF.");
      }

      pageContainer.className = "pdf-reader-page";
      pageContainer.setAttribute("aria-label", `Página ${pageNumber}`);
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      pageContainer.append(canvas);
      reader.append(pageContainer);

      await page.render({ canvasContext: context, viewport }).promise;
      if (reader.renderGeneration !== generation) {
        pageContainer.remove();
        return;
      }
    }

    status.remove();
  } catch (error) {
    if (reader.renderGeneration !== generation) return;

    console.error("No se pudo cargar el documento PDF.", error);
    status.textContent =
      "No se pudo cargar el documento. Comprueba que el archivo exista y vuelve a intentarlo.";
  }
};

document
  .querySelectorAll(".pdf-reader[data-pdf]")
  .forEach((reader) => window.renderPdfReader(reader));
