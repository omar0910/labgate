<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CentroComputo;
use App\Models\Equipo;

class EquiposSeeder extends Seeder
{
    public function run()
    {
        // Traemos todos los laboratorios que ya existen
        $centros = CentroComputo::all();

        foreach ($centros as $centro) {
            // Un ciclo desde la PC 1 hasta la "capacidad" del laboratorio (Ej. 30)
            for ($i = 1; $i <= $centro->capacidad; $i++) {
                Equipo::firstOrCreate([
                    'centro_computo_id' => $centro->id,
                    'numero_maquina' => $i,
                ], [
                    'estado' => 'disponible',
                    'usos_acumulados' => 0
                ]);
            }
        }

        $this->command->info('¡Equipos generados correctamente según la capacidad de cada laboratorio!');
    }
}
