<?php

namespace App\Console\Commands;

use App\Models\Asistencia;
use App\Models\CentroComputo;
use App\Models\Equipo;
use App\Models\Incidencia;
use Illuminate\Console\Command;

/**
 * Crea en el registro de hardware las computadoras que le faltan a un laboratorio.
 *
 * Cada laboratorio debe tener una fila en "equipos" por cada PC, de la 1 a su
 * capacidad. Se crean solas al dar de alta el laboratorio o al cambiarle la
 * capacidad, pero un laboratorio que ya existía cuando se agregó ese registro se
 * quedó sin ninguna. Sin esas filas el laboratorio no sale en el Registro Físico
 * de Hardware ni en el mantenimiento masivo, sus usos no se cuentan y mandar una
 * de sus PCs a mantenimiento desde el Monitor no la bloquea.
 *
 * A cada PC nueva se le ponen los usos que de verdad tiene en las asistencias. Si
 * ya tuvo un mantenimiento (un ticket de mantenimiento cerrado), el odómetro
 * cuenta desde esa fecha; si tiene uno abierto, se crea bloqueada.
 *
 * Por omisión sólo ENSEÑA lo que falta; con --aplicar lo crea. No toca ni borra
 * las que ya existen, así que se puede ejecutar las veces que haga falta.
 *
 *   php artisan equipos:completar
 *   php artisan equipos:completar --aplicar
 */
class CompletarEquipos extends Command
{
    protected $signature = 'equipos:completar {--aplicar : Crea las computadoras que faltan}';

    protected $description = 'Crea en el registro de hardware las computadoras que le faltan a cada laboratorio (de la 1 a su capacidad)';

    public function handle(): int
    {
        $resumen = [];
        $porCrear = [];

        foreach (CentroComputo::orderBy('id')->get() as $centro) {
            $existentes = $centro->equipos()->pluck('numero_maquina')->flip();
            $faltan = [];

            for ($i = 1; $i <= (int) $centro->capacidad; $i++) {
                if (! isset($existentes[$i])) {
                    $faltan[] = $i;
                }
            }

            $resumen[] = [
                $centro->nombre_centro,
                (int) $centro->capacidad,
                $existentes->count(),
                count($faltan) === 0 ? 'Ninguna' : count($faltan) . ' (' . $this->rango($faltan) . ')',
            ];

            foreach ($faltan as $numero) {
                $porCrear[] = $this->datosDe($centro, $numero);
            }
        }

        $this->table(['Laboratorio', 'Capacidad', 'PCs registradas', 'Faltan'], $resumen);

        if (empty($porCrear)) {
            $this->info('Todos los laboratorios tienen sus computadoras registradas. No hay nada que crear.');
            return self::SUCCESS;
        }

        $bloqueadas = array_filter($porCrear, fn($pc) => $pc['estado'] === 'mantenimiento');
        foreach ($bloqueadas as $pc) {
            $this->warn("  {$pc['laboratorio']} PC #{$pc['numero_maquina']}: se creará BLOQUEADA, tiene un ticket de mantenimiento abierto en la Mesa de Ayuda.");
        }

        if (! $this->option('aplicar')) {
            $this->line('');
            $this->warn('Faltan ' . count($porCrear) . ' computadoras. No se creó nada.');
            $this->line('Para crearlas: php artisan equipos:completar --aplicar');
            return self::SUCCESS;
        }

        foreach ($porCrear as $pc) {
            Equipo::firstOrCreate(
                ['centro_computo_id' => $pc['centro_computo_id'], 'numero_maquina' => $pc['numero_maquina']],
                [
                    'estado'               => $pc['estado'],
                    'usos_acumulados'      => $pc['usos_acumulados'],
                    'usos_historicos'      => $pc['usos_historicos'],
                    'ultimo_mantenimiento' => $pc['ultimo_mantenimiento'],
                ]
            );
        }

        $this->info('Listo: se crearon ' . count($porCrear) . ' computadoras, con los usos que ya tenían registrados.');

        return self::SUCCESS;
    }

    /** Lo que le corresponde a una PC que todavía no está en el registro. */
    protected function datosDe(CentroComputo $centro, int $numero): array
    {
        $registros = Asistencia::where('centro_computo_id', $centro->id)->where('numero_maquina', $numero);

        $mantenimientos = Incidencia::where('centro_computo_id', $centro->id)
            ->where('numero_maquina', $numero)
            ->where('categoria', 'like', '%Mantenimiento%');

        // Su último mantenimiento terminado: desde ahí cuenta el odómetro.
        $ultimo = (clone $mantenimientos)->whereIn('estado', Incidencia::RESUELTAS)->get()
            ->map(fn($ticket) => $ticket->fecha_de_cierre)
            ->filter()
            ->max();

        return [
            'laboratorio'          => $centro->nombre_centro,
            'centro_computo_id'    => $centro->id,
            'numero_maquina'       => $numero,
            'estado'               => (clone $mantenimientos)->where('estado', 'pendiente')->exists() ? 'mantenimiento' : 'disponible',
            'usos_historicos'      => (clone $registros)->count(),
            'usos_acumulados'      => $ultimo ? (clone $registros)->where('created_at', '>', $ultimo)->count() : (clone $registros)->count(),
            'ultimo_mantenimiento' => $ultimo,
        ];
    }

    /** "1 a 25" si son seguidas; si no, la lista: "3, 7, 12". */
    protected function rango(array $numeros): string
    {
        $seguidas = count($numeros) > 2 && (end($numeros) - $numeros[0] + 1) === count($numeros);

        return $seguidas ? $numeros[0] . ' a ' . end($numeros) : implode(', ', $numeros);
    }
}
