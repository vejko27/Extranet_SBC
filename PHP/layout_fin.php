    <?php /* Cierre del layout: termina el contenido principal, el contenedor y el documento HTML */ ?>
    </main>
</div>
<script>
    (() => {
        const boton = document.getElementById("toggle-sidebar");
        if (!boton) return;
        const actualizar = (colapsado) => {
            document.documentElement.classList.toggle("sidebar-colapsado", colapsado);
            boton.textContent = colapsado ? "›" : "‹";
            boton.setAttribute("aria-expanded", String(!colapsado));
            boton.setAttribute("aria-label", colapsado ? "Expandir menú" : "Contraer menú");
            boton.title = colapsado ? "Expandir menú" : "Contraer menú";
        };
        actualizar(document.documentElement.classList.contains("sidebar-colapsado"));
        boton.addEventListener("click", () => {
            const colapsado = !document.documentElement.classList.contains("sidebar-colapsado");
            actualizar(colapsado);
            try {
                localStorage.setItem("sidebarColapsado", colapsado ? "1" : "0");
            } catch (e) {}
        });
    })();
</script>
</body>
</html>
