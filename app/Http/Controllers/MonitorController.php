<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CentroComputo;
use App\Models\Asistencia;
use App\Models\Incidencia;
use App\Models\Horario;
use App\Models\User;
use App\Services\AvisosCorreo;
use Carbon\Carbon;
use App\Models\Equipo;

class MonitorController extends Controller
{
    /**
     * Muestra la lista de laboratorios para elegir cuál monitorear.
     */
    public function index()
    {
        $centros = CentroComputo::all();

        // Cada laboratorio con su ocupación de este momento (antes sólo se veía la
        // capacidad): el mismo cálculo del inicio del admin y del encargado.
        $laboratorios = \App\Support\EstadoDeLaboratorios::ahora();

        return view('admin.monitor.index', compact('centros', 'laboratorios'));
    }

    /**
     * Muestra el MAPA en tiempo real de un laboratorio específico.
     */
    public function show($id)
    {
        $centro = CentroComputo::findOrFail($id);

        // ==========================================================
        // CORRECCIÓN DEFINITIVA DE HORA: Forzamos la zona horaria aquí
        // para ignorar cualquier caché del servidor.
        // ==========================================================
        $zonaHoraria = 'America/Hermosillo'; // UTC-7 estricto sin Horario de Verano
        $today = Carbon::today($zonaHoraria);
        $now = Carbon::now($zonaHoraria);

        // ==========================================================
        // 1. LIMPIEZA AUTOMÁTICA (Auto-Cierre de Clases Vencidas)
        // ==========================================================
        $clasesAbiertas = Asistencia::with('horario')
            ->where('centro_computo_id', $id)
            ->whereDate('fecha', $today)
            ->where('tipo', 'Clase')
            ->whereNull('fecha_hora_salida')
            ->get();

        foreach ($clasesAbiertas as $asistencia) {
            if ($asistencia->horario) {
                // Forzamos también la zona horaria al evaluar la hora fin
                $horaFinClase = Carbon::parse($asistencia->horario->hora_fin, $zonaHoraria)->setDateFrom($today);

                // Si AHORA es más tarde que el fin de la clase...
                if ($now->gt($horaFinClase)) {
                    $asistencia->fecha_hora_salida = $horaFinClase->toDateTimeString();
                    $asistencia->save();
                }
            }
        }

        // ==========================================================
        // 2. DETECTAR CLASE ACTUAL (Para el botón de Liberar)
        // ==========================================================
        $semestreActivo = \App\Models\Semestre::where('es_activo', 1)->first();

        $claseActual = Horario::with(['materia', 'user'])
            ->where('centro_computo_id', $id)
            // Las fijas de hoy y las reservas especiales de hoy (no las de otro día).
            // La comparación del día ya ignora acentos: la columna usa utf8mb4_unicode_ci.
            ->queTocanEl($now)
            ->when($semestreActivo, function ($query) use ($semestreActivo) {
                return $query->where('semestre_id', $semestreActivo->id);
            })
            ->whereTime('hora_inicio', '<=', $now->format('H:i:s'))
            ->whereTime('hora_fin', '>=', $now->format('H:i:s'))
            ->first();

        // ==========================================================
        // 3. Obtener Incidencias y Asistencias Activas
        // ==========================================================
        $incidencias = Incidencia::where('centro_computo_id', $id)
            ->where('estado', 'pendiente')
            ->get()
            ->keyBy('numero_maquina');

        $ocupadas = Asistencia::with(['user', 'horario.materia'])
            ->where('centro_computo_id', $id)
            ->whereDate('fecha', $today)
            ->where(function ($query) {
                $query->where('tipo', 'Uso Libre')
                    ->whereNull('fecha_hora_salida');
            })
            ->orWhere(function ($query) use ($id, $today) {
                $query->where('centro_computo_id', $id)
                    ->whereDate('fecha', $today)
                    ->where('tipo', 'Clase')
                    ->whereNull('fecha_hora_salida')
                    ->whereNotNull('numero_maquina');   // los de equipo personal no ocupan PC
            })
            ->get()
            ->keyBy('numero_maquina');

        // En clase ahora con su laptop: no salen en el mapa de PCs, se listan aparte
        $conEquipoPersonal = Asistencia::with('user')
            ->where('centro_computo_id', $id)
            ->whereDate('fecha', $today)
            ->where('tipo', 'Clase')
            ->where('equipo_personal', true)
            ->where('estado', 'presente')
            ->whereNull('fecha_hora_salida')
            ->get();

        $totalMaquinas = $centro->capacidad;

        // ==========================================================
        // 4. RETORNO FINAL
        // ==========================================================
        return view('admin.monitor.show', compact('centro', 'incidencias', 'ocupadas', 'totalMaquinas', 'claseActual', 'conEquipoPersonal'));
    }

    /**
     * Fuerza el cierre de sesión de una máquina específica.
     */
    public function forzarSalida(Request $request, $centro_id, $numero_maquina)
    {
        $now = Carbon::now();
        $today = Carbon::today();

        // Buscamos la asistencia ACTIVA en esa máquina hoy
        $asistencia = Asistencia::where('centro_computo_id', $centro_id)
            ->where('numero_maquina', $numero_maquina)
            ->whereDate('fecha', $today)
            ->whereNull('fecha_hora_salida')
            ->first();

        if ($asistencia) {
            $asistencia->fecha_hora_salida = $now->toDateTimeString();
            $asistencia->save();

            // Sólo el uso libre lleva resumen de salida; los registros de clase no
            // tienen hora de salida que reportar.
            if ($asistencia->tipo === 'Uso Libre' && $asistencia->user) {
                AvisosCorreo::salidaUsoLibre($asistencia->user, $asistencia);
            }

            return back()->with('success', "Se cerró la sesión de la PC #$numero_maquina correctamente.");
        }

        return back()->with('error', "No se encontró una sesión activa en la PC #$numero_maquina.");
    }


    // =========================================================================
    // NUEVAS FUNCIONES PARA ASIGNAR USO LIBRE DESDE EL MONITOR
    // =========================================================================

    /**
     * Busca a un alumno por matrícula (usado mediante AJAX/Fetch en la vista).
     */
    public function buscarAlumnoPorMatricula(Request $request)
    {
        $matricula = $request->query('matricula');

        if (!$matricula) {
            return response()->json(['encontrado' => false]);
        }

        // Buscamos al usuario que tenga rol 'Alumno' y esa matrícula
        $alumno = User::where('rol', 'Alumno')
            ->where('matricula', $matricula)
            ->activos()   // a uno dado de baja no se le asigna computadora
            ->first();

        if ($alumno) {
            return response()->json([
                'encontrado' => true,
                'id' => $alumno->id,
                'nombre' => $alumno->name,
                'apellidos' => $alumno->apellido_paterno . ' ' . $alumno->apellido_materno,
                'carrera' => $alumno->carrera ?? 'Sin carrera especificada'
            ]);
        }

        return response()->json(['encontrado' => false]);
    }

    /**
     * Registra manualmente a un alumno en Uso Libre en una PC específica.
     */
    public function asignarUsoLibre(Request $request)
    {
        $request->validate([
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'numero_maquina' => 'required|integer|min:1',
            // Sólo alumnos activos (antes se aceptaba cualquier usuario)
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('rol', 'Alumno')->where('activo', true)],
        ], [
            'user_id.exists' => 'Ese usuario no es un alumno activo.',
        ]);

        if ($problema = $this->pcFueraDelLaboratorio($request->centro_computo_id, $request->numero_maquina)) {
            return back()->with('error', $problema);
        }

        // ---  EL ESCUDO DE MANTENIMIENTO  ---
        $equipoFisico = Equipo::where('centro_computo_id', $request->centro_computo_id)
            ->where('numero_maquina', $request->numero_maquina)
            ->first();

        if ($equipoFisico && $equipoFisico->estado !== 'disponible') {
            return back()->with('error', '¡Acceso Denegado! La PC #' . $request->numero_maquina . ' se encuentra bloqueada por mantenimiento o falla técnica.');
        }
        // ------------------------------------------

        $hoy = Carbon::today()->toDateString();
        $ahora = Carbon::now();

        // 1. Verificamos que la PC no esté ya ocupada (por otro alumno). Mismo
        //    criterio que el alumno: una clase ya terminada no la deja ocupada.
        $ocupada = Asistencia::estaOcupada($request->centro_computo_id, $request->numero_maquina);

        if ($ocupada) {
            return back()->with('error', 'La PC #' . $request->numero_maquina . ' ya fue ocupada mientras realizabas la asignación.');
        }

        // 2. Verificamos que EL ALUMNO no tenga ya una sesión de USO LIBRE activa.
        //    Se filtra por tipo a propósito: los registros de clase se guardan sin
        //    hora de salida (al pasar lista no se marca salida), así que sin este
        //    filtro cualquier alumno con lista pasada ese día quedaba bloqueado.
        //    Es el mismo criterio que usa el alumno al registrarse por su cuenta.
        $alumnoOcupado = Asistencia::where('user_id', $request->user_id)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha', $hoy)
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($alumnoOcupado) {
            return back()->with('error', 'El alumno seleccionado ya tiene una sesión activa en otro equipo o laboratorio.');
        }

        // 3. Registramos la entrada
        $sesion = Asistencia::create([
            'user_id' => $request->user_id,
            'centro_computo_id' => $request->centro_computo_id,
            'numero_maquina' => $request->numero_maquina,
            'horario_id' => null, // Es uso libre
            'tipo' => 'Uso Libre',
            'estado' => 'presente',
            'fecha' => $hoy,
            'fecha_hora_registro' => $ahora,
        ]);

        // El alumno recibe los datos del equipo y el recordatorio de cerrar sesión,
        // igual que cuando se registra él mismo desde su panel.
        $alumno = User::find($request->user_id);
        if ($alumno) {
            AvisosCorreo::entradaUsoLibre($alumno, $sesion);
        }

        return back()->with('success', 'Se ha registrado a ' . ($alumno->name ?? 'el alumno') . ' en la PC #' . $request->numero_maquina . ' correctamente.');
    }

    /**
     * Registra manualmente a un alumno en Uso Libre de forma retroactiva (Bitácora)
     */
    public function asignarUsoLibreHistorico(Request $request)
    {
        $request->validate([
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'numero_maquina' => 'required|integer|min:1',
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('rol', 'Alumno')],
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            // Una hora inválida tronaba al armar la fecha y hora (error 500)
            'hora_entrada' => 'required|date_format:H:i',
            'hora_salida' => 'required|date_format:H:i|after:hora_entrada',
        ], [
            'user_id.exists' => 'Ese usuario no es un alumno.',
            'hora_entrada.date_format' => 'La hora de entrada no es válida.',
            'hora_salida.date_format' => 'La hora de salida no es válida.',
        ]);

        if ($problema = $this->pcFueraDelLaboratorio($request->centro_computo_id, $request->numero_maquina)) {
            return back()->with('error', $problema);
        }

        // Aquí NO se revisa si la PC está hoy en mantenimiento: es un registro de
        // otro día (de las hojas de papel), y lo que le pase hoy al equipo no
        // cambia que ese día sí se usó. Antes se rechazaba por eso.

        // Combinar la fecha con las horas para crear timestamps exactos
        $fechaHoraEntrada = Carbon::parse($request->fecha . ' ' . $request->hora_entrada)->toDateTimeString();
        $fechaHoraSalida = Carbon::parse($request->fecha . ' ' . $request->hora_salida)->toDateTimeString();

        // Registramos en la bitácora
        Asistencia::create([
            'user_id' => $request->user_id,
            'centro_computo_id' => $request->centro_computo_id,
            'numero_maquina' => $request->numero_maquina,
            'horario_id' => null, // Es uso libre
            'tipo' => 'Uso Libre',
            'estado' => 'presente',
            'fecha' => $request->fecha,
            'fecha_hora_registro' => $fechaHoraEntrada,
            'fecha_hora_salida' => $fechaHoraSalida, // Ya tiene hora de salida confirmada
        ]);

        return back()->with('success', 'El registro de bitácora se guardó correctamente para la PC #' . $request->numero_maquina . '.');
    }

    public function enviarMantenimiento(Request $request)
    {
        $request->validate([
            // Antes sin revisar: un laboratorio inexistente tronaba al crear el ticket
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'numero_maquina' => 'required|integer|min:1',
        ]);

        if ($problema = $this->pcFueraDelLaboratorio($request->centro_computo_id, $request->numero_maquina)) {
            return back()->with('error', $problema);
        }

        // Si ya tiene un mantenimiento abierto, no se abre otro ticket igual
        $yaAbierto = Incidencia::where('centro_computo_id', $request->centro_computo_id)
            ->where('numero_maquina', $request->numero_maquina)
            ->where('estado', 'pendiente')
            ->where('categoria', 'like', '%Mantenimiento%')
            ->exists();

        // 1. Cambiamos el estado físico del equipo
        $equipo = Equipo::where('centro_computo_id', $request->centro_computo_id)
            ->where('numero_maquina', $request->numero_maquina)
            ->first();

        if ($equipo) {
            $equipo->estado = 'mantenimiento';
            $equipo->save();
        }

        if ($yaAbierto) {
            return redirect()->back()->with('success', 'La PC #' . $request->numero_maquina . ' ya estaba en mantenimiento: sigue bloqueada con su ticket abierto en la Mesa de Ayuda.');
        }

        // 2. Creamos el Ticket en la Mesa de Ayuda a nombre de quien lo bloqueó
        Incidencia::create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(), // El Admin o Encargado actual
            'centro_computo_id' => $request->centro_computo_id,
            'numero_maquina' => $request->numero_maquina,
            'categoria' => 'Mantenimiento Preventivo (Manual)',
            'descripcion' => 'Bloqueo manual desde el Monitor Operativo para revisión y limpieza de este equipo.',
            'estado' => 'pendiente',
        ]);

        return redirect()->back()->with('success', 'La PC #' . $request->numero_maquina . ' ha sido bloqueada y enviada a la Mesa de Ayuda.');
    }

    /**
     * Por qué ese número de PC no existe en ese laboratorio, o null. Antes se
     * aceptaba cualquier número (una PC #999 en un laboratorio de 25).
     */
    private function pcFueraDelLaboratorio($centroId, $numeroMaquina): ?string
    {
        $centro = CentroComputo::find($centroId);

        return $centro && $centro->capacidad && (int) $numeroMaquina > $centro->capacidad
            ? 'La PC #' . $numeroMaquina . ' no existe: ' . $centro->nombre_centro . ' tiene ' . $centro->capacidad . ' equipos.'
            : null;
    }
}
