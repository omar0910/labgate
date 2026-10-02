<?php

namespace App\Console\Commands;

use App\Demo\Simulador;
use Illuminate\Console\Command;

/**
 * Pone en la demostración lo que "está pasando" en este momento: los alumnos de
 * las clases que ya empezaron y el ir y venir del uso libre. El detalle está en
 * App\Demo\Simulador.
 *
 * Lo ejecuta la tarea programada cada cinco minutos, sólo con DEMO=true.
 */
class DemoSimular extends Command
{
    protected $signature = 'demo:simular';

    protected $description = 'Simula la actividad del momento en la demostración (sólo con DEMO=true)';

    public function handle(): int
    {
        if (! config('demo.activo')) {
            $this->error('El modo demostración está apagado (DEMO=false). No se tocó nada.');

            return self::FAILURE;
        }

        $hecho = Simulador::avanzar();

        $this->info("Clases registradas: {$hecho['clases']} ({$hecho['registros']} registros). "
            . "Uso libre: {$hecho['uso_libre_abierto']} entradas, {$hecho['uso_libre_cerrado']} salidas.");

        return self::SUCCESS;
    }
}
