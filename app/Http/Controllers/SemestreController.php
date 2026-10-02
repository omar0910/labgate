<?php

namespace App\Http\Controllers;

use App\Models\Semestre;
use App\Support\Busqueda;
use Illuminate\Http\Request;

class SemestreController extends Controller
{
    public function index(Request $request)
    {
        // Ordenamos: primero los activos, luego por fecha de inicio.
        $consultaBase = function () {
            return Semestre::query()
                ->orderBy('es_activo', 'desc')
                ->orderBy('fecha_inicio', 'desc');
        };

        // Palabra por palabra: "agosto 2025" encuentra "Agosto 2025 - Enero 2026".
        $semestres = Busqueda::paginar(
            $consultaBase,
            $request->input('search'),
            ['nombre'],
            10
        );

        return view('admin.semestres.index', ['semestres' => $semestres]);
    }

    public function create()
    {
        return view('admin.semestres.create');
    }

    public function store(Request $request)
    {
        // 1. VALIDACIÓN (Aquí evitamos el error de duplicado)
        $request->validate([
            'nombre' => 'required|string|max:255|unique:semestres,nombre',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'es_activo' => 'nullable'
        ], [
            'nombre.unique' => 'Ya existe un semestre registrado con ese nombre.'
        ]);

        if ($otro = $this->encimado($request->fecha_inicio, $request->fecha_fin)) {
            return back()->withInput()->withErrors(['fecha_inicio' => 'Esas fechas se enciman con el semestre «' . $otro->nombre . '» ('
                . \Carbon\Carbon::parse($otro->fecha_inicio)->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($otro->fecha_fin)->format('d/m/Y') . ').']);
        }

        // 2. LÓGICA DE ACTIVO
        $esActivo = $request->has('es_activo');

        if ($esActivo) {
            // Si este es el activo, apagamos todos los demás primero
            Semestre::query()->update(['es_activo' => 0]);
        }

        // 3. GUARDAR
        Semestre::create([
            'nombre' => $request->nombre,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'es_activo' => $esActivo
        ]);

        return redirect()->route('semestres.index')->with('success', 'Semestre creado correctamente.');
    }

    public function edit(Semestre $semestre)
    {
        return view('admin.semestres.edit', ['semestre' => $semestre]);
    }

    public function update(Request $request, $id)
    {
        $semestre = Semestre::findOrFail($id);

        // 1. VALIDACIÓN EN ACTUALIZACIÓN
        $request->validate([
            // "unique:semestres,nombre,$id" significa: revisa que sea único, PERO ignora este ID (el actual)
            'nombre' => 'required|string|max:255|unique:semestres,nombre,' . $id,
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ], [
            'nombre.unique' => 'Ya existe otro semestre con este nombre.'
        ]);

        if ($otro = $this->encimado($request->fecha_inicio, $request->fecha_fin, (int) $id)) {
            return back()->withInput()->withErrors(['fecha_inicio' => 'Esas fechas se enciman con el semestre «' . $otro->nombre . '» ('
                . \Carbon\Carbon::parse($otro->fecha_inicio)->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($otro->fecha_fin)->format('d/m/Y') . ').']);
        }

        // 2. LÓGICA DE ACTIVO
        $esActivo = $request->has('es_activo');

        if ($esActivo) {
            // Desactivamos todos los demás (menos el actual, que se actualizará abajo)
            Semestre::where('id', '!=', $id)->update(['es_activo' => 0]);
        }

        // 3. ACTUALIZAR
        $semestre->update([
            'nombre' => $request->nombre,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'es_activo' => $esActivo
        ]);

        return redirect()->route('semestres.index')->with('success', 'Semestre actualizado correctamente.');
    }

    public function destroy(Semestre $semestre)
    {
        // La base borra EN CASCADA los grupos, los horarios (con las asistencias de
        // los profesores) y los días inhábiles del semestre: un clic se llevaba el
        // historial de todo un periodo. Sólo se detenía si algún alumno tenía
        // asistencia. Ahora, igual que con materias, grupos y laboratorios, se
        // avisa qué lo usa en lugar de borrarlo.
        if ($semestre->es_activo) {
            return redirect()->route('semestres.index')->with('error', 'No se puede eliminar «' . $semestre->nombre
                . '» porque es el semestre activo. Activa otro primero.');
        }

        $grupos = \App\Models\Grupo::where('semestre_id', $semestre->id)->count();
        $clases = \App\Models\Horario::withTrashed()->where('semestre_id', $semestre->id)->count();

        if ($grupos > 0 || $clases > 0) {
            $motivos = array_filter([
                $grupos > 0 ? ($grupos == 1 ? '1 grupo' : "{$grupos} grupos") : null,
                $clases > 0 ? ($clases == 1 ? '1 clase en los horarios' : "{$clases} clases en los horarios") : null,
            ]);

            return redirect()->route('semestres.index')->with('error', 'No se puede eliminar «' . $semestre->nombre
                . '»: tiene ' . implode(' y ', $motivos) . ' (contando la papelera de horarios). Es el historial de ese periodo.');
        }

        try {
            $semestre->delete();
            return redirect()->route('semestres.index')->with('success', '¡Semestre eliminado exitosamente!');
        } catch (\Exception $e) {
            // Por si intentas borrar un semestre que tiene alumnos o materias ligadas
            return redirect()->route('semestres.index')->with('error', 'No se puede eliminar este semestre porque tiene registros asociados.');
        }
    }

    /**
     * Otro semestre cuyas fechas se enciman con éstas, o null. Con dos periodos
     * encimados, una fecha pertenecía a los dos: el historial del profesor, los
     * reportes y los días inhábiles tomaban el primero que encontraban.
     */
    protected function encimado(string $inicio, string $fin, ?int $excepto = null): ?Semestre
    {
        return Semestre::when($excepto, fn($q) => $q->where('id', '!=', $excepto))
            ->whereDate('fecha_inicio', '<=', $fin)
            ->whereDate('fecha_fin', '>=', $inicio)
            ->first();
    }
}
