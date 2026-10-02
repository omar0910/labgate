<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\AsistenciaProfesor; // <-- Tu modelo específico
use App\Models\Semestre; // <-- NUEVO: Importamos el modelo Semestre
use App\Mail\ReporteSemanalProfesor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EnviarCorreoProfesorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $profesor;

    /**
     * La semana del resumen, fijada por el comando al programar los envíos.
     * Antes cada correo la calculaba al salir, y como se escalonan de 10 en 10
     * por minuto, los que salían después de medianoche (o si la cola se
     * atrasaba) resumían otra semana. Si viene vacía (correos que ya estaban en
     * la cola), se calcula como antes.
     */
    protected $inicioSemana;
    protected $finSemana;

    public function __construct(User $profesor, ?string $inicioSemana = null, ?string $finSemana = null)
    {
        $this->profesor = $profesor;
        $this->inicioSemana = $inicioSemana;
        $this->finSemana = $finSemana;
    }

    public function handle(): void
    {
        $zonaHoraria = 'America/Hermosillo';

        // =========================================================================
        // EL GUARDIÁN DEL SEMESTRE: Valida si estamos en periodo de clases activo
        // =========================================================================
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // Si por alguna razón no hay ningún semestre marcado como activo, abortamos
        if (!$semestreActivo) {
            return;
        }

        // Obtenemos la fecha actual y las fechas límite del semestre
        $hoy = Carbon::now($zonaHoraria);
        $inicioSemestre = Carbon::parse($semestreActivo->fecha_inicio, $zonaHoraria)->startOfDay();
        $finSemestre = Carbon::parse($semestreActivo->fecha_fin, $zonaHoraria)->endOfDay();

        // Comparamos si la fecha de hoy está fuera de los límites del semestre
        if ($hoy->lessThan($inicioSemestre) || $hoy->greaterThan($finSemestre)) {
            // El Job se detiene silenciosamente aquí, sin enviar ningún correo
            return;
        }
        // =========================================================================

        $finSemana = $this->finSemana ?? Carbon::now($zonaHoraria)->toDateString();
        $inicioSemana = $this->inicioSemana ?? Carbon::now($zonaHoraria)->subDays(6)->toDateString();

        // 1. Cargamos también la relación del grupo
        $asistencias = AsistenciaProfesor::with(['horario.materia', 'horario.grupo'])
            ->whereHas('horario', function ($query) {
                $query->where('user_id', $this->profesor->id);
            })
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->get();

        $totalClases = $asistencias->count();

        if ($totalClases === 0) {
            return;
        }

        $presentes = $asistencias->where('estado', 'asistio')->count();
        $justificadas = $asistencias->where('estado', 'justificado')->count();
        $faltas = $asistencias->where('estado', 'falta')->count();
        $porcentaje = round((($presentes + $justificadas) / $totalClases) * 100);

        $resumenGeneral = [
            'total' => $totalClases,
            'asistencias' => $presentes,
            'justificadas' => $justificadas,
            'faltas' => $faltas,
            'porcentaje' => $porcentaje
        ];

        $desgloseClases = [];

        foreach ($asistencias as $asistencia) {
            // 2. Construimos el nombre detallado: "Nombre Materia (Grupo)"
            $materia = $asistencia->horario->materia->nombre_materia ?? 'Materia';
            $grupo = $asistencia->horario->grupo->nombre_grupo ?? 'Gral'; // Ajusta 'nombre_grupo' según tu tabla

            $nombreIdentificador = "{$materia} - {$grupo}";

            if (!isset($desgloseClases[$nombreIdentificador])) {
                $desgloseClases[$nombreIdentificador] = [
                    'total' => 0,
                    'presentes' => 0,
                    'faltas' => 0,
                    'justificadas' => 0,
                    'porcentaje' => 0
                ];
            }

            $desgloseClases[$nombreIdentificador]['total']++;

            if ($asistencia->estado === 'asistio') {
                $desgloseClases[$nombreIdentificador]['presentes']++;
            } elseif ($asistencia->estado === 'falta') {
                $desgloseClases[$nombreIdentificador]['faltas']++;
            } elseif ($asistencia->estado === 'justificado') {
                $desgloseClases[$nombreIdentificador]['justificadas']++;
            }
        }

        foreach ($desgloseClases as $clase => &$datos) {
            $datos['porcentaje'] = round((($datos['presentes'] + $datos['justificadas']) / $datos['total']) * 100);
        }

        try {
            if (!empty($this->profesor->email)) {
                Mail::to($this->profesor->email)->send(new ReporteSemanalProfesor($this->profesor, $resumenGeneral, $desgloseClases));
            }
        } catch (\Exception $e) {
            Log::error('Fallo al enviar reporte a profesor ' . $this->profesor->email . ': ' . $e->getMessage());
        }
    }
}
