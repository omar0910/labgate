<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Desde dónde se abrió una pantalla, para que "Volver" regrese ahí.
 *
 * Varias pantallas se abren desde más de un lugar. Clases pendientes, por
 * ejemplo, se abre desde la Bitácora, la Semana de clases, Clases de hoy y el
 * Rendimiento por Asignatura; antes su "Volver" llevaba siempre a la Bitácora y
 * el menú marcaba otra sección, así que uno no sabía dónde estaba.
 *
 * Quien enlaza agrega ?origen=... (y, si hace falta, el día o el laboratorio que
 * estaba viendo). La pantalla de destino pregunta aquí a dónde regresar, con qué
 * texto y qué sección del menú marcar. Sin origen conocido, cada pantalla usa su
 * "Volver" de siempre.
 */
class Origen
{
    /**
     * Datos que viajan con el origen para regresar al mismo lugar. El día va como
     * "dia" y no como "fecha": varias pantallas ya usan "fecha" para otra cosa (el
     * día de la clase de la lista, por ejemplo).
     */
    const PARAMETROS = ['origen', 'semana', 'dia', 'centro_id', 'semestre_id', 'pestana'];

    /**
     * ['url' => ..., 'texto' => ..., 'menu' => ...] o null si no hay un origen
     * conocido para este rol.
     */
    public static function de(Request $request): ?array
    {
        $esAdmin = optional(Auth::user())->rol === 'Administrador';
        $centro = $request->input('centro_id');

        // El día que se estaba viendo. En la lista de una clase, su "fecha" ya es
        // un día de esa semana, así que también sirve para volver a la Semana.
        $dia = self::fecha($request->input('dia')) ?? self::fecha($request->input('fecha'));

        switch ($request->input('origen')) {
            case 'inicio':
                return [
                    'url'   => route($esAdmin ? 'admin.dashboard' : 'encargado.inicio'),
                    'texto' => 'Volver al Inicio',
                    'menu'  => 'inicio',
                ];

            case 'semana':
                // La semana se identifica con cualquiera de sus días
                return [
                    'url'   => route($esAdmin ? 'admin.semana' : 'encargado.semana', array_filter([
                        'semana'    => self::fecha($request->input('semana')) ?? $dia,
                        'centro_id' => $centro,
                    ])),
                    'texto' => 'Volver a la Semana',
                    'menu'  => 'inicio',
                ];

            case 'clases':
                return [
                    'url'   => route($esAdmin ? 'admin.reportes.clases-hoy' : 'encargado.dashboard', array_filter([
                        'fecha'     => $dia,
                        'centro_id' => $centro,
                    ])),
                    'texto' => $esAdmin ? 'Volver a Clases de Hoy' : 'Volver a Gestión de Clases',
                    'menu'  => $esAdmin ? 'inicio' : 'clases',
                ];

            case 'bitacora':
                return [
                    'url'   => route('admin.bitacora.index'),
                    'texto' => 'Volver a la Bitácora',
                    'menu'  => 'monitor',
                ];

            case 'rendimiento':
                if (! $esAdmin) {
                    return null;
                }

                return [
                    'url'   => route('admin.reportes.materias-detalle', array_filter(['semestre_id' => $request->input('semestre_id')])),
                    'texto' => 'Volver al Rendimiento',
                    'menu'  => 'reportes',
                ];

            case 'reportes':
                if (! $esAdmin) {
                    return null;
                }

                $pestana = in_array($request->input('pestana'), ['dashboard', 'docentes', 'alumnos', 'infraestructura'], true)
                    ? $request->input('pestana')
                    : 'dashboard';

                return [
                    'url'   => route('admin.reportes.index', array_filter(['semestre_id' => $request->input('semestre_id')])) . '#' . $pestana,
                    'texto' => 'Volver a Reportes',
                    'menu'  => 'reportes',
                ];
        }

        return null;
    }

    /** Los parámetros del origen que trae la petición, para pasarlos a la siguiente pantalla. */
    public static function parametros(Request $request): array
    {
        return array_filter($request->only(self::PARAMETROS), fn($valor) => $valor !== null && $valor !== '');
    }

    /** Sólo fechas AAAA-MM-DD; cualquier otra cosa se descarta. */
    protected static function fecha($valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
    }
}
