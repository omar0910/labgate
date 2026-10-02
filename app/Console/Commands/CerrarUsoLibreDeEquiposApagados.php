<?php

namespace App\Console\Commands;

use App\Support\ReportesDeEquipos;
use Illuminate\Console\Command;

/**
 * Cierra el Uso Libre de las computadoras que se apagaron sin que el alumno lo
 * terminara.
 *
 * Cada computadora con el script del laboratorio se reporta cada pocos segundos.
 * Si una deja de hacerlo durante 10 minutos, se apagó: su Uso Libre se cierra
 * con la hora de su último reporte. El detalle está en App\Support\ReportesDeEquipos.
 *
 * Lo ejecuta la tarea programada cada minuto; a mano sirve para comprobarlo:
 *
 *   php artisan equipos:cerrar-uso-libre-apagados
 */
class CerrarUsoLibreDeEquiposApagados extends Command
{
    protected $signature = 'equipos:cerrar-uso-libre-apagados';

    protected $description = 'Cierra el Uso Libre de las computadoras que dejaron de reportarse (se apagaron)';

    public function handle(): int
    {
        $cerrados = ReportesDeEquipos::cerrarDeEquiposApagados();

        $this->info($cerrados === 0
            ? 'No hay Uso Libre abierto en computadoras apagadas.'
            : "Se cerraron {$cerrados} sesiones de Uso Libre de computadoras apagadas.");

        return self::SUCCESS;
    }
}
