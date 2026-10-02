<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Jobs\EnviarCorreoAlumnoJob;
use Carbon\Carbon;

class GenerarReportesSemanales extends Command
{
    /**
     * El nombre del comando que escribiremos en la terminal.
     */
    protected $signature = 'emails:resumen-semanal';

    /**
     * La descripción del comando.
     */
    protected $description = 'Genera y encola los reportes semanales de asistencia para alumnos y profesores de forma progresiva.';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $zonaHoraria = 'America/Hermosillo';

        // El reporte abarca desde el sábado pasado hasta hoy (viernes)
        $finSemana = Carbon::now($zonaHoraria)->toDateString(); // Hoy (Viernes)
        $inicioSemana = Carbon::now($zonaHoraria)->subDays(6)->toDateString(); // 6 días atrás (Sábado pasado)

        $this->info("=== INICIANDO PROCESO DE REPORTES SEMANALES ===");
        $this->info("Rango de evaluación: {$inicioSemana} a {$finSemana}");

        // Inicializamos los controladores de la fila de espera (Colas)
        $contador = 0;
        $lotePorMinuto = 10;

        // ---------------------------------------------------------
        // FASE 1: PROCESAMIENTO DE ALUMNOS
        // ---------------------------------------------------------
        $this->info("\n[1/2] Buscando alumnos con actividad...");

        $alumnosConActividad = User::where('rol', 'Alumno')
            ->activos()   // a los dados de baja ya no se les escribe
            ->whereHas('asistencias', function ($query) use ($inicioSemana, $finSemana) {
                $query->where('tipo', 'Clase')
                    ->whereBetween('fecha', [$inicioSemana, $finSemana]);
            })
            ->get();

        $totalProcesarAlumnos = $alumnosConActividad->count();

        if ($totalProcesarAlumnos > 0) {
            $this->info("Se encontraron {$totalProcesarAlumnos} alumnos activos. Encolando...");

            foreach ($alumnosConActividad as $alumno) {
                $minutosDeEspera = floor($contador / $lotePorMinuto);

                EnviarCorreoAlumnoJob::dispatch($alumno, $inicioSemana, $finSemana)
                    ->delay(Carbon::now($zonaHoraria)->addMinutes($minutosDeEspera));

                $contador++;
            }
        } else {
            $this->warn('No se encontraron alumnos con asistencias esta semana. Saltando fase...');
        }

        // ---------------------------------------------------------
        // FASE 2: PROCESAMIENTO DE PROFESORES
        // ---------------------------------------------------------
        $this->info("\n[2/2] Buscando profesores con actividad...");

        $horariosActivosIds = \App\Models\AsistenciaProfesor::whereBetween('fecha', [$inicioSemana, $finSemana])
            ->pluck('horario_id')
            ->unique();

        $profesoresIds = \App\Models\Horario::whereIn('id', $horariosActivosIds)
            ->pluck('user_id')
            ->unique();

        $profesores = User::whereIn('id', $profesoresIds)
            ->where('rol', 'Profesor')
            ->activos()
            ->get();

        $totalProfesores = $profesores->count();

        if ($totalProfesores > 0) {
            $this->info("Se encontraron {$totalProfesores} profesores con actividad. Encolando...");

            foreach ($profesores as $profesor) {
                // El contador sigue sumando desde donde se quedaron los alumnos
                $minutosDeEspera = floor($contador / $lotePorMinuto);

                \App\Jobs\EnviarCorreoProfesorJob::dispatch($profesor, $inicioSemana, $finSemana)
                    ->delay(Carbon::now($zonaHoraria)->addMinutes($minutosDeEspera));

                $contador++;
            }
        } else {
            $this->warn('No hubo actividad de profesores esta semana. Saltando fase...');
        }

        // ---------------------------------------------------------
        // RESUMEN FINAL
        // ---------------------------------------------------------
        $this->info("\n=== RESUMEN DE EJECUCIÓN ===");
        if ($contador === 0) {
            $this->warn("No hubo actividad en todo el sistema. No se encoló ningún correo.");
        } else {
            $minutosTotales = floor($contador / $lotePorMinuto);
            $this->info("¡Éxito! Se han encolado un total de {$contador} correos.");
            $this->info("Tiempo estimado para que el servidor termine los envíos: {$minutosTotales} minutos.");
        }
    }
}
