// Búsqueda local: no cambia ni envía registros a las bases.
document.querySelectorAll('[data-search-table]').forEach((buscador) => {
    const tabla = document.getElementById(buscador.dataset.searchTable);
    if (!tabla) return;
    const filas = [...tabla.querySelectorAll('[data-search-row]')];
    const vacio = tabla.querySelector('[data-empty-row]');
    const contador = tabla.closest('section').querySelector('[data-search-count]');
    const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    buscador.addEventListener('input', () => {
        const termino = normalizar(buscador.value.trim());
        let visibles = 0;
        filas.forEach((fila) => {
            fila.hidden = !normalizar(fila.textContent).includes(termino);
            if (!fila.hidden) visibles++;
        });
        if (vacio) vacio.hidden = visibles > 0 || filas.length === 0;
        if (contador) contador.textContent = `${visibles} de ${filas.length} tickets`;
    });
});
