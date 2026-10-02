<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold text-marca-green">Resultados de la Búsqueda ({{ $asistencias->count() }} registros)</h6>

    {{-- BOTONES DE EXPORTACIÓN --}}
    <div>
        {{-- Fíjate que reutilizamos los parámetros de la URL para generar el PDF con los mismos filtros --}}
        <a href="{{ route('reportes.generar', array_merge(request()->all(), ['formato' => 'pdf'])) }}" target="_blank"
            class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <a href="#" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Descargar Excel
        </a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover table-bordered">
        <thead class="table-light">
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Alumno</th>
                <th>Actividad</th>
                <th>PC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($asistencias as $registro)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($registro->fecha_hora_registro)->format('H:i') }}</td>
                    <td>{{ $registro->user->name }} {{ $registro->user->apellido_paterno }}</td>
                    <td>{{ $registro->tipo }}</td>
                    <td>{{ $registro->equipo_personal ? 'Equipo personal' : $registro->numero_maquina }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No hay datos</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
