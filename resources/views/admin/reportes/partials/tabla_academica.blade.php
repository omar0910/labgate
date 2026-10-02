<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-bold text-marca-green">Estadística de Asistencia: {{ $materia_nombre ?? 'Materia' }}</h6>
        <small class="text-muted">Total de sesiones en el periodo: <strong>{{ $total_sesiones }}</strong></small>
    </div>

    <div>
        <a href="{{ route('reportes.generar', array_merge(request()->all(), ['formato' => 'pdf'])) }}" target="_blank"
            class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover table-bordered">
        <thead class="table-light">
            <tr>
                <th>Alumno</th>
                <th class="text-center">Asistencias</th>
                <th class="text-center">% Porcentaje</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($estadisticas as $stat)
                @php
                    $porcentaje = $total_sesiones > 0 ? round(($stat->total_asistencias / $total_sesiones) * 100) : 0;
                @endphp
                <tr>
                    <td>{{ $stat->user->apellido_paterno }} {{ $stat->user->apellido_materno }} {{ $stat->user->name }}
                    </td>
                    <td class="text-center">{{ $stat->total_asistencias }} / {{ $total_sesiones }}</td>
                    <td class="text-center fw-bold">{{ $porcentaje }}%</td>
                    <td class="text-center">
                        @if ($porcentaje >= 80)
                            <span class="badge bg-success">Excelente</span>
                        @elseif($porcentaje >= 60)
                            <span class="badge bg-warning text-dark">Regular</span>
                        @else
                            <span class="badge bg-danger">Bajo</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No hay alumnos registrados en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
