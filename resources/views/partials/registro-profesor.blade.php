{{--
    Registrar si el profesor dio su clase, desde "Gestión de Clases" (encargado) y
    "Clases de hoy" (administrador). Las dos pantallas tenían este mismo código
    copiado.

    Recibe: $urlClase (p. ej. url('encargado/clase')) y $fechaSeleccionada.
    Cada fila de la tabla lleva id="clase-{id}", data-estado (lo registrado) y
    data-registrados (cuántos alumnos se registraron ese día).
--}}
<form id="formAsistencia" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="estado" id="inputEstado">
    <input type="hidden" name="fecha" value="{{ $fechaSeleccionada }}">
    <input type="hidden" name="observaciones" id="inputObservaciones">
</form>

@push('scripts')
    {{-- SweetAlert ya lo carga el layout --}}
    <script>
        (function() {
            const urlClase = @json(rtrim($urlClase, '/'));
            const nombres = {
                asistio: 'Presente',
                falta: 'Falta',
                justificado: 'Justificado'
            };

            function fila(id) {
                return document.getElementById('clase-' + id);
            }

            function enviar(id, estado) {
                const form = document.getElementById('formAsistencia');
                form.action = urlClase + '/' + id + '/estado';
                document.getElementById('inputEstado').value = estado;
                // La nota que haya escrito para ESA clase
                document.getElementById('inputObservaciones').value = document.getElementById('input_comentario_' + id).value;
                form.submit();
            }

            // Nota al docente. Si la clase ya tiene estado, se guarda en ese momento
            // (antes había que volver a marcar la asistencia para que se guardara).
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.btn-comentario').forEach(function(boton) {
                    boton.addEventListener('click', function() {
                        const id = this.dataset.claseId;
                        const estadoActual = fila(id).dataset.estado;

                        Swal.fire({
                            title: 'Nota al Docente',
                            text: this.dataset.nombre,
                            input: 'textarea',
                            inputValue: document.getElementById('input_comentario_' + id).value,
                            inputPlaceholder: 'Escribe una observación...',
                            inputAttributes: {
                                maxlength: 1000
                            },
                            showCancelButton: true,
                            confirmButtonColor: '#009B4D',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> ' + (estadoActual ? 'Guardar Nota' : 'Continuar'),
                            cancelButtonText: 'Cancelar',
                            customClass: {
                                popup: 'rounded-4 shadow',
                                confirmButton: 'rounded-pill px-4',
                                cancelButton: 'rounded-pill px-4'
                            }
                        }).then((resultado) => {
                            if (!resultado.isConfirmed) return;

                            document.getElementById('input_comentario_' + id).value = resultado.value;
                            this.dataset.comentario = resultado.value;
                            document.getElementById('icon_comentario_' + id).className = resultado.value.trim() !== '' ?
                                'bi bi-chat-left-text-fill text-marca-green fs-5' :
                                'bi bi-chat-left-text text-secondary opacity-50 fs-5';

                            if (estadoActual) {
                                enviar(id, estadoActual);   // mismo estado: sólo se guarda la nota
                                return;
                            }

                            Swal.fire({
                                title: 'Falta marcar la asistencia',
                                text: 'La nota se guarda junto con la asistencia del profesor: ahora marca Presente, Falta o Justificado.',
                                icon: 'info',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#009B4D',
                                customClass: {
                                    popup: 'rounded-4 shadow',
                                    confirmButton: 'rounded-pill px-4'
                                }
                            });
                        });
                    });
                });
            });

            // Confirmar el estado del profesor. Si la clase no se dio, dice cuántas
            // asistencias de alumnos se van a quitar (antes sólo avisaba en general).
            window.confirmarCambio = function(id, estado) {
                const datos = fila(id).dataset;
                const registrados = parseInt(datos.registrados || '0', 10);

                if (datos.estado === estado) {
                    Swal.fire({
                        title: 'Ya está registrado como ' + nombres[estado],
                        text: 'Para cambiar sólo la nota, usa el botón de observaciones.',
                        icon: 'info',
                        confirmButtonColor: '#009B4D'
                    });
                    return;
                }

                let texto, icono = 'question', color = '#009B4D';
                if (estado === 'falta' || estado === 'justificado') {
                    icono = 'warning';
                    color = '#dc3545';
                    texto = registrados > 0 ?
                        'Hay ' + registrados + (registrados === 1 ? ' alumno registrado' : ' alumnos registrados') +
                        ' en esta clase. Como la clase no se dio, se quitarán sus asistencias y esto no se puede deshacer.' :
                        'La clase quedará como no impartida y los alumnos ya no podrán registrarse en ella.';
                } else {
                    texto = 'Se registrará que el profesor sí dio la clase.';
                    if (datos.estado === 'falta' || datos.estado === 'justificado') {
                        texto += ' Las asistencias de alumnos que se quitaron al marcar la falta no regresan: si hace falta, captúralas en la lista.';
                    }
                }

                Swal.fire({
                    title: '¿Marcar ' + nombres[estado].toUpperCase() + '?',
                    text: texto,
                    icon: icono,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, confirmar',
                    confirmButtonColor: color,
                    cancelButtonText: 'Cancelar'
                }).then((resultado) => {
                    if (resultado.isConfirmed) enviar(id, estado);
                });
            };
        })();
    </script>
@endpush
