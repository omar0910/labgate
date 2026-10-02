<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Semestre;
use App\Support\Busqueda;
use App\Imports\GruposImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class GrupoController extends Controller
{
    /**
     * Muestra la lista de grupos, PERO filtrada por el semestre activo y con buscador.
     */
    public function index(Request $request) // <-- Agregamos Request aquí
    {
        // 1. Buscamos el semestre activo
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // 2. Lógica de filtrado
        if ($semestreActivo) {
            // La consulta base va en una función porque, si la búsqueda no encuentra
            // nada, Busqueda la reconstruye para reintentarla tolerando erratas.
            $consultaBase = function () use ($semestreActivo) {
                return Grupo::with('semestre')
                    ->where('semestre_id', $semestreActivo->id)
                    ->orderBy('nombre_grupo', 'asc');
            };

            // Palabra por palabra: "1SM calculo" encuentra "1SM-Cálculo Diferencial"
            // aunque el guion los separe o se escriban en otro orden.
            $grupos = Busqueda::paginar(
                $consultaBase,
                $request->input('search'),
                ['nombre_grupo'],
                20
            );
        } else {
            // Si no hay semestre activo, mandamos una colección vacía para no romper la vista
            $grupos = collect();
        }

        // Pasamos también la variable $semestreActivo para que puedas poner el nombre en el título de la vista
        return view('admin.grupos.index', [
            'grupos' => $grupos,
            'semestreActivo' => $semestreActivo
        ]);
    }

    /**
     * El alta de grupos ahora vive en el propio listado (columna izquierda), para
     * poder crear varios seguidos sin entrar y salir de otra pantalla.
     * Se conserva la ruta y redirige, por si quedó algún enlace o marcador guardado.
     */
    /**
     * Muestra el formulario para dar de alta un grupo suelto.
     *
     * El alta masiva se hace con el importador de Excel desde el listado; esta
     * pantalla es para los casos sueltos, por ejemplo un grupo que faltó.
     */
    public function create()
    {
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        if (!$semestreActivo) {
            return redirect()->route('grupos.index')
                ->with('error', '¡No puedes crear grupos porque no hay ningún Semestre Activo! Ve a "Semestres" y activa uno.');
        }

        return view('admin.grupos.create', compact('semestreActivo'));
    }

    public function store(Request $request)
    {
        // 1. Validamos el nombre
        $request->validate([
            'nombre_grupo' => 'required|string|max:255',
        ]);

        // 2. Buscamos el semestre activo (CRÍTICO)
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        // Validación de seguridad: Si no hay semestre activo, no dejamos crear grupos
        if (!$semestreActivo) {
            return back()->with('error', '¡No puedes crear grupos porque no hay ningún Semestre Activo! Ve a "Semestres" y activa uno.');
        }

        // 3. Creamos el grupo asignándole el ID del semestre activo AUTOMÁTICAMENTE
        // (Ignoramos si el formulario envió un 'semestre_id', usamos el activo)
        Grupo::create([
            'nombre_grupo' => $request->nombre_grupo,
            'semestre_id'  => $semestreActivo->id,
        ]);

        return redirect()->route('grupos.index')
            ->with('success', 'Grupo creado correctamente para el semestre: ' . $semestreActivo->nombre);
    }

    public function edit(Grupo $grupo)
    {
        // Pasamos el grupo a editar Y la lista de todos los semestres
        $semestres = Semestre::all();
        return view('admin.grupos.edit', [
            'grupo' => $grupo,
            'semestres' => $semestres
        ]);
    }

    public function update(Request $request, Grupo $grupo)
    {
        // Aquí sí permitimos cambiar el semestre manualmente si el admin se equivocó
        $request->validate([
            'nombre_grupo' => 'required|string|max:255',
            'semestre_id' => 'required|exists:semestres,id',
        ]);

        $grupo->update($request->all());

        return redirect()->route('grupos.index')
            ->with('success', '¡Grupo actualizado exitosamente!');
    }

    /**
     * Crea de golpe todos los grupos del semestre a partir del archivo de horarios.
     *
     * Es el mismo Excel del que se importan las materias; aquí sólo se toman las
     * columnas GRUPO y NOMBRE DE LA ASIGNATURA para armar nombres del estilo
     * "1SM-Cálculo Diferencial". El alta manual de la izquierda sigue igual.
     */
    public function importar(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|mimes:xlsx,xls,csv'
        ]);

        // Misma regla que en el alta manual: los grupos son del semestre activo.
        $semestreActivo = Semestre::where('es_activo', 1)->first();

        if (!$semestreActivo) {
            return back()->with('error', '¡No puedes importar grupos porque no hay ningún Semestre Activo! Ve a "Semestres" y activa uno.');
        }

        try {
            $importador = new GruposImport($semestreActivo->id);

            // Todo o nada: si el archivo falla a la mitad no quedan grupos sueltos.
            DB::transaction(function () use ($importador, $request) {
                Excel::import($importador, $request->file('archivo_excel'));
            });

            if ($importador->hojasLeidas === 0) {
                return back()->with('error', 'No se encontraron las columnas necesarias en ninguna hoja del archivo. Hace falta una columna GRUPO y otra con la asignatura (puede llamarse MATERIA, ASIGNATURA o NOMBRE DE LA ASIGNATURA).');
            }

            if ($importador->creados === 0 && $importador->repetidos === 0) {
                return back()->with('error', 'El archivo se leyó, pero no traía ninguna fila con grupo y asignatura. Revisa que sea el archivo correcto.');
            }

            $mensaje = 'Importación terminada: ' . $importador->creados
                . ($importador->creados == 1 ? ' grupo nuevo' : ' grupos nuevos');

            if ($importador->repetidos > 0) {
                $mensaje .= ', ' . $importador->repetidos
                    . ($importador->repetidos == 1 ? ' que ya existía se omitió' : ' que ya existían se omitieron');
            }

            $mensaje .= '. Semestre: ' . $semestreActivo->nombre . '.';

            return redirect()->route('grupos.index')->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    public function destroy(Grupo $grupo)
    {
        // La base no deja borrar un grupo que usan los horarios (también los de la
        // papelera): antes eso terminaba en error 500. Se avisa en su lugar.
        $clases = \App\Models\Horario::withTrashed()->where('grupo_id', $grupo->id)->count();

        if ($clases > 0) {
            return redirect()->route('grupos.index')->with('error', 'No se puede eliminar el grupo «' . $grupo->nombre_grupo
                . '»: lo ' . ($clases == 1 ? 'usa 1 clase' : "usan {$clases} clases") . ' de los horarios (contando la papelera).'
                . ' Elimina o cambia esas clases primero.');
        }

        $grupo->delete();
        return redirect()->route('grupos.index')
            ->with('success', '¡Grupo eliminado exitosamente!');
    }
}
