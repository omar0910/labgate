<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deja la demostración como nueva: borra TODA la base y la vuelve a llenar con
 * los datos ficticios, con las fechas recalculadas respecto a hoy.
 *
 * Lo ejecuta la tarea programada cada noche, y sirve a mano después de que
 * alguien haya dejado la demostración patas arriba:
 *
 *   php artisan demo:reiniciar
 *
 * Sólo funciona con el modo demostración encendido (DEMO=true). En una
 * instalación real se niega a correr: borraría los datos de verdad.
 */
class DemoReiniciar extends Command
{
    protected $signature = 'demo:reiniciar';

    protected $description = 'Borra la base y la vuelve a llenar con los datos ficticios de la demostración (sólo con DEMO=true)';

    public function handle(): int
    {
        if (! config('demo.activo')) {
            $this->error('El modo demostración está apagado (DEMO=false). No se tocó nada.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true, '--seed' => true, '--seeder' => 'DemoSeeder']);

        // Las fotos de perfil que hayan subido los visitantes
        File::cleanDirectory(storage_path('app/public/profile-photos'));
        $this->callSilently('cache:clear');

        $this->info('Demostración reiniciada.');

        return self::SUCCESS;
    }
}
