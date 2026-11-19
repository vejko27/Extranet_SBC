(() => {
    const form = document.getElementById('formulario-muebles');
    if (form) {
        form.addEventListener('formdata', (event) => {
            const filas = {};
            for (const [nombre, valor] of Array.from(event.formData.entries())) {
                const campo = /^filas\[(\d+)\]\[(\d+)\]$/.exec(nombre);
                if (!campo) continue;
                if (!filas[campo[1]]) filas[campo[1]] = {};
                filas[campo[1]][campo[2]] = valor;
                event.formData.delete(nombre);
            }
            event.formData.set('filas_json', JSON.stringify(filas));
        });
    }

    const eliminar = document.querySelector('#formulario-borrar-mueble[data-seleccion-multiple]');
    if (eliminar) {
        eliminar.addEventListener('submit', (event) => {
            const seleccion = document.querySelectorAll('input[name="filas_borrar[]"]:checked');
            if (!seleccion.length) {
                event.preventDefault();
                alert('Selecciona al menos una fila para borrar.');
                return;
            }
            if (!confirm('¿Eliminar las filas seleccionadas? Esta acción no se puede deshacer.')) event.preventDefault();
        });
        eliminar.addEventListener('formdata', (event) => {
            const seleccion = event.formData.getAll('filas_borrar[]');
            event.formData.delete('filas_borrar[]');
            event.formData.set('filas_borrar_json', JSON.stringify(seleccion));
        });
    }
})();
