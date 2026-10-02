<?php

namespace Database\Seeders;

use App\Demo\Generador;
use App\Demo\Simulador;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Llena una base VACÍA con los datos ficticios de la demostración.
 *
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * o, con el modo demostración encendido:  php artisan demo:reiniciar
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Nunca sobre una base con gente: mezclaría datos inventados con reales.
        if (User::count() > 0) {
            $this->command?->error('La base ya tiene usuarios. La demostración sólo se siembra en una base vacía (migrate:fresh).');

            return;
        }

        $resumen = (new Generador())->generar();

        // Lo que ya ocurrió hoy hasta este momento
        $hoy = Simulador::avanzar();

        $this->command?->table(['Datos de demostración', 'Cantidad'], collect($resumen)->map(fn($n, $que) => [$que, $n])->values()->all());
        $this->command?->info("Hoy: {$hoy['clases']} clases ya empezadas, {$hoy['registros']} registros y {$hoy['uso_libre_abierto']} sesiones de uso libre abiertas.");
        $this->command?->line('Cuentas: ' . implode(', ', config('demo.cuentas')) . ' · contraseña: ' . config('demo.contrasena'));
    }
}
