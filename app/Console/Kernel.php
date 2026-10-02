<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Se ejecuta el viernes a las 10:15 PM, garantizando que el laboratorio
        // ya está cerrado y no habrá más modificaciones en las asistencias.
        $schedule->command('emails:resumen-semanal')
            ->fridays()
            ->at('22:15')
            ->timezone('America/Hermosillo');

        // Cierra el Uso Libre de las computadoras del laboratorio que se apagaron
        // sin que el alumno lo terminara (ver App\Support\ReportesDeEquipos).
        $schedule->command('equipos:cerrar-uso-libre-apagados')->everyMinute();

        // Sólo en la demostración pública: la actividad simulada del día y el
        // reinicio nocturno de los datos (ver config/demo.php).
        if (config('demo.activo')) {
            $schedule->command('demo:simular')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('demo:reiniciar')->dailyAt(config('demo.hora_de_reinicio'));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
