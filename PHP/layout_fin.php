    <?php /* Cierre del layout: termina el contenido principal, el contenedor y el documento HTML */ ?>
    </main>
</div>
<script>
    (() => {
        const boton = document.getElementById("toggle-sidebar");
        if (!boton) return;
        const mediaMovil = window.matchMedia("(max-width: 800px)");
        const actualizar = (colapsado) => {
            document.documentElement.classList.toggle("sidebar-colapsado", colapsado);
            boton.textContent = colapsado ? "›" : "‹";
            boton.setAttribute("aria-expanded", String(!colapsado));
            boton.setAttribute("aria-label", colapsado ? "Expandir menú" : "Contraer menú");
            boton.title = colapsado ? "Expandir menú" : "Contraer menú";
        };
        const actualizarMovil = (abierto) => {
            document.documentElement.classList.toggle("sidebar-movil-abierto", abierto);
            boton.setAttribute("aria-expanded", String(abierto));
            boton.setAttribute("aria-label", abierto ? "Cerrar menú" : "Abrir menú");
            boton.title = abierto ? "Cerrar menú" : "Abrir menú";
        };
        if (mediaMovil.matches) {
            actualizarMovil(false);
        } else {
            actualizar(document.documentElement.classList.contains("sidebar-colapsado"));
        }
        boton.addEventListener("click", () => {
            if (mediaMovil.matches) {
                actualizarMovil(!document.documentElement.classList.contains("sidebar-movil-abierto"));
                return;
            }
            const colapsado = !document.documentElement.classList.contains("sidebar-colapsado");
            actualizar(colapsado);
            try {
                localStorage.setItem("sidebarColapsado", colapsado ? "1" : "0");
            } catch (e) {}
        });
        mediaMovil.addEventListener("change", (evento) => {
            document.documentElement.classList.remove("sidebar-movil-abierto");
            if (evento.matches) {
                actualizarMovil(false);
            } else {
                actualizar(document.documentElement.classList.contains("sidebar-colapsado"));
            }
        });
    })();
</script>
</body>
</html>
