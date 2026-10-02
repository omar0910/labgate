<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Incidencia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class IncidenciaController extends Controller
{
    /**
     * Muestra la lista de fallas.
     * Lógica inteligente: Si es Admin ve TODO, si es Encargado solo ve SU laboratorio.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Incidencia::with(['centroComputo', 'user'])
            ->where('estado', 'pendiente')
            ->orderBy('created_at', 'desc');

        // Filtro por laboratorio. Con filled y no has: "?centro_id=" (vacío) dejaba la
        // lista sin nada, porque buscaba las del laboratorio "null".
        $centroId = is_numeric($request->input('centro_id')) ? (int) $request->input('centro_id') : null;
        if ($centroId) {
            $query->where('centro_computo_id', $centroId);
        }

        $pendientes = $query->get();

        // Cuántas pendientes tiene cada laboratorio, para los botones del filtro
        $pendientesPorCentro = Incidencia::where('estado', 'pendiente')
            ->selectRaw('centro_computo_id, COUNT(*) as total')
            ->groupBy('centro_computo_id')
            ->pluck('total', 'centro_computo_id');
        $centros = \App\Models\CentroComputo::orderBy('nombre_centro')->get();

        // CAMBIO: Ahora traemos 6 para que se vea una cuadrícula perfecta (2 filas de 3)
        $resueltas = Incidencia::with(['centroComputo', 'resolvio'])->whereIn('estado', Incidencia::RESUELTAS)->latest('updated_at')->take(6)->get();

        $datos = compact('pendientes', 'resueltas', 'centros', 'centroId', 'pendientesPorCentro');

        return view($user->rol == 'Administrador' ? 'admin.incidencias.index' : 'encargado.incidencias.index', $datos);
    }

    /**
     * Marcar como Resuelta
     */
    public function resolver(Request $request, $id)
    {
        $request->validate([
            'nota_resolucion' => 'nullable|string|max:1000',
        ]);

        $incidencia = Incidencia::findOrFail($id);

        // Ya estaba cerrada (p. ej. doble clic, o la cerró otra persona): no se vuelve
        // a tocar la PC ni se reinicia otra vez su odómetro.
        if (in_array($incidencia->estado, Incidencia::RESUELTAS, true)) {
            return redirect()->back()->with('success', 'Ese reporte ya estaba cerrado.');
        }

        // 1. Guardamos la nota del técnico y cerramos el ticket
        $incidencia->estado = 'resuelta';
        $incidencia->nota_resolucion = $request->input('nota_resolucion');
        // Quién y cuándo (antes sólo quedaba la fecha de la última modificación)
        $incidencia->resuelta_por = Auth::id();
        $incidencia->fecha_resolucion = now();
        $incidencia->save();

        // 2. Buscamos la máquina física
        $equipo = \App\Models\Equipo::where('centro_computo_id', $incidencia->centro_computo_id)
            ->where('numero_maquina', $incidencia->numero_maquina)
            ->first();

        // Si la misma PC sigue con un mantenimiento abierto (el bloqueo desde el
        // monitor), se queda bloqueada: antes cerrar cualquier otro reporte suyo
        // (p. ej. el del mouse que mandó un alumno) la liberaba en pleno mantenimiento.
        $otrosPendientes = Incidencia::where('centro_computo_id', $incidencia->centro_computo_id)
            ->where('numero_maquina', $incidencia->numero_maquina)
            ->where('estado', 'pendiente')
            ->where('categoria', 'like', '%Mantenimiento%')
            ->count();

        if ($equipo) {
            // Le quitamos el candado rojo, salvo que le queden otros reportes
            if ($otrosPendientes === 0) {
                $equipo->estado = 'disponible';
            }

            // --- REGLA ESTRICTA DE NEGOCIO ---
            // Solo reiniciamos a cero si la categoría incluye la palabra "Mantenimiento"
            // (Aplica para "Mantenimiento Preventivo (Automático)" y "(Manual)")
            if (str_contains($incidencia->categoria, 'Mantenimiento')) {

                $equipo->usos_acumulados = 0;
                // Igual que el mantenimiento masivo: se anota cuándo se reinició, para
                // saber qué usos cuentan en el odómetro si después se borra un registro.
                $equipo->ultimo_mantenimiento = now();
                $mensajeFlash = 'Ticket cerrado. La PC ha sido liberada y su odómetro de desgaste se reinició a 0.';
            } else {

                // Si fue un Mouse roto, Internet, etc., no tocamos el odómetro.
                $mensajeFlash = 'Ticket cerrado. La PC ha sido liberada (El odómetro sigue contando porque fue una reparación menor).';
            }

            if ($otrosPendientes > 0) {
                $mensajeFlash = 'Ticket cerrado. La PC #' . $incidencia->numero_maquina
                    . ' sigue bloqueada porque tiene un mantenimiento pendiente.';
            }

            // Guardamos los cambios físicos en la PC
            $equipo->save();

            return redirect()->back()->with('success', $mensajeFlash);
        }

        return redirect()->back()->with('success', 'Ticket cerrado correctamente.');
    }

    public function historialCompleto(Request $request)
    {
        $user = Auth::user();

        $query = Incidencia::with(['centroComputo', 'user', 'resolvio'])
            ->whereIn('estado', Incidencia::RESUELTAS)
            // Por cuándo se cerraron (la fecha guardada al cerrarlas, o su última modificación)
            ->orderByRaw('COALESCE(fecha_resolucion, updated_at) DESC');

        // Filtro por laboratorio (vacío o inválido: todos)
        if (is_numeric($request->input('centro_id'))) {
            $query->where('centro_computo_id', (int) $request->input('centro_id'));
        }

        // Usamos paginate(10) para que Laravel divida los resultados de 10 en 10
        $historial = $query->paginate(10);

        if ($user->rol == 'Administrador') {
            return view('admin.incidencias.historial', compact('historial'));
        } else {
            return view('encargado.incidencias.historial', compact('historial'));
        }
    }
}
