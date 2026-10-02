@extends('layouts.admin')

@section('title', 'Revisar importación de horarios')

@push('styles')
    {{-- Buscador en los menús de materias y docentes, como en el resto del sistema --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .select2-container--bootstrap-5 .select2-selection {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 0.5rem !important;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #009B4D !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 155, 77, 0.15) !important;
            background-color: #ffffff !important;
        }

        /* Los que faltan por elegir se marcan en rojo, igual que sin buscador */
        .selector-equivalencia.sin-elegir .select2-selection {
            border-color: #dc3545 !important;
        }

        /* Que el buscador ocupe el espacio junto a la flecha */
        .selector-equivalencia > .select2-container {
            flex: 1 1 auto;
            width: 1% !important;
        }
    </style>
@endpush

@section('content')
    <div>

        {{-- El título ya lo pone el diseño general; aquí sólo el aviso y la salida --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <p class="text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Nada se ha guardado todavía. Esto es lo que pasaría en el semestre
                <strong>{{ $semestre->nombre }}</strong>.
            </p>
            <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                <i class="bi bi-x-lg me-1"></i> Cancelar
            </a>
        </div>

        @php
            $porRevisar = array_merge(
                array_values($plan['areasPendientes']),
                array_values($plan['materiasPendientes']),
                array_values($plan['docentesPendientes']),
            );
            $pendientes = count($porRevisar);
            $sinElegir = count(array_filter($porRevisar, fn($d) => empty($d['sugerido'])));
        @endphp

        {{-- RESUMEN --}}
        <div class="row g-3 mb-4">
            @foreach ([['Clases nuevas', $plan['resumen']['nuevas'], 'bi-plus-circle', 'var(--marca-green)'], ['Cambian de horario', $plan['resumen']['cambian'], 'bi-arrow-left-right', '#b58100'], ['Se quedan igual', $plan['resumen']['iguales'], 'bi-check2', '#6c757d'], ['No se importarán', $plan['resumen']['pendientes'], 'bi-dash-circle', '#c62828']] as [$titulo, $valor, $icono, $color])
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body py-3">
                            <div class="text-muted text-uppercase fw-bold" style="font-size: .7rem;">{{ $titulo }}</div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <i class="bi {{ $icono }}" style="color: {{ $color }}; font-size: 1.3rem;"></i>
                                <span class="fw-bold" style="font-size: 1.6rem;">{{ $valor }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <form action="{{ route('horarios.importar.confirmar') }}" method="POST" id="form-importacion">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            {{-- 1. LO QUE NO SE PUDO RECONOCER --}}
            @if ($pendientes > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-4"
                    style="border-left: 5px solid var(--marca-yellow) !important;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-question-circle me-2" style="color: #b58100;"></i>
                            Revisa a qué corresponde cada dato del archivo
                            @if ($sinElegir > 0)
                                <span class="badge rounded-pill ms-1"
                                    style="background-color: #fdecea; color: #c62828; font-size: .75rem;">
                                    {{ $sinElegir }} sin elegir
                                </span>
                            @endif
                        </h5>
                        <p class="text-muted small mb-4">
                            El archivo usa claves y abreviaturas. Lo que ya aparece elegido es una propuesta y
                            <strong>se usará tal cual</strong>: cámbiala si no es correcta. Si cambias algo, pulsa
                            <strong>Actualizar revisión</strong> para ver los números al día. Lo que elijas queda
                            guardado para la próxima vez; lo que se quede en <em>No importar</em> no se registra.
                        </p>

                        @foreach ([['area', 'Laboratorios', $plan['areasPendientes'], $centros, 'bi-building'], ['materia', 'Materias', $plan['materiasPendientes'], $materias, 'bi-journal-bookmark'], ['docente', 'Docentes', $plan['docentesPendientes'], $docentes, 'bi-person-badge']] as [$tipo, $titulo, $lista, $opciones, $icono])
                            @if (count($lista) > 0)
                                <h6 class="fw-bold text-marca-green text-uppercase small mb-2">
                                    <i class="bi {{ $icono }} me-1"></i> {{ $titulo }}
                                </h6>
                                <div class="mb-4">
                                    @foreach ($lista as $clave => $dato)
                                        <div class="row g-2 align-items-center py-2 border-bottom">
                                            {{-- Lo que dice el archivo, completo --}}
                                            <div class="col-12 col-md-5">
                                                <div class="fw-bold text-dark small" style="word-break: break-word;">
                                                    {{ $dato['texto'] }}
                                                </div>
                                                @if (!empty($dato['motivo']))
                                                    <div class="text-muted" style="font-size: .75rem;">
                                                        {{ $dato['motivo'] }}
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- A qué corresponde en el sistema --}}
                                            <div class="col-12 col-md-7">
                                                <div
                                                    class="d-flex align-items-center gap-2 selector-equivalencia {{ empty($dato['sugerido']) ? 'sin-elegir' : '' }}">
                                                    <i class="bi bi-arrow-right text-muted d-none d-md-inline"></i>
                                                    {{-- Laboratorios son pocos: menú normal. Materias y docentes, con buscador. --}}
                                                    <select name="{{ $tipo }}[{{ $clave }}]"
                                                        class="form-select form-select-sm {{ $tipo !== 'area' ? 'select-search' : '' }} {{ empty($dato['sugerido']) ? 'border-danger' : '' }}">
                                                        <option value="">— No importar —</option>
                                                        @foreach ($opciones as $id => $nombre)
                                                            <option value="{{ $id }}"
                                                                {{ (string) ($dato['sugerido'] ?? '') === (string) $id ? 'selected' : '' }}>
                                                                {{ $nombre }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                @if (empty($dato['sugerido']) && !empty($dato['pista']) && isset($opciones[$dato['pista']]))
                                                    <div class="small mt-1" style="color: #8a6d00;">
                                                        <i class="bi bi-lightbulb me-1"></i>¿Quizá
                                                        <strong>{{ $opciones[$dato['pista']] }}</strong>? Revísalo antes de
                                                        elegirlo.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach

                        <button type="submit" form="form-revision"
                            class="btn btn-outline-success rounded-pill px-4 fw-bold">
                            <i class="bi bi-arrow-clockwise me-1"></i> Actualizar revisión
                        </button>
                    </div>
                </div>
            @endif

            {{-- 2. AVISOS --}}
            @if (count($plan['empalmes']) || count($plan['empalmesSistema']) || count($problemas) || count($plan['gruposNuevos']))
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>
                            Avisos</h5>

                        @if (count($plan['gruposNuevos']))
                            <p class="small mb-2">
                                <strong>Se crearán {{ count($plan['gruposNuevos']) }} grupos</strong> que no existían:
                                <span class="text-muted">{{ implode(' · ', array_slice($plan['gruposNuevos'], 0, 8)) }}
                                    @if (count($plan['gruposNuevos']) > 8)
                                        y {{ count($plan['gruposNuevos']) - 8 }} más
                                    @endif
                                </span>
                            </p>
                        @endif

                        @foreach ([['Se encima dentro del propio archivo', $plan['empalmes']], ['Se encima con algo que ya está en el sistema', $plan['empalmesSistema']], ['Filas que no se entendieron', array_map(fn($p) => 'Hoja ' . $p['hoja'] . ', fila ' . $p['fila'] . ': ' . $p['detalle'], $problemas)]] as [$titulo, $lista])
                            @if (count($lista))
                                <div class="mt-3">
                                    <span class="fw-bold small text-dark">{{ $titulo }}
                                        ({{ count($lista) }})</span>
                                    <ul class="small text-muted mb-0 mt-1 ps-3">
                                        @foreach (array_slice($lista, 0, 10) as $aviso)
                                            <li>{{ $aviso }}</li>
                                        @endforeach
                                        @if (count($lista) > 10)
                                            <li>… y {{ count($lista) - 10 }} más</li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 3. CLASES QUE YA NO APARECEN EN EL ARCHIVO --}}
            @if (count($plan['sobrantes']))
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-inbox me-2 text-muted"></i>
                            {{ count($plan['sobrantes']) }}
                            {{ count($plan['sobrantes']) == 1 ? 'clase del sistema no aparece' : 'clases del sistema no aparecen' }}
                            en el archivo
                        </h5>
                        <p class="text-muted small mb-3">
                            Pueden ser clases que capturaste a mano o que cambiaron de día. <strong>No se tocan</strong>
                            a menos que marques la casilla; y si la marcas, van a la papelera, de donde se pueden
                            restaurar.
                        </p>

                        <ul class="small text-muted mb-3 ps-3">
                            @foreach (array_slice($plan['sobrantes'], 0, 15) as $sobrante)
                                <li>{{ $sobrante['descripcion'] }}</li>
                            @endforeach
                            @if (count($plan['sobrantes']) > 15)
                                <li>… y {{ count($plan['sobrantes']) - 15 }} más</li>
                            @endif
                        </ul>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" name="quitar_sobrantes"
                                id="quitar_sobrantes">
                            <label class="form-check-label small fw-bold" for="quitar_sobrantes">
                                Mandar esas {{ count($plan['sobrantes']) }} clases a la papelera
                            </label>
                        </div>
                    </div>
                </div>
            @endif

            {{-- 4. DETALLE DE LO QUE SE VA A GUARDAR --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-marca-green"></i>
                        Clases del archivo ({{ count($plan['clases']) }})</h5>
                </div>
                <div class="card-body p-4 pt-3">
                    <div class="table-responsive" style="max-height: 420px;">
                        <table class="table table-sm align-middle small">
                            <thead class="sticky-top bg-white">
                                <tr class="text-uppercase text-muted" style="font-size: .7rem;">
                                    <th>Estado</th>
                                    <th>Laboratorio</th>
                                    <th>Día</th>
                                    <th>Horario</th>
                                    <th>Materia</th>
                                    <th>Grupo</th>
                                    <th>Docente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan['clases'] as $clase)
                                    @php
                                        $estilos = [
                                            'nueva' => ['Nueva', 'bg-success'],
                                            'cambia' => ['Cambia', 'bg-warning text-dark'],
                                            'igual' => ['Igual', 'bg-light text-muted border'],
                                            'pendiente' => ['Falta un dato', 'bg-danger'],
                                        ];
                                        [$texto, $clase_css] = $estilos[$clase['estado']];
                                    @endphp
                                    <tr>
                                        <td><span class="badge {{ $clase_css }}">{{ $texto }}</span></td>
                                        <td>{{ $clase['centro_id'] ? $centros[$clase['centro_id']] ?? '' : $clase['area'] }}
                                        </td>
                                        <td>{{ $clase['dia'] }}</td>
                                        <td>{{ substr($clase['inicio'], 0, 5) }} – {{ substr($clase['fin'], 0, 5) }}</td>
                                        <td>{{ $clase['materia_id'] ? $materias[$clase['materia_id']] ?? '' : $clase['materia'] }}
                                        </td>
                                        <td>{{ $clase['grupo'] }}</td>
                                        <td>{{ $clase['docente_id'] ? $docentes[$clase['docente_id']] ?? '' : $clase['docente'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- CONFIRMAR --}}
            <div class="d-flex flex-wrap justify-content-end gap-2 pb-4">
                <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-marca-green rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Guardar estos cambios
                </button>
            </div>
        </form>

        {{-- Formulario gemelo: vuelve a revisar con las equivalencias elegidas arriba --}}
        <form action="{{ route('horarios.importar.revisar') }}" method="POST" id="form-revision" class="d-none">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
        </form>

        <script>
            // El botón "Actualizar revisión" manda las mismas selecciones al otro formulario.
            // Con el buscador activo esto sigue valiendo: el menú original conserva el valor.
            // "No importar" viaja como 0: también es una decisión, y sin ella se volvería
            // a aplicar la propuesta automática.
            document.getElementById('form-revision')?.addEventListener('submit', function(evento) {
                document.querySelectorAll('#form-importacion select').forEach(function(campo) {
                    const copia = document.createElement('input');
                    copia.type = 'hidden';
                    copia.name = campo.name;
                    copia.value = campo.value || '0';
                    evento.target.appendChild(copia);
                });
            });
        </script>
    </div>
@endsection

@push('scripts')
    {{-- jQuery y Select2, los mismos que usan las demás pantallas con buscador --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                language: {
                    noResults: function() {
                        return "No se encontraron resultados";
                    }
                }
            });

            // Al abrir, el cursor queda listo para escribir.
            $(document).on('select2:open', () => {
                document.querySelector('.select2-search__field').focus();
            });

            // En cuanto se elige algo, deja de marcarse en rojo; si se vuelve a
            // "No importar", se marca otra vez.
            $('.selector-equivalencia select').on('change', function() {
                $(this).closest('.selector-equivalencia').toggleClass('sin-elegir', !this.value);
                $(this).toggleClass('border-danger', !this.value);
            });
        });
    </script>
@endpush
