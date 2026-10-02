<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Asistencia;
use App\Models\Semestre; // <-- NUEVO: Importamos el modelo Semestre
use App\Support\FaltasSinRegistro;
use App\Mail\ReporteSemanalAlumno;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EnviarCorreoAlumnoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alumno;

    /**
     * La semana del resumen, fijada por el comando al programar los envíos.
     * Antes cada correo la calculaba al salir, y como se escalonan de 10 en 10
     * por minuto, los que salían después de medianoche (o si la cola se
     * atrasaba) resumían otra semana. Si viene vacía (correos que ya estaban en
     * la cola), se calcula como antes.
     */
    protected $inicioSemana;
    protected $finSemana;

    /**
     * Create a new job instance.
     */
    public function __construct(User $alumno, ?string $inicioSemana = null, ?string $finSemana = null)
    {
        $this->alumno = $alumno;
        $this->inicioSemana = $inicioSemana;
        $this->finSemana = $finSemana;
    }

    /**
     * Execute the job.
     */
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

        // rango: Desde el sábado pasado hasta hoy viernes
        $finSemana = $this->finSemana ?? Carbon::now($zonaHoraria)->toDateString();
        $inicioSemana = $this->inicioSemana ?? Carbon::now($zonaHoraria)->subDays(6)->toDateString();

        // Agregamos 'with' para traer los nombres de las materias eficientemente
        $asistencias = Asistencia::with(['horario.materia'])
            ->where('user_id', $this->alumno->id)
            ->where('tipo', 'Clase')
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->get();

        // Las clases de la semana a las que faltó sin que quedara registro: se dieron,
        // otros compañeros sí se registraron y él no. Es la misma regla de su
        // pantalla "Mi Progreso"; antes el correo sólo contaba sus registros y le
        // podía decir 100% mientras la pantalla decía menos (ver FaltasSinRegistro).
        $sinRegistro = FaltasSinRegistro::de($this->alumno, $semestreActivo->id)
            ->filter(fn($falta) => $falta['fecha'] >= $inicioSemana && $falta['fecha'] <= $finSemana)
            ->values();

        $totalClases = $asistencias->count() + $sinRegistro->count();

        if ($totalClases === 0) {
            return;
        }

        $presentes = $asistencias->where('estado', 'presente')->count();
        $justificadas = $asistencias->where('estado', 'justificado')->count();
        $faltas = $asistencias->where('estado', 'falta')->count() + $sinRegistro->count();
        $porcentaje = round((($presentes + $justificadas) / $totalClases) * 100);

        $resumenGeneral = [
            'total' => $totalClases,
            'asistencias' => $presentes,
            'justificadas' => $justificadas,
            'faltas' => $faltas,
            'sin_registro' => $sinRegistro->count(),
            'porcentaje' => $porcentaje
        ];

        // NUEVO: Magia para agrupar las asistencias por Materia
        $desgloseMaterias = [];

        foreach ($asistencias as $asistencia) {
            $nombreMateria = $asistencia->horario->materia->nombre_materia ?? 'Clase General';

            if (!isset($desgloseMaterias[$nombreMateria])) {
                $desgloseMaterias[$nombreMateria] = $this->materiaVacia();
            }

            $desgloseMaterias[$nombreMateria]['total']++;

            if ($asistencia->estado === 'presente') {
                $desgloseMaterias[$nombreMateria]['presentes']++;
            } elseif ($asistencia->estado === 'falta') {
                $desgloseMaterias[$nombreMateria]['faltas']++;
            } elseif ($asistencia->estado === 'justificado') {
                $desgloseMaterias[$nombreMateria]['justificadas']++;
            }
        }

        // Las faltas sin registro, en su materia (puede ser una en la que no tenga
        // ningún registro esa semana)
        foreach ($sinRegistro as $falta) {
            $nombreMateria = $falta['horario']->materia->nombre_materia ?? 'Clase General';

            if (!isset($desgloseMaterias[$nombreMateria])) {
                $desgloseMaterias[$nombreMateria] = $this->materiaVacia();
            }

            $desgloseMaterias[$nombreMateria]['total']++;
            $desgloseMaterias[$nombreMateria]['faltas']++;
            $desgloseMaterias[$nombreMateria]['sin_registro']++;
        }

        // NUEVO: Calcular el porcentaje individual de cada materia
        foreach ($desgloseMaterias as $materia => &$datos) {
            $datos['porcentaje'] = round((($datos['presentes'] + $datos['justificadas']) / $datos['total']) * 100);
        }

        try {
            if (!empty($this->alumno->email)) {
                // Ahora le enviamos DOS cosas al correo: el resumen general y el desglose
                Mail::to($this->alumno->email)->send(new ReporteSemanalAlumno($this->alumno, $resumenGeneral, $desgloseMaterias));
            }
        } catch (\Exception $e) {
            Log::error('Fallo al enviar reporte semanal a ' . $this->alumno->email . ': ' . $e->getMessage());
        }
    }

    /** Contadores en cero de una materia del desglose. */
    protected function materiaVacia(): array
    {
        return [
            'total' => 0,
            'presentes' => 0,
            'faltas' => 0,
            'justificadas' => 0,
            'sin_registro' => 0,
            'porcentaje' => 0
        ];
    }
}
