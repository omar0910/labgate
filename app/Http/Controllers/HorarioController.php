<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use App\Models\User;
use App\Models\Materia;
use App\Models\Grupo;
use App\Models\CentroComputo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Semestre;
use Barryvdh\DomPDF\Facade\Pdf;

class HorarioController extends Controller
{
    public function index(Request $request)
    {
        // 1. GESTIÓN DE FECHA ACTUAL
        $fechaActual = $request->input('fecha', Carbon::now()->format('Y-m-d'));

        $centros = CentroComputo::orderBy('nombre_centro')->get();
        $centroSeleccionadoId = $request->input('centro_id', $centros->first()->id ?? null);

        // ======================================================
        // 0. DETECTAR SEMESTRE ACTIVO
        // ======================================================
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // MODIFICACIÓN AQUÍ:
        // Si no hay semestre activo, cargamos la vista de "Estado Vacío"
        // en lugar de cargar la tabla vacía con errores.
        if (!$semestreActivo) {
            return view('admin.horarios.empty_state');
        }

        // 1. GESTIÓN DE FECHAS DE LA SEMANA
        $fechaReferencia = $request->has('fecha') ? Carbon::parse($request->fecha) : Carbon::now();
        $inicioSemana = $fechaReferencia->copy()->startOfWeek(Carbon::MONDAY);
        $finSemana = $fechaReferencia->copy()->endOfWeek(Carbon::SATURDAY);

        // 2. LA CONSULTA MAESTRA (Filtrada por Semestre)
        $horariosBD = Horario::with(['user', 'materia', 'grupo.semestre', 'centroComputo'])
            ->where('centro_computo_id', $centroSeleccionadoId)
            ->where('semestre_id', $semestreActivo->id)
            ->where(function ($query) use ($inicioSemana, $finSemana) {
                $query->orWhere(function ($q) {
                    $q->whereNull('fecha_especial'); // Recurrentes
                })->orWhereBetween('fecha_especial', [$inicioSemana, $finSemana]); // Especiales
            })
            ->get();

        // 3. GENERAR FILAS DE HORAS
        $horasDisponibles = [];
        for ($h = 7; $h < 22; $h++) {
            $horasDisponibles[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';
        }

        // 4. MAPEO VISUAL
        $mapaHorarios = [];
        foreach ($horariosBD as $horario) {
            $inicio = Carbon::parse($horario->hora_inicio);
            $fin = Carbon::parse($horario->hora_fin);
            $iterador = $inicio->copy();

            while ($iterador < $fin) {
                $horaKey = $iterador->format('H:i:s');
                $dia = $horario->dia_semana;

                $mapaHorarios[$dia][$horaKey] = [
                    'info' => $horario,
                    'es_inicio' => ($horaKey == $inicio->format('H:i:s')),
                    'es_especial' => !is_null($horario->fecha_especial)
                ];
                $iterador->addHour();
            }
        }

        return view('admin.horarios.index', [
            'centros' => $centros,
            'centroSeleccionadoId' => $centroSeleccionadoId,
            'horasDisponibles' => $horasDisponibles,
            'diasSemana' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'mapaHorarios' => $mapaHorarios,
            'fechaActual' => $inicioSemana->format('Y-m-d'),
            'inicioSemana' => $inicioSemana,
            'finSemana' => $finSemana
        ]);
    }

    public function create()
    {
        // Validación preventiva por si intentan entrar por URL directa
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        if (!$semestreActivo) {
            return redirect()->route('semestres.create')->with('warning', 'Primero crea un semestre.');
        }

        $data = $this->getDropdownData();
        return view('admin.horarios.create', $data);
    }

    public function store(Request $request)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        if (!$semestreActivo) {
            return back()->with('error', 'No puedes crear horarios porque no hay un semestre activo.');
        }

        $rules = [
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'materia_id' => 'required|exists:materias,id',
            // Sólo profesores: antes se aceptaba cualquier usuario (hasta un alumno)
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('rol', 'Profesor')],
            'grupo_id' => 'required|exists:grupos,id',
            'hora_inicio' => 'required',
            'hora_fin' => 'required|after:hora_inicio',
            'tipo_reserva' => 'required|in:recurrente,especial',
            'comentario' => 'nullable|string|max:255',
        ];

        if ($request->tipo_reserva === 'recurrente') {
            // Tal cual lo guarda el formulario; otro texto ("lunes", "Miercoles") no
            // lo encontrarían los reportes ni las agendas del día.
            $rules['dia_semana'] = 'required|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo';
        } else {
            $rules['fecha_especial'] = 'required|date|after_or_equal:today';
        }

        $validated = $request->validate($rules);

        $nuevoHorario = new Horario($validated);
        $nuevoHorario->semestre_id = $semestreActivo->id;

        if ($request->tipo_reserva === 'especial') {
            $fecha = Carbon::parse($request->fecha_especial);
            $diasEspanol = [
                'Monday' => 'Lunes',
                'Tuesday' => 'Martes',
                'Wednesday' => 'Miércoles',
                'Thursday' => 'Jueves',
                'Friday' => 'Viernes',
                'Saturday' => 'Sábado',
                'Sunday' => 'Domingo'
            ];
            $nuevoHorario->dia_semana = $diasEspanol[$fecha->format('l')];
            $nuevoHorario->fecha_especial = $request->fecha_especial;
        } else {
            $nuevoHorario->dia_semana = $request->dia_semana;
            $nuevoHorario->fecha_especial = null;
        }

        // El grupo tiene que ser del semestre activo, y la reserva caer dentro de él
        if ($problema = $this->grupoDeOtroSemestre($request->grupo_id, $semestreActivo->id)) {
            return back()->withErrors(['grupo_id' => $problema])->withInput();
        }
        if ($nuevoHorario->fecha_especial && ! $semestreActivo->contieneFecha($nuevoHorario->fecha_especial)) {
            return back()->withErrors(['fecha_especial' => 'La fecha de la reserva queda fuera del semestre activo ('
                . Carbon::parse($semestreActivo->fecha_inicio)->format('d/m/Y') . ' al ' . Carbon::parse($semestreActivo->fecha_fin)->format('d/m/Y') . ').'])->withInput();
        }

        $hayChoque = $this->verificarChoque(
            $request->centro_computo_id,
            $nuevoHorario->dia_semana,
            $request->hora_inicio,
            $request->hora_fin,
            $nuevoHorario->fecha_especial,
            null,
            $semestreActivo->id
        );

        if ($hayChoque) {
            return back()->withErrors(['choque' => $this->mensajeChoque($hayChoque)])->withInput();
        }

        if ($otra = $this->choqueDelProfesor($nuevoHorario)) {
            return back()->withErrors(['choque' => $this->mensajeChoqueProfesor($otra)])->withInput();
        }

        $nuevoHorario->comentario = $request->comentario;
        $nuevoHorario->save();

        // Se vuelve al propio formulario para poder capturar varios horarios seguidos
        // sin tener que entrar de nuevo desde el calendario. Se recuerdan laboratorio,
        // día y grupo porque al cargar un horario casi siempre se repiten.
        return redirect()->route('horarios.create')
            ->with('success', 'Horario creado exitosamente.')
            ->with('ultimo_horario', [
                'centro_computo_id' => $request->centro_computo_id,
                'dia_semana' => $nuevoHorario->dia_semana,
                'grupo_id' => $request->grupo_id,
            ]);
    }

    public function edit(Horario $horario)
    {
        $data = $this->getDropdownData($horario);
        $data['horario'] = $horario;
        return view('admin.horarios.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $horario = Horario::findOrFail($id);

        $rules = [
            'centro_computo_id' => 'required|exists:centro_computos,id',
            'materia_id' => 'required|exists:materias,id',
            // Sólo profesores: antes se aceptaba cualquier usuario (hasta un alumno)
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('rol', 'Profesor')],
            'grupo_id' => 'required|exists:grupos,id',
            'hora_inicio' => 'required',
            'hora_fin' => 'required|after:hora_inicio',
            'tipo_reserva' => 'required|in:recurrente,especial',
            'comentario' => 'nullable|string|max:255',
        ];

        if ($request->tipo_reserva === 'recurrente') {
            // Tal cual lo guarda el formulario; otro texto ("lunes", "Miercoles") no
            // lo encontrarían los reportes ni las agendas del día.
            $rules['dia_semana'] = 'required|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo';
        } else {
            $rules['fecha_especial'] = 'required|date';
        }

        $validated = $request->validate($rules);

        if ($request->tipo_reserva === 'especial') {
            $fecha = Carbon::parse($request->fecha_especial);
            $diasEspanol = [
                'Monday' => 'Lunes',
                'Tuesday' => 'Martes',
                'Wednesday' => 'Miércoles',
                'Thursday' => 'Jueves',
                'Friday' => 'Viernes',
                'Saturday' => 'Sábado',
                'Sunday' => 'Domingo'
            ];
            $horario->dia_semana = $diasEspanol[$fecha->format('l')];
            $horario->fecha_especial = $request->fecha_especial;
        } else {
            $horario->dia_semana = $request->dia_semana;
            $horario->fecha_especial = null;
        }

        $horario->centro_computo_id = $request->centro_computo_id;
        $horario->materia_id = $request->materia_id;
        $horario->user_id = $request->user_id;
        $horario->grupo_id = $request->grupo_id;
        $horario->hora_inicio = $request->hora_inicio;
        $horario->hora_fin = $request->hora_fin;
        $horario->comentario = $request->comentario;

        $hayChoque = $this->verificarChoque(
            $horario->centro_computo_id,
            $horario->dia_semana,
            $horario->hora_inicio,
            $horario->hora_fin,
            $horario->fecha_especial,
            $horario->id,
            $horario->semestre_id
        );

        if ($hayChoque) {
            // El caso más común al reacomodar un horario es querer cambiar dos
            // clases de lugar. Bloquear y ya obligaba a mover una a un hueco
            // libre, mover la otra y regresar la primera; y si el laboratorio
            // estaba lleno, no había hueco donde estacionarla.
            if ((int) $request->input('intercambiar_con') === $hayChoque->id
                && $this->sePuedeIntercambiar($horario, $hayChoque)) {
                return $this->intercambiarLugares($horario, $hayChoque);
            }

            return back()
                ->withErrors(['choque' => $this->mensajeChoque($hayChoque)])
                ->with('intercambio', $this->sePuedeIntercambiar($horario, $hayChoque) ? [
                    'id'           => $hayChoque->id,
                    'descripcion'  => $this->describirClase($hayChoque),
                    'lugar'        => $this->describirLugar($hayChoque),
                    'lugar_actual' => $this->describirLugar($horario, true),
                ] : null)
                ->withInput();
        }

        // Sólo si cambió lo que importa: con datos viejos que ya tuvieran un empalme,
        // no se bloquea editar otra cosa (el comentario, por ejemplo).
        $cambioElGrupo = (int) $horario->getOriginal('grupo_id') !== (int) $horario->grupo_id;
        $cambioProfesorOHora = (int) $horario->getOriginal('user_id') !== (int) $horario->user_id
            || $horario->getOriginal('dia_semana') !== $horario->dia_semana
            || substr((string) $horario->getOriginal('hora_inicio'), 0, 5) !== substr((string) $horario->hora_inicio, 0, 5)
            || substr((string) $horario->getOriginal('hora_fin'), 0, 5) !== substr((string) $horario->hora_fin, 0, 5)
            || (string) $horario->getOriginal('fecha_especial') !== (string) $horario->fecha_especial;

        if ($cambioElGrupo && ($problema = $this->grupoDeOtroSemestre($horario->grupo_id, $horario->semestre_id))) {
            return back()->withErrors(['grupo_id' => $problema])->withInput();
        }

        if ($cambioProfesorOHora && ($otra = $this->choqueDelProfesor($horario))) {
            return back()->withErrors(['choque' => $this->mensajeChoqueProfesor($otra)])->withInput();
        }

        $horario->save();

        return redirect()->route('horarios.index')->with('success', 'Horario actualizado correctamente.');
    }

    /**
     * ¿Tiene sentido ofrecer el intercambio entre estas dos clases?
     *
     * Sólo entre clases fijas de cada semana y del mismo semestre. Con las
     * reservas de un día concreto, "cambiar de lugar" no significa lo mismo y se
     * presta a confusiones, así que ahí se mantiene el aviso de siempre.
     */
    private function sePuedeIntercambiar(Horario $horario, Horario $otro): bool
    {
        return $horario->fecha_especial === null
            && $horario->getOriginal('fecha_especial') === null
            && $otro->fecha_especial === null
            && $horario->semestre_id === $otro->semestre_id
            && $horario->id !== $otro->id;
    }

    /**
     * Cambia de lugar las dos clases, todo o nada.
     *
     * La clase que se está editando se queda con el lugar EXACTO de la otra, y la
     * otra se va al lugar que ésta tenía. Al ser un intercambio entre dos lugares
     * que ya existían, no puede aparecer un empalme nuevo con una tercera clase.
     * Los demás cambios del formulario (materia, grupo, docente, comentario) se
     * guardan igual.
     */
    private function intercambiarLugares(Horario $horario, Horario $otro)
    {
        $lugarPropio = [
            'centro_computo_id' => $horario->getOriginal('centro_computo_id'),
            'dia_semana'        => $horario->getOriginal('dia_semana'),
            'hora_inicio'       => $horario->getOriginal('hora_inicio'),
            'hora_fin'          => $horario->getOriginal('hora_fin'),
        ];

        $horario->centro_computo_id = $otro->centro_computo_id;
        $horario->dia_semana        = $otro->dia_semana;
        $horario->hora_inicio       = $otro->hora_inicio;
        $horario->hora_fin          = $otro->hora_fin;

        DB::transaction(function () use ($horario, $otro, $lugarPropio) {
            $horario->save();
            $otro->fill($lugarPropio)->save();
        });

        return redirect()->route('horarios.index')->with(
            'success',
            'Se intercambiaron los lugares: ' . $this->describirClase($horario) . ' quedó en '
                . $this->describirLugar($horario) . ', y ' . $this->describirClase($otro)
                . ' pasó a ' . $this->describirLugar($otro) . '.'
        );
    }

    /** "Cálculo Diferencial (1SM)", para los avisos. */
    private function describirClase(Horario $horario): string
    {
        $materia = $horario->materia->nombre_materia ?? 'la clase';
        $grupo   = $horario->grupo->nombre_grupo ?? null;

        return $grupo ? $materia . ' (' . $grupo . ')' : $materia;
    }

    /** "Lunes de 10:00 a 11:00 en CAD 1". Con $original, el lugar que tenía guardado. */
    private function describirLugar(Horario $horario, bool $original = false): string
    {
        $dia    = $original ? $horario->getOriginal('dia_semana') : $horario->dia_semana;
        $inicio = $original ? $horario->getOriginal('hora_inicio') : $horario->hora_inicio;
        $fin    = $original ? $horario->getOriginal('hora_fin') : $horario->hora_fin;
        $centroId = $original ? $horario->getOriginal('centro_computo_id') : $horario->centro_computo_id;

        $centro = CentroComputo::find($centroId);

        return $dia . ' de ' . Carbon::parse($inicio)->format('H:i')
            . ' a ' . Carbon::parse($fin)->format('H:i')
            . ($centro ? ' en ' . $centro->nombre_centro : '');
    }

    public function destroy(Horario $horario)
    {
        $horario->delete();
        return redirect()->route('horarios.index')->with('success', 'Horario eliminado exitosamente.');
    }

    /**
     * Papelera, por semestre y laboratorio.
     *
     * Por omisión enseña el semestre activo: restaurar una clase de un semestre
     * pasado la devuelve a ESE semestre, y en el calendario de hoy no se vería.
     */
    public function papelera(Request $request)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // 'todos' enseña cualquier semestre; si no se dice nada, el activo.
        $semestreId = $request->input('semestre', $semestreActivo->id ?? 'todos');
        $centroId = $request->input('centro');

        $consulta = Horario::onlyTrashed()
            ->with(['materia', 'user', 'grupo', 'centroComputo', 'semestre'])
            ->withCount(['asistencias', 'asistenciasProfesor'])
            ->orderBy('deleted_at', 'desc');

        if ($semestreId !== 'todos') {
            $consulta->where('semestre_id', $semestreId);
        }

        if ($centroId) {
            $consulta->where('centro_computo_id', $centroId);
        }

        return view('admin.horarios.papelera', [
            'horariosBorrados' => $consulta->get(),
            'semestres'        => Semestre::orderBy('id', 'desc')->get(),
            'centros'          => CentroComputo::orderBy('nombre_centro')->get(),
            'semestreId'       => $semestreId,
            'centroId'         => $centroId,
            'semestreActivo'   => $semestreActivo,
            'totalEnPapelera'  => Horario::onlyTrashed()->count(),
        ]);
    }

    public function restaurar($id)
    {
        $horario = Horario::withTrashed()->findOrFail($id);

        $choque = $this->choqueAlRestaurar($horario);

        if ($choque) {
            return back()->with('error', 'No se restauró ' . $this->describirClase($horario) . ': '
                . $this->mensajeChoque($choque) . ' Mueve o elimina esa clase primero.');
        }

        $horario->restore();

        return back()->with('success', 'Se restauró ' . $this->describirClase($horario) . ' en '
            . $this->describirLugar($horario) . $this->avisoDeSemestre($horario) . '.');
    }

    /**
     * Restaurar o eliminar para siempre varias a la vez.
     *
     * Se hace una por una y se informa de cada una que no se pudo, en lugar de
     * detener todo por la primera: así, de diez clases que el importador mandó a
     * la papelera, se restauran las nueve que caben y se dice cuál estorba.
     */
    public function accionesPapelera(Request $request)
    {
        $request->validate([
            'accion'  => 'required|in:restaurar,eliminar',
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer',
        ], [
            'ids.required' => 'Marca al menos un horario.',
        ]);

        $horarios = Horario::onlyTrashed()->with(['materia', 'grupo'])->whereIn('id', $request->ids)->get();

        $hechos = 0;
        $fallos = [];

        foreach ($horarios as $horario) {
            if ($request->accion === 'restaurar') {
                // Se revisa contra lo que ya está en el calendario, incluidas las que
                // se acaban de restaurar en esta misma vuelta.
                $choque = $this->choqueAlRestaurar($horario);

                if ($choque) {
                    $fallos[] = $this->describirClase($horario) . ' — ' . $this->mensajeChoque($choque);
                    continue;
                }

                $horario->restore();
                $hechos++;
                continue;
            }

            if (! $horario->sePuedeEliminarDefinitivamente()) {
                $fallos[] = $this->describirClase($horario) . ' — tiene asistencias registradas y se conserva.';
                continue;
            }

            $horario->forceDelete();
            $hechos++;
        }

        $verbo = $request->accion === 'restaurar' ? 'restauraron' : 'eliminaron para siempre';
        $redireccion = back();

        if ($hechos > 0) {
            $redireccion = $redireccion->with('success', "Se {$verbo} {$hechos} " . ($hechos == 1 ? 'horario' : 'horarios') . '.');
        }

        if (! empty($fallos)) {
            $redireccion = $redireccion->with('fallos', $fallos);
        }

        return $redireccion;
    }

    public function eliminarDefinitivo($id)
    {
        $horario = Horario::onlyTrashed()->findOrFail($id);

        if (! $horario->sePuedeEliminarDefinitivamente()) {
            return back()->with('error', 'No se eliminó ' . $this->describirClase($horario)
                . ': tiene asistencias registradas. Se conserva en la papelera para no perder ese historial.');
        }

        $descripcion = $this->describirClase($horario);
        $horario->forceDelete();

        return back()->with('success', 'Se eliminó para siempre ' . $descripcion . '.');
    }

    /** La clase del calendario que ocupa hoy el lugar de ésta, si hay alguna. */
    private function choqueAlRestaurar(Horario $horario)
    {
        return $this->verificarChoque(
            $horario->centro_computo_id,
            $horario->dia_semana,
            $horario->hora_inicio,
            $horario->hora_fin,
            $horario->fecha_especial,
            $horario->id,
            $horario->semestre_id
        );
    }

    /** Si la clase restaurada es de otro semestre, se dice: en el calendario de hoy no se verá. */
    private function avisoDeSemestre(Horario $horario): string
    {
        $activo = Semestre::where('es_activo', 1)->value('id');

        if ($activo && (int) $horario->semestre_id !== (int) $activo) {
            $nombre = Semestre::where('id', $horario->semestre_id)->value('nombre') ?? 'otro semestre';
            return ' (semestre ' . $nombre . ', no el activo: no aparecerá en el calendario actual)';
        }

        return '';
    }

    private function getDropdownData(?Horario $horario = null)
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();
        $gruposQuery = Grupo::with('semestre')->orderBy('nombre_grupo');

        if ($semestreActivo) {
            $gruposQuery->where('semestre_id', $semestreActivo->id);
        }

        return [
            // Sólo activos; al editar, también el profesor que ya tiene la clase aunque
            // esté dado de baja: si no saliera en la lista, el formulario lo cambiaría
            // sin avisar por el primero de la lista.
            'profesores' => User::where('rol', 'Profesor')
                ->where(function ($q) use ($horario) {
                    $q->where('activo', true);
                    if ($horario) {
                        $q->orWhere('id', $horario->user_id);
                    }
                })
                ->orderBy('name')->get(),
            'materias' => Materia::orderBy('nombre_materia')->get(),
            'grupos' => $gruposQuery->get(),
            'centros' => CentroComputo::orderBy('nombre_centro')->get(),
        ];
    }

    /**
     * Devuelve el horario con el que se empalma la reserva, o null si el espacio
     * está libre. Antes regresaba solo true/false, y por eso el aviso no podía
     * decir con qué clase chocaba.
     */
    private function verificarChoque($centro_id, $dia, $hora_inicio, $hora_fin, $fecha_especial = null, $horario_id = null, $semestre_id = null)
    {
        $query = Horario::with(['materia', 'grupo', 'user'])
            ->where('centro_computo_id', $centro_id)
            ->where('dia_semana', $dia)
            ->where(function ($q) use ($hora_inicio, $hora_fin) {
                $q->where('hora_inicio', '<', $hora_fin)
                    ->where('hora_fin', '>', $hora_inicio);
            });

        if ($semestre_id) {
            $query->where('semestre_id', $semestre_id);
        }

        if ($horario_id) {
            $query->where('id', '!=', $horario_id);
        }

        if ($fecha_especial) {
            $query->where(function ($q) use ($fecha_especial) {
                $q->where('fecha_especial', $fecha_especial)
                    ->orWhereNull('fecha_especial');
            });
        } else {
            // Una clase fija choca con las demás fijas y con las reservas de un día
            // que aún no pasan. Antes también con las que ya pasaron: una reserva de
            // hace meses en ese día y hora impedía crear la clase para siempre.
            $query->where(function ($q) {
                $q->whereNull('fecha_especial')
                    ->orWhereDate('fecha_especial', '>=', Carbon::today()->toDateString());
            });
        }

        return $query->first();
    }

    /**
     * Otra clase del mismo profesor a la misma hora (en otro laboratorio), o null.
     * El choque de laboratorio sólo revisaba el salón: se le podían poner dos
     * clases simultáneas a un profesor en laboratorios distintos.
     */
    private function choqueDelProfesor(Horario $horario)
    {
        return Horario::with(['materia', 'grupo', 'centroComputo'])
            ->where('user_id', $horario->user_id)
            ->where('semestre_id', $horario->semestre_id)
            ->where('dia_semana', $horario->dia_semana)
            ->where('hora_inicio', '<', $horario->hora_fin)
            ->where('hora_fin', '>', $horario->hora_inicio)
            ->when($horario->id, fn($q) => $q->where('id', '!=', $horario->id))
            // Fija contra fija, y reserva contra otra reserva del mismo día. Una
            // reserva a la hora de su propia clase fija suele ser esa clase movida
            // a otro laboratorio por un día: eso no se bloquea.
            ->when(
                $horario->fecha_especial,
                fn($q) => $q->whereDate('fecha_especial', $horario->fecha_especial),
                fn($q) => $q->whereNull('fecha_especial')
            )
            ->first();
    }

    private function mensajeChoqueProfesor(Horario $otra): string
    {
        return 'El profesor ya tiene otra clase a esa hora: ' . $this->describirClase($otra) . ', '
            . $this->describirLugar($otra) . '. Un profesor no puede estar en dos laboratorios a la vez.';
    }

    /** El grupo tiene que ser del mismo semestre que la clase. */
    private function grupoDeOtroSemestre($grupoId, $semestreId): ?string
    {
        $grupo = Grupo::with('semestre')->find($grupoId);

        return $grupo && (int) $grupo->semestre_id !== (int) $semestreId
            ? 'El grupo «' . $grupo->nombre_grupo . '» es del semestre «' . ($grupo->semestre->nombre ?? 'otro') . '», no del de esta clase.'
            : null;
    }

    /**
     * Arma el aviso de empalme con los datos de la clase que ya ocupa el espacio,
     * para no tener que ir al calendario a averiguar cuál es.
     */
    private function mensajeChoque($horarioEnConflicto)
    {
        $materia = $horarioEnConflicto->materia->nombre_materia ?? 'otra clase';
        $grupo = $horarioEnConflicto->grupo->nombre_grupo ?? null;
        $profesor = $horarioEnConflicto->user->name ?? null;

        $inicio = \Carbon\Carbon::parse($horarioEnConflicto->hora_inicio)->format('H:i');
        $fin = \Carbon\Carbon::parse($horarioEnConflicto->hora_fin)->format('H:i');

        $detalle = $materia;
        if ($grupo) {
            $detalle .= ' (' . $grupo . ')';
        }
        if ($profesor) {
            $detalle .= ', con ' . $profesor;
        }

        return 'El laboratorio ya está ocupado de ' . $inicio . ' a ' . $fin . ' por: ' . $detalle . '.';
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'centros_ids' => 'required|array',
            'centros_ids.*' => 'exists:centro_computos,id',
            'fecha_actual' => 'required|date'
        ]);

        $semestreActivo = Semestre::where('es_activo', 1)->first();

        if (!$semestreActivo) {
            return back()->with('error', 'No hay semestre activo para exportar.');
        }

        // Determinar las fechas de la semana seleccionada
        $fechaReferencia = Carbon::parse($request->fecha_actual);
        $inicioSemana = $fechaReferencia->copy()->startOfWeek(Carbon::MONDAY);
        $finSemana = $fechaReferencia->copy()->endOfWeek(Carbon::SATURDAY);

        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        // Generar horas
        $horasDisponibles = [];
        for ($h = 7; $h < 22; $h++) {
            $horasDisponibles[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
        }

        $datosPorCentro = [];
        $centros = CentroComputo::whereIn('id', $request->centros_ids)->orderBy('nombre_centro')->get();

        foreach ($centros as $centro) {
            $horariosBD = Horario::with(['user', 'materia', 'grupo.semestre'])
                ->where('centro_computo_id', $centro->id)
                ->where('semestre_id', $semestreActivo->id)
                ->where(function ($query) use ($inicioSemana, $finSemana) {
                    $query->orWhere(function ($q) {
                        $q->whereNull('fecha_especial');
                    })->orWhereBetween('fecha_especial', [$inicioSemana, $finSemana]);
                })
                ->get();

            $mapaHorarios = [];
            foreach ($horariosBD as $horario) {
                $inicio = Carbon::parse($horario->hora_inicio);
                $fin = Carbon::parse($horario->hora_fin);
                $iterador = $inicio->copy();

                while ($iterador < $fin) {
                    $horaKey = $iterador->format('H:i');
                    $dia = $horario->dia_semana;

                    $mapaHorarios[$dia][$horaKey] = $horario;
                    $iterador->addHour();
                }
            }

            $datosPorCentro[] = [
                'centro' => $centro,
                'mapa' => $mapaHorarios
            ];
        }

        // Cada laboratorio en UNA hoja: con nombres largos las celdas crecen y la
        // tabla se pasaba a una segunda. Se busca, laboratorio por laboratorio, el
        // ajuste más holgado con el que cabe (ver los estilos .ajuste-N de la vista).
        $comunes = compact('diasSemana', 'horasDisponibles', 'inicioSemana', 'finSemana', 'semestreActivo');
        foreach ($datosPorCentro as $i => $dato) {
            $datosPorCentro[$i]['ajuste'] = $this->ajusteParaUnaHoja($dato, $comunes);
        }

        // Cargar la vista y configurar el papel en Horizontal (Landscape)
        $pdf = Pdf::loadView('admin.horarios.pdf', compact(
            'datosPorCentro',
            'diasSemana',
            'horasDisponibles',
            'inicioSemana',
            'finSemana',
            'semestreActivo'
        ))->setPaper('letter', 'landscape');

        // --- LÓGICA PARA EL NOMBRE DEL ARCHIVO ---

        $totalCentrosBD = CentroComputo::count();
        $cantidadSeleccionados = $centros->count();

        // 1. Determinar qué poner en la parte de los laboratorios
        if ($cantidadSeleccionados == $totalCentrosBD) {
            $nombresLabs = 'Todos_Los_Labs';
        } elseif ($cantidadSeleccionados > 3) {
            $nombresLabs = 'Multiples_Labs';
        } else {
            // Unimos los nombres quitando espacios (Ej: "Laboratorio CAD" -> "laboratoriocad")
            $nombresLabs = $centros->pluck('nombre_centro')->map(function ($nombre) {
                return \Illuminate\Support\Str::slug($nombre, '');
            })->implode('_');
        }

        // 2. Limpiamos el nombre del semestre para que sea seguro en URLs/Archivos
        // (Ej: "Agosto 25 - Enero 26" -> "agosto-25-enero-26")
        $semestreLimpio = \Illuminate\Support\Str::slug($semestreActivo->nombre, '_');

        // 3. Fecha actual de descarga
        $fechaHoy = now()->format('d-m-Y');

        // 4. Ensamblamos el nombre final
        $nombreArchivo = "Horarios_{$nombresLabs}_{$semestreLimpio}_{$fechaHoy}.pdf";

        // Retornamos la descarga con el nuevo nombre
        return $pdf->download($nombreArchivo);
    }

    /** Cuántos ajustes de tamaño tiene la vista del PDF (.ajuste-0 a .ajuste-6). */
    const AJUSTES_DEL_PDF = 7;

    /**
     * El ajuste más holgado con el que el horario de un laboratorio cabe en una hoja.
     *
     * No se calcula a ojo: se arma el PDF de ese laboratorio solo y se cuentan sus
     * hojas. Primero el diseño normal (0), que es lo que casi siempre alcanza; si
     * no, se busca por mitades entre los demás, para no armar el PDF siete veces.
     * Si ni el más apretado alcanza, se usa ése: es lo más cerca de una hoja.
     */
    protected function ajusteParaUnaHoja(array $dato, array $comunes): int
    {
        // Sin clases, la tabla vacía cabe de sobra
        if (empty($dato['mapa'])) {
            return 0;
        }

        $cabe = function (int $ajuste) use ($dato, $comunes) {
            $prueba = Pdf::loadView('admin.horarios.pdf', $comunes + [
                'datosPorCentro' => [$dato + ['ajuste' => $ajuste]],
            ])->setPaper('letter', 'landscape');

            $prueba->render();

            return $prueba->getDomPDF()->getCanvas()->get_page_count() <= 1;
        };

        if ($cabe(0)) {
            return 0;
        }

        // El menor ajuste que cabe: cada uno es más apretado que el anterior, así
        // que si uno cabe, los siguientes también.
        $bajo = 1;
        $alto = self::AJUSTES_DEL_PDF - 1;

        while ($bajo < $alto) {
            $medio = intdiv($bajo + $alto, 2);

            if ($cabe($medio)) {
                $alto = $medio;
            } else {
                $bajo = $medio + 1;
            }
        }

        return $bajo;
    }
}
