{{--
    Buscador que filtra las filas de una tabla mientras se escribe, sin recargar la
    página. Para las tablas que ya traen todas sus filas, como las de Reportes: ahí
    recargar vuelve a calcular todas las pestañas, así que filtrar lo que ya está
    en pantalla es mucho más rápido.

    Busca igual que el resto del sistema (App\Support\Busqueda): por palabras
    sueltas y en cualquier orden, sin importar acentos ni mayúsculas, y si no
    encuentra nada perdona un error de dedo al final de las palabras largas.

    Cada fila que se pueda buscar lleva data-buscar="el texto en el que se busca".

    @include('partials.buscador-en-tabla', [
        'tabla'    => 'tablaDocentes',                 // id de la <table>
        'etiqueta' => 'Buscar docente',
        'ayuda'    => 'Nombre, usuario o materia...',
        'singular' => 'docente',                       // para "3 de 45 docentes"
        'plural'   => 'docentes',
    ])
--}}
<div class="buscador-en-tabla px-4 py-3 border-bottom bg-white" data-tabla="{{ $tabla }}"
    data-singular="{{ $singular }}" data-plural="{{ $plural }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-7 col-lg-6">
            <label class="form-label fw-bold text-muted small text-uppercase"
                for="buscar_{{ $tabla }}">{{ $etiqueta }}</label>
            <div class="d-flex gap-2">
                <div class="input-group shadow-sm rounded-3 overflow-hidden"
                    style="border: 1px solid #ced4da; flex-grow: 1;">
                    <span class="input-group-text bg-white border-0 text-marca-green">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="buscar_{{ $tabla }}" class="form-control border-0 search-input ps-0"
                        placeholder="{{ $ayuda }}" autocomplete="off">
                </div>
                <button type="button"
                    class="buscador-limpiar btn btn-outline-danger shadow-sm rounded-3 px-3 align-items-center d-none"
                    title="Limpiar búsqueda">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div class="col-md-5 col-lg-6 text-md-end">
            <small class="buscador-conteo text-muted"></small>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Minúsculas y sin acentos, para comparar igual que la base de datos
                const normalizar = (texto) => (texto || '').toString().toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '');

                document.querySelectorAll('.buscador-en-tabla').forEach(function(caja) {
                    const tabla = document.getElementById(caja.dataset.tabla);
                    const cuerpo = tabla ? tabla.tBodies[0] : null;
                    const filas = cuerpo ? Array.from(cuerpo.querySelectorAll('tr[data-buscar]')) : [];

                    // Sin filas no hay nada que buscar
                    if (filas.length === 0) {
                        caja.classList.add('d-none');
                        return;
                    }

                    const textos = filas.map((fila) => normalizar(fila.dataset.buscar));
                    const entrada = caja.querySelector('input');
                    const limpiar = caja.querySelector('.buscador-limpiar');
                    const conteo = caja.querySelector('.buscador-conteo');
                    const nombre = (cuantos) => cuantos === 1 ? caja.dataset.singular : caja.dataset.plural;

                    // La fila de "no se encontró nada", que sólo se ve cuando hace falta
                    const vacia = document.createElement('tr');
                    const celda = document.createElement('td');
                    celda.colSpan = tabla.tHead ? tabla.tHead.rows[0].cells.length : 1;
                    celda.className = 'text-center py-5 text-muted';
                    vacia.className = 'd-none';
                    vacia.appendChild(celda);
                    cuerpo.appendChild(vacia);

                    const coinciden = (palabras) => textos.map((texto) => palabras.every((p) => texto.includes(p)));

                    function filtrar() {
                        const palabras = normalizar(entrada.value).split(/\s+/).filter(Boolean);
                        let visibles = coinciden(palabras);
                        let parecidos = false;

                        // Segundo intento: sin la última letra de las palabras largas,
                        // para que "AMADOX" siga encontrando a "AMADOR".
                        if (palabras.length > 0 && !visibles.includes(true)) {
                            visibles = coinciden(palabras.map((p) => p.length >= 5 ? p.slice(0, -1) : p));
                            parecidos = visibles.includes(true);
                        }

                        let cuantos = 0;
                        filas.forEach(function(fila, i) {
                            fila.classList.toggle('d-none', !visibles[i]);
                            if (visibles[i]) cuantos++;
                        });

                        const buscando = palabras.length > 0;
                        limpiar.classList.toggle('d-none', !buscando);
                        limpiar.classList.toggle('d-flex', buscando);

                        vacia.classList.toggle('d-none', cuantos > 0);
                        if (cuantos === 0) {
                            celda.textContent = 'No hay resultados para «' + entrada.value.trim() + '».';
                        }

                        conteo.textContent = !buscando
                            ? filas.length + ' ' + nombre(filas.length)
                            : 'Mostrando ' + cuantos + ' de ' + filas.length + ' ' + nombre(filas.length)
                                + (parecidos ? ' (no hubo coincidencia exacta: se muestran los parecidos)' : '');
                    }

                    entrada.addEventListener('input', filtrar);
                    entrada.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            entrada.value = '';
                            filtrar();
                        }
                    });
                    limpiar.addEventListener('click', function() {
                        entrada.value = '';
                        filtrar();
                        entrada.focus();
                    });

                    filtrar();
                });
            });
        </script>
    @endpush
@endonce
