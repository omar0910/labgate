<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\CentroComputo;
use App\Models\DiaInhabil;
use App\Models\Horario;
use App\Models\Semestre;
use App\Models\User;
use App\Support\CalendarioDeClases;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Clases ya pasadas a las que les falta la asistencia del profesor.
 *
 * Mientras en los laboratorios se sigan usando hojas de papel, las semanas
 * anteriores a que el horario existiera en el sistema quedan sin registro. Aquí
 * se ven todas juntas y se capturan de una vez, marcando varias y eligiendo un
 * estado, en lugar de ir día por día en "Clases de hoy".
 *
 * Hace lo mismo que el registro de "Clases de hoy", con una diferencia a
 * propósito: NO manda correo al profesor. Es captura de historial; pasar cinco
 * semanas de hojas le llenaría el buzón de avisos atrasados.
 */
class ClasesPendientesController extends Controller
{
    const ESTADOS = [
        'asistio'     => 'Asistió',
        'falta'       => 'Falta',
        'justificado' => 'Justificado',
    ];

    /** Dónde se guarda la última captura, para poder deshacerla. */
    const SESION_CAPTURA = 'pendientes.ultima_captura';

    public function index(Request $request)
    {
        $semestre = Semestre::where('es_activo', 1)->first();

        if (! $semestre) {
            return view('admin.monitor.pendientes', ['semestre' => null]);
        }

        [$desde, $hasta] = $this->rango($request, $semestre);

        $horarios = Horario::with(['materia', 'grupo', 'user', 'centroComputo'])
            ->where('semestre_id', $semestre->id)
            ->when($request->filled('centro'), fn($q) => $q->where('centro_computo_id', $request->centro))
            ->when($request->filled('profesor'), fn($q) => $q->where('user_id', $request->profesor))
            ->get();

        $ids = $horarios->pluck('id');
        $diasInhabiles = DiaInhabil::where('semestre_id', $semestre->id)->pluck('fecha')->all();

        // Lo que ya está capturado del profesor en el rango: esas no están pendientes.
        $capturadas = AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->get(['horario_id', 'fecha'])
            ->mapWithKeys(fn($r) => [$r->horario_id . '|' . CalendarioDeClases::soloFecha($r->fecha) => true])
            ->all();

        // Cuántos alumnos registraron asistencia ese día: es la señal de que la
        // clase sí se dio, y ayuda a decidir con la hoja en la mano.
        $alumnosPorDia = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->select('horario_id', 'fecha', DB::raw('COUNT(*) as total'))
            ->groupBy('horario_id', 'fecha')
            ->get()
            ->mapWithKeys(fn($r) => [$r->horario_id . '|' . CalendarioDeClases::soloFecha($r->fecha) => (int) $r->total])
            ->all();

        $pendientes = [];

        foreach ($horarios as $horario) {
            $vigenteDesde = CalendarioDeClases::vigenteDesde($horario, $semestre)->format('Y-m-d');
            $fechas = CalendarioDeClases::fechas($horario, $desde, $hasta, $diasInhabiles)['clases'];

            foreach ($fechas as $fecha) {
                $llave = $horario->id . '|' . $fecha;

                if (isset($capturadas[$llave])) {
                    continue;
                }

                $pendientes[] = [
                    'llave'    => $llave,
                    'horario'  => $horario,
                    'fecha'    => $fecha,
                    'alumnos'  => $alumnosPorDia[$llave] ?? 0,
                    // Antes de que el horario existiera en el sistema: no cuenta en el
                    // reporte mientras siga vacía, pero se puede capturar de la hoja.
                    'anterior' => $fecha < $vigenteDesde,
                ];
            }
        }

        usort($pendientes, function ($a, $b) {
            return [$b['fecha'], $a['horario']->hora_inicio] <=> [$a['fecha'], $b['horario']->hora_inicio];
        });

        // Las de antes de que el horario existiera en el sistema no cuentan en el
        // reporte mientras sigan vacías, y solían ser la gran mayoría: las que de
        // verdad faltan se perdían entre ellas. Se ocultan salvo que se pidan.
        $totalAnteriores = count(array_filter($pendientes, fn($p) => $p['anterior']));
        $verAnteriores = $request->boolean('anteriores');

        if (! $verAnteriores) {
            $pendientes = array_values(array_filter($pendientes, fn($p) => ! $p['anterior']));
        }

        return view('admin.monitor.pendientes', [
            'semestre'        => $semestre,
            'pendientes'      => $pendientes,
            'totalAnteriores' => $totalAnteriores,
            'verAnteriores'   => $verAnteriores,
            'ultimaCaptura'   => $this->ultimaCaptura(),
            'desde'      => $desde,
            'hasta'      => $hasta,
            'centros'    => CentroComputo::orderBy('nombre_centro')->get(),
            'profesores' => User::whereIn('id', Horario::where('semestre_id', $semestre->id)->pluck('user_id'))
                ->orderBy('name')->get(),
            'estados'    => self::ESTADOS,
        ]);
    }

    /**
     * Registra el mismo estado del profesor en todas las clases marcadas.
     */
    public function registrar(Request $request)
    {
        $request->validate([
            'clases'        => 'required|array|min:1',
            'clases.*'      => ['string', 'regex:/^\d+\|\d{4}-\d{2}-\d{2}$/'],
            'estado'        => 'required|in:' . implode(',', array_keys(self::ESTADOS)),
            'observaciones' => 'nullable|string|max:255',
        ], [
            'clases.required' => 'Marca al menos una clase.',
            'estado.required' => 'Elige qué se registra: asistió, falta o justificado.',
        ]);

        $semestre = Semestre::where('es_activo', 1)->first();

        if (! $semestre) {
            return back()->with('error', 'No hay un semestre activo.');
        }

        $hoy = now()->format('Y-m-d');
        $registradas = 0;
        $alumnosBorrados = 0;
        $creadas = [];

        DB::transaction(function () use ($request, $semestre, $hoy, &$registradas, &$alumnosBorrados, &$creadas) {
            foreach ($request->clases as $clase) {
                [$horarioId, $fecha] = explode('|', $clase);

                // Sólo clases del semestre activo, dentro de sus fechas y ya pasadas.
                $horario = Horario::where('semestre_id', $semestre->id)->find($horarioId);

                if (! $horario || ! $semestre->contieneFecha($fecha) || $fecha > $hoy) {
                    continue;
                }

                $registro = AsistenciaProfesor::updateOrCreate(
                    ['horario_id' => $horario->id, 'fecha' => $fecha],
                    ['estado' => $request->estado, 'observaciones' => $request->observaciones]
                );

                // Para poder deshacer la captura: sólo lo que se creó aquí
                if ($registro->wasRecentlyCreated) {
                    $creadas[] = $registro->id;
                }

                $registradas++;

                // Igual que en "Clases de hoy": si el profesor faltó o se justificó,
                // esa clase no se dio y las asistencias de alumnos de ese día sobran.
                if (in_array($request->estado, ['falta', 'justificado'])) {
                    // Una por una, para que cada máquina recupere el uso sumado.
                    $alumnosBorrados += Asistencia::borrarUnaPorUna(
                        Asistencia::where('horario_id', $horario->id)
                            ->where('tipo', 'Clase')
                            ->whereDate('fecha', $fecha)
                    );
                }
            }
        });

        if ($registradas === 0) {
            return back()->with('error', 'No se registró ninguna: las clases marcadas son de fechas futuras o fuera del semestre activo.');
        }

        // Se recuerda la captura para ofrecer "Deshacer" durante un rato: una
        // equivocación al marcar muchas (p. ej. "Marcar todas") se corrige de un clic.
        if ($creadas) {
            session()->put(self::SESION_CAPTURA, [
                'ids'              => $creadas,
                'estado'           => $request->estado,
                'user_id'          => auth()->id(),
                'hora'             => now()->toDateTimeString(),
                'alumnos_borrados' => $alumnosBorrados,
            ]);
        }

        $mensaje = 'Se registró "' . self::ESTADOS[$request->estado] . '" en ' . $registradas
            . ($registradas == 1 ? ' clase.' : ' clases.');

        if ($alumnosBorrados > 0) {
            $mensaje .= ' Se ' . ($alumnosBorrados == 1 ? 'quitó 1 asistencia' : 'quitaron ' . $alumnosBorrados . ' asistencias')
                . ' de alumnos de esos días, porque la clase no se dio.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Deshace la última captura: quita los registros del profesor que creó, y esas
     * clases vuelven a estar pendientes.
     *
     * Sólo la de esta misma persona, de la última hora, y sólo los registros que
     * nadie modificó después (mismo estado y sin cambios desde la captura). Lo que
     * no se puede devolver son las asistencias de alumnos que se quitaron al marcar
     * falta o justificado: el aviso de confirmación ya lo advertía.
     */
    public function deshacer()
    {
        $captura = $this->ultimaCaptura();
        session()->forget(self::SESION_CAPTURA);

        if (! $captura) {
            return back()->with('error', 'Ya no hay una captura reciente que deshacer.');
        }

        $quitadas = AsistenciaProfesor::whereIn('id', $captura['ids'])
            ->where('estado', $captura['estado'])
            ->where('updated_at', '<=', $captura['hora'])
            ->delete();

        $mensaje = 'Se deshizo la captura: ' . $quitadas . ($quitadas == 1 ? ' clase volvió' : ' clases volvieron') . ' a estar pendientes.';

        $noTocadas = count($captura['ids']) - $quitadas;
        if ($noTocadas > 0) {
            $mensaje .= ' ' . $noTocadas . ($noTocadas == 1 ? ' no se tocó porque se modificó' : ' no se tocaron porque se modificaron') . ' después.';
        }

        if (($captura['alumnos_borrados'] ?? 0) > 0) {
            $mensaje .= ' Las ' . $captura['alumnos_borrados'] . ' asistencias de alumnos que se quitaron no se pueden recuperar.';
        }

        return back()->with('success', $mensaje);
    }

    /** La última captura de esta persona, si fue en la última hora. */
    protected function ultimaCaptura(): ?array
    {
        $captura = session(self::SESION_CAPTURA);

        if (! is_array($captura)
            || ($captura['user_id'] ?? null) !== auth()->id()
            || Carbon::parse($captura['hora'])->lt(now()->subHour())) {
            return null;
        }

        return $captura;
    }

    /**
     * Rango de fechas a revisar: por omisión, del inicio del semestre a ayer (lo de
     * hoy se captura en "Clases de hoy"). Nunca sale del semestre ni pasa de hoy.
     */
    protected function rango(Request $request, Semestre $semestre): array
    {
        $inicio = Carbon::parse($semestre->fecha_inicio)->format('Y-m-d');
        $tope = min(Carbon::parse($semestre->fecha_fin)->format('Y-m-d'), now()->format('Y-m-d'));

        $desde = $request->input('desde', $inicio);
        $hasta = $request->input('hasta', min($tope, now()->subDay()->format('Y-m-d')));

        $desde = max($inicio, substr((string) $desde, 0, 10));
        $hasta = min($tope, substr((string) $hasta, 0, 10));

        if ($desde > $hasta) {
            $desde = $hasta;
        }

        return [$desde, $hasta];
    }
}
