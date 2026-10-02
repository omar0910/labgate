<?php

namespace App\Console\Commands;

use App\Models\Asistencia;
use App\Models\Equipo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vuelve a contar los usos de cada máquina a partir de los registros reales.
 *
 * Cada registro con número de máquina le suma un uso al equipo. Hasta ahora, al
 * borrar un registro (un uso libre eliminado, las asistencias de una clase a la
 * que faltó el profesor) ese uso no se restaba, así que los contadores quedaron
 * más altos de lo real. Este comando los deja cuadrados con lo que de verdad hay.
 *
 * Por omisión sólo ENSEÑA las diferencias; con --aplicar las guarda.
 *
 *   php artisan equipos:recalcular-usos
 *   php artisan equipos:recalcular-usos --aplicar
 */
class RecalcularUsosEquipos extends Command
{
    protected $signature = 'equipos:recalcular-usos {--aplicar : Guarda los valores corregidos}';

    protected $description = 'Recalcula los usos de cada máquina (históricos y desde el último mantenimiento) a partir de las asistencias reales';

    public function handle(): int
    {
        $equipos = Equipo::with('centroComputo')->orderBy('centro_computo_id')->orderBy('numero_maquina')->get();

        // Usos históricos: todos los registros de esa máquina.
        $historicos = Asistencia::whereNotNull('numero_maquina')
            ->whereNotNull('centro_computo_id')
            ->select('centro_computo_id', 'numero_maquina', DB::raw('COUNT(*) as total'))
            ->groupBy('centro_computo_id', 'numero_maquina')
            ->get()
            ->mapWithKeys(fn($r) => [$r->centro_computo_id . '|' . $r->numero_maquina => (int) $r->total]);

        $cambios = [];

        foreach ($equipos as $equipo) {
            $llave = $equipo->centro_computo_id . '|' . $equipo->numero_maquina;
            $historicoReal = $historicos[$llave] ?? 0;

            // Odómetro: sólo lo registrado después del último mantenimiento. Si el
            // equipo no tiene fecha de mantenimiento, no se sabe desde cuándo contar
            // (los tickets de mantenimiento lo reiniciaban sin anotar la fecha), así
            // que se deja como está: recalcularlo podría deshacer un mantenimiento.
            $acumuladoReal = $equipo->ultimo_mantenimiento
                ? Asistencia::where('centro_computo_id', $equipo->centro_computo_id)
                    ->where('numero_maquina', $equipo->numero_maquina)
                    ->where('created_at', '>', $equipo->ultimo_mantenimiento)
                    ->count()
                : (int) $equipo->usos_acumulados;

            if ((int) $equipo->usos_historicos === $historicoReal && (int) $equipo->usos_acumulados === $acumuladoReal) {
                continue;
            }

            $cambios[] = [
                'equipo'   => $equipo,
                'fila'     => [
                    ($equipo->centroComputo->nombre_centro ?? '?') . ' #' . $equipo->numero_maquina,
                    $equipo->usos_historicos . ' → ' . $historicoReal,
                    $equipo->ultimo_mantenimiento
                        ? $equipo->usos_acumulados . ' → ' . $acumuladoReal
                        : $equipo->usos_acumulados . ' (sin fecha de mantenimiento: se deja igual)',
                ],
                'historico' => $historicoReal,
                'acumulado' => $acumuladoReal,
            ];
        }

        if (empty($cambios)) {
            $this->info('Todos los equipos están cuadrados con sus registros. No hay nada que corregir.');
            return self::SUCCESS;
        }

        $this->table(['Equipo', 'Usos históricos', 'Desde el último mantenimiento'], array_column($cambios, 'fila'));

        if (! $this->option('aplicar')) {
            $this->line('');
            $this->warn(count($cambios) . ' equipos no cuadran. No se guardó nada.');
            $this->line('Para corregirlos: php artisan equipos:recalcular-usos --aplicar');
            return self::SUCCESS;
        }

        foreach ($cambios as $cambio) {
            // Directo a la tabla: no hace falta disparar nada del modelo.
            Equipo::whereKey($cambio['equipo']->id)->update([
                'usos_historicos' => $cambio['historico'],
                'usos_acumulados' => $cambio['acumulado'],
            ]);
        }

        $this->info('Listo: se corrigieron ' . count($cambios) . ' equipos.');

        return self::SUCCESS;
    }
}
