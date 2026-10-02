@extends('layouts.admin')
@section('title', 'Editar Alumno')


@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        /* Mismo acabado que el buscador de la pantalla de horarios */
        .select2-container--bootstrap-5 .select2-selection {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 0.5rem !important;
            padding: 0.4rem 0.75rem;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #009B4D !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 155, 77, 0.15) !important;
            background-color: #ffffff !important;
        }

        /* El desplegable va por encima para que el grupo de entrada no lo tape */
        .select2-container {
            z-index: 5;
        }

        /* Select2 dentro de un input-group.
           Select2 esconde el <select> original y pone en su lugar un contenedor
           propio. Ese contenedor no sabe que vive dentro de un input-group, así
           que ocupaba el ancho completo y empujaba el icono al renglón de arriba.
           Con esto vuelve a comportarse como un control más de la misma fila. */
        .input-group > .select2-container {
            flex: 1 1 auto;
            width: 1% !important;
        }

        .input-group > .select2-container .select2-selection {
            height: 100%;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
    </style>
@endpush

@section('content')

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-2 border-marca-green">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-marca-green me-2"></i> Editar Alumno</h3>
            <p class="text-muted small mb-0 mt-1">Modificando la información de <strong>{{ $alumno->name }}</strong>.</p>
        </div>
        <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancelar
        </a>
    </div>

    {{-- Formulario --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('alumnos.update', $alumno->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    {{-- SECCIÓN: DATOS PERSONALES --}}
                    <div class="col-12 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-person-vcard me-2"></i>Datos Personales
                        </h6>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nombre(s) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light @error('nombres') is-invalid @enderror"
                            id="nombres" name="nombres" required value="{{ old('nombres', $alumno->name) }}">
                        @error('nombres')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Apellido Paterno <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light @error('apellido_paterno') is-invalid @enderror"
                            id="apellido_paterno" name="apellido_paterno" required
                            value="{{ old('apellido_paterno', $alumno->apellido_paterno) }}">
                        @error('apellido_paterno')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Apellido Materno <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light @error('apellido_materno') is-invalid @enderror"
                            id="apellido_materno" name="apellido_materno" required
                            value="{{ old('apellido_materno', $alumno->apellido_materno) }}">
                        @error('apellido_materno')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- SECCIÓN: DATOS ACADÉMICOS Y ACCESO --}}
                    <div class="col-12 mt-5 mb-2">
                        <h6 class="fw-bold text-marca-green text-uppercase mb-0 border-bottom pb-2">
                            <i class="bi bi-backpack me-2"></i>Datos Académicos y Acceso
                        </h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Matrícula (N° Control) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-123"></i></span>
                            <input type="text"
                                class="form-control bg-light font-monospace fs-6 @error('matricula') is-invalid @enderror"
                                id="matricula" name="matricula" required value="{{ old('matricula', $alumno->matricula) }}">
                        </div>
                        @error('matricula')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Carrera</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                            {{-- Buscador sobre las carreras ya registradas. Si hiciera falta una
                                 nueva, se escribe y el propio buscador la ofrece para agregarla. --}}
                            @php($carreraActual = old('carrera', $alumno->carrera))
                            <select class="form-select select-carrera @error('carrera') is-invalid @enderror"
                                id="carrera" name="carrera" data-placeholder="Busca o escribe la carrera"
                                style="flex: 1 1 auto; width: 1%;">
                                <option value=""></option>
                                @foreach ($carreras as $nombreCarrera)
                                    <option value="{{ $nombreCarrera }}"
                                        {{ $carreraActual == $nombreCarrera ? 'selected' : '' }}>
                                        {{ $nombreCarrera }}
                                    </option>
                                @endforeach
                                {{-- La carrera que ya tenía el alumno, por si no está en la lista --}}
                                @if ($carreraActual && !$carreras->contains($carreraActual))
                                    <option value="{{ $carreraActual }}" selected>{{ $carreraActual }}</option>
                                @endif
                            </select>
                        </div>
                        @error('carrera')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                            <input type="email"
                                class="form-control bg-light text-lowercase @error('email') is-invalid @enderror"
                                id="email" name="email" required value="{{ old('email', $alumno->email) }}">
                        </div>
                        @error('email')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nueva Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control bg-light @error('password') is-invalid @enderror"
                                id="password" name="password" placeholder="Dejar en blanco para mantener la actual">
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle text-marca-green"></i> Escribe algo aquí solo si deseas cambiar la
                            contraseña del alumno.</small>
                        @error('password')
                            <br><span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                    <button type="submit"
                        class="btn btn-marca-yellow rounded-pill shadow-sm px-5 py-2 fw-bold fs-5 text-marca-black">
                        <i class="bi bi-arrow-repeat me-2"></i> Actualizar Alumno
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {

            // Buscador sobre las carreras que ya existen en el sistema.
            //
            // tags:true deja además escribir una nueva: si hace falta una carrera
            // que todavía no está en la lista, se teclea y se guarda igual.
            $('.select-carrera').select2({
                theme: 'bootstrap-5',
                width: '100%',
                tags: true,
                placeholder: $('.select-carrera').data('placeholder'),
                language: {
                    noResults: function() {
                        return 'Sin coincidencias: escríbela para agregarla';
                    }
                },
                createTag: function(params) {
                    var texto = $.trim(params.term).toUpperCase();

                    if (texto === '') {
                        return null;
                    }

                    // Si ya existe una igual, no se ofrece crearla otra vez.
                    var yaExiste = false;
                    $(this.$element).find('option').each(function() {
                        if ($(this).val().toUpperCase() === texto) {
                            yaExiste = true;
                        }
                    });
                    if (yaExiste) {
                        return null;
                    }

                    // Se guarda en mayúsculas, como el resto del padrón.
                    return {
                        id: texto,
                        text: texto + '   (agregar como nueva)',
                        nuevo: true
                    };
                },
                insertTag: function(datos, etiqueta) {
                    // La opción de crear una nueva va AL FINAL, después de las que ya
                    // existen. Si fuera la primera, pulsar Enter deprisa daría de alta
                    // un duplicado mal escrito en vez de elegir la que ya estaba.
                    datos.push(etiqueta);
                }
            });

            // Al abrir, el cursor va directo al campo de búsqueda.
            $(document).on('select2:open', function() {
                var campo = document.querySelector('.select2-search__field');
                if (campo) { campo.focus(); }
            });
        });
    </script>
@endpush
