<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Horario;
use App\Models\CentroComputo;
use App\Models\Asistencia;
use App\Models\Semestre;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\AsistenciaProfesor;
use App\Models\Incidencia;
use App\Support\AgendaSemanal;
use App\Support\EstadoDeLaboratorios;
use App\Support\ListaDelDia;
use App\Support\RegistroDeProfesor;

class EncargadoController extends Controller
{
    /**
     * Inicio del encargado: el panorama del día para operar los laboratorios.
     *
     * Todo es de consulta (el encargado no da altas ni bajas): qué clases hay y
     * cuáles están en curso, cómo están los laboratorios ahora mismo, quién está
     * en uso libre, qué falta registrar, qué fallas hay pendientes y el progreso
     * de la semana. Cada bloque lleva a la pantalla donde se atiende.
     */
    public function inicio()
    {
        $semestreActivo = Semestre::activo();
        $hoy = Carbon::today()->format('Y-m-d');

        // --- La semana y las clases de hoy (el mismo cálculo que el admin) ---
        $semana = AgendaSemanal::de($semestreActivo, Carbon::now());
        $clasesDeHoy = $semana['clases']->where('fecha', $hoy)->values();
        $motivoInhabilHoy = $semana['inhabiles'][$hoy] ?? null;
        $siguienteClase = $clasesDeHoy->firstWhere('estado', 'proxima');

        // En curso por la hora, sin importar si ya se registró al profesor
        $clasesAhora = EstadoDeLaboratorios::clasesEnCurso($semana);

        // --- Los laboratorios en este momento (el mismo cálculo del admin y del Monitor) ---
        $laboratorios = EstadoDeLaboratorios::ahora($clasesAhora);

        // --- Uso libre ---
        $usoLibreHoy = Asistencia::where('tipo', 'Uso Libre')->whereDate('fecha', $hoy)->count();
        $usoLibreAhora = Asistencia::with(['user', 'centroComputo'])
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha', $hoy)
            ->whereNull('fecha_hora_salida')
            ->orderBy('fecha_hora_registro')
            ->get();
        $ultimosUsoLibre = Asistencia::with(['user', 'centroComputo'])
            ->where('tipo', 'Uso Libre')
            ->when($semestreActivo, fn($q) => $q->whereBetween('fecha', [$semestreActivo->fecha_inicio, $semestreActivo->fecha_fin]))
            ->latest('created_at')
            ->take(5)
            ->get();

        // --- Fallas ---
        $fallasPendientes = Incidencia::where('estado', 'pendiente')->count();
        $ultimasFallas = Incidencia::with(['centroComputo', 'user'])
            ->where('estado', 'pendiente')
            ->latest()
            ->take(5)
            ->get();

        return view('encargado.inicio', [
            'semestreActivo'   => $semestreActivo,
            'semana'           => $semana,
            'clasesDeHoy'      => $clasesDeHoy,
            'clasesAhora'      => $clasesAhora,
            'motivoInhabilHoy' => $motivoInhabilHoy,
            'siguienteClase'   => $siguienteClase,
            'laboratorios'     => $laboratorios,
            'pcsOcupadas'      => $laboratorios->sum('ocupadas'),
            'pcsTotales'       => (int) $laboratorios->sum(fn($l) => $l->centro->capacidad),
            'pcsMantenimiento' => $laboratorios->sum('mantenimiento'),
            'usoLibreHoy'      => $usoLibreHoy,
            'usoLibreAhora'    => $usoLibreAhora,
            'ultimosUsoLibre'  => $ultimosUsoLibre,
            'fallasPendientes' => $fallasPendientes,
            'ultimasFallas'    => $ultimasFallas,
            // Las que ya pasaron esta semana sin el registro del profesor
            'porRegistrar'     => $semana['clases']->where('estado', 'por_registrar')->values(),
            'profesoresFaltas' => AsistenciaProfesor::rankingDeFaltas($semestreActivo),
        ]);
    }

    public function index(Request $request)
    {
        if (Auth::user()->rol != 'Encargado') {
            abort(403);
        }

        $centros = CentroComputo::all();

        // 1. CAPTURAR FECHA Y CENTRO (Ahora acepta 'todos' por defecto). Una fecha
        //    inválida escrita en la dirección daba error 500: ahora se usa hoy.
        $fecha = RegistroDeProfesor::fechaValida($request->input('fecha')) ?? Carbon::today()->format('Y-m-d');
        $centroId = is_scalar($request->input('centro_id')) ? $request->input('centro_id') : 'todos';

        // 2. MAPEO DE DÍA BASADO EN LA FECHA SELECCIONADA
        $diasMap = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];
        $diaSeleccionado = $diasMap[Carbon::parse($fecha)->dayOfWeekIso];

        // 3. EL SEMESTRE ACTIVO MANDA: si la fecha consultada cae fuera del periodo
        //    de clases, no hay agenda que mostrar (mismo criterio que los Jobs de correo).
        $semestreActivo = Semestre::activo();
        $fueraDePeriodo = !$semestreActivo || !$semestreActivo->contieneFecha($fecha);

        // 4. OBTENER HORARIOS DE ESE DÍA ESPECÍFICO, DENTRO DEL SEMESTRE ACTIVO
        if ($fueraDePeriodo) {
            $horarios = collect();
        } else {
            $query = Horario::with(['materia', 'user', 'grupo', 'centroComputo'])
                ->where('semestre_id', $semestreActivo->id)
                ->queTocanEl($fecha);

            // Si seleccionó un laboratorio en específico, lo filtramos. Si es 'todos', dejamos pasar todas las clases.
            if ($centroId != 'todos') {
                $query->where('centro_computo_id', $centroId);
            }

            $horarios = $query->orderBy('hora_inicio')->get();
        }

        // 5. Lo registrado del profesor y cuántos alumnos quedaron registrados en cada
        //    clase (antes se consultaba clase por clase dentro de la vista). La
        //    confirmación de "falta" dice cuántas asistencias se quitarían.
        [$reportes, $registrados] = RegistroDeProfesor::delDia($horarios, $fecha);

        return view('encargado.dashboard', [
            'horarios' => $horarios,
            'centros' => $centros,
            'centroSeleccionadoId' => $centroId,
            'fechaSeleccionada' => $fecha,
            'diaNombre' => $diaSeleccionado,
            'semestreActivo' => $semestreActivo,
            'fueraDePeriodo' => $fueraDePeriodo,
            'reportes' => $reportes,
            'registrados' => $registrados,
        ]);
    }

    public function registrarEstadoClase(Request $request, $horario_id)
    {
        $request->validate([
            'estado' => 'required|in:asistio,falta,justificado,retardo',
            'fecha' => 'required|date_format:Y-m-d', // Recibimos la fecha desde el formulario
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'observaciones.max' => 'La nota al docente puede tener hasta 1000 caracteres.',
        ]);

        // Antes una clase que no existe daba error 500
        $horario = Horario::with(['semestre', 'user'])->findOrFail($horario_id);
        $fecha = $request->fecha;

        // Que la clase se dé ese día, dentro de su semestre, y nada de "asistió" por adelantado
        if ($problema = RegistroDeProfesor::problema($horario, $fecha, $request->estado)) {
            return redirect()->back()->with('error', $problema);
        }

        // Registra, avisa al profesor y, si no hubo clase, quita las asistencias de los alumnos
        $quitadas = RegistroDeProfesor::registrar($horario, $fecha, $request->estado, $request->observaciones);

        return redirect()->back()->with('success', 'Estado actualizado para el día ' . Carbon::parse($fecha)->format('d/m/Y') . '.'
            . ($quitadas > 0 ? " Se quitaron las asistencias de {$quitadas} " . ($quitadas === 1 ? 'alumno' : 'alumnos') . ' (la clase no se dio).' : ''));
    }

    /**
     * Muestra la lista detallada de alumnos y su asistencia para una clase específica.
     * NUEVO MÉTODO AGREGADO
     */
    /**
     * Muestra la lista detallada de alumnos y su asistencia para una clase y fecha específica.
     */
    public function verAsistenciaClase(Request $request, $id)
    {
        // 1. Recibir la fecha seleccionada (o usar la de hoy por defecto; una fecha
        //    inválida en la dirección daba error 500)
        $fecha = RegistroDeProfesor::fechaValida($request->input('fecha')) ?? Carbon::today()->format('Y-m-d');

        // 2. Obtener datos de la clase
        $horario = Horario::with(['materia', 'grupo', 'user', 'centroComputo'])->findOrFail($id);

        // 3. Cada alumno inscrito y cómo quedó ese día. Se busca por 'fecha' (el día
        //    de clase) y no por 'fecha_hora_registro' (cuándo se grabó). Quien no
        //    tiene registro ya no sale siempre "Falta": puede que la clase siga en
        //    curso, que no se haya dado o que nadie quedara registrado (ListaDelDia).
        $lista = ListaDelDia::de($horario, $fecha);

        return view('encargado.asistencia_clase', compact('horario', 'lista', 'fecha'));
    }
}
