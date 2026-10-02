<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use App\Support\Busqueda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MateriasImport;

class MateriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // La consulta base va en una función porque, si la búsqueda no encuentra
        // nada, Busqueda la reconstruye para reintentarla tolerando erratas.
        $consultaBase = function () {
            return Materia::query();
        };

        // Palabra por palabra: "calculo diferencial" encuentra la materia aunque
        // se escriba en otro orden o con espacios de más.
        $materias = Busqueda::paginar(
            $consultaBase,
            $request->input('search'),
            ['nombre_materia', 'clave'],
            10
        );

        // 4. Devuelve la vista
        return view('admin.materias.index', ['materias' => $materias]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.materias.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validación Actualizada (Incluyendo Clave)
        $request->validate([
            'clave' => 'nullable|string|max:20|unique:materias', // La clave debe ser única
            'nombre_materia' => 'required|string|max:255',
            'creditos' => 'nullable|string|max:50',
        ]);

        // 2. Guardado Automático
        // Usamos create($request->all()) para guardar Clave, Nombre y Créditos de un jalón.
        // Asegúrate de que en tu Modelo Materia.php tengas 'clave' y 'creditos' en $fillable.
        Materia::create($request->all());

        return redirect()->route('materias.index')
            ->with('success', '¡Materia creada exitosamente!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Materia $materia)
    {
        return view('admin.materias.edit', ['materia' => $materia]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Materia $materia)
    {
        // 1. Validación (Ignorando el ID actual para que no de error de "ya existe")
        $request->validate([
            'clave' => 'nullable|string|max:20|unique:materias,clave,' . $materia->id,
            'nombre_materia' => 'required|string|max:255',
            'creditos' => 'nullable|string|max:50',
        ]);

        // 2. Actualización Masiva
        $materia->update($request->all());

        return redirect()->route('materias.index')
            ->with('success', '¡Materia actualizada exitosamente!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Materia $materia)
    {
        // La base no deja borrar una materia que usan los horarios (también los de la
        // papelera): antes eso terminaba en error 500. Se avisa en su lugar.
        $clases = \App\Models\Horario::withTrashed()->where('materia_id', $materia->id)->count();

        if ($clases > 0) {
            return redirect()->route('materias.index')->with('error', 'No se puede eliminar «' . $materia->nombre_materia
                . '»: la ' . ($clases == 1 ? 'usa 1 clase' : "usan {$clases} clases") . ' de los horarios (contando la papelera).'
                . ' Quítala de esas clases primero.');
        }

        $materia->delete();

        return redirect()->route('materias.index')
            ->with('success', '¡Materia eliminada exitosamente!');
    }

    // Importador de materias
    public function importar(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $importador = new MateriasImport;

            // Todo o nada: si el archivo falla a la mitad no quedan materias sueltas.
            DB::transaction(function () use ($importador, $request) {
                Excel::import($importador, $request->file('archivo_excel'));
            });

            $mensaje = 'Importación terminada: ' . $importador->creadas . ' materias nuevas, '
                . $importador->actualizadas . ' actualizadas.';

            if ($importador->ignoradas > 0) {
                $mensaje .= ' (' . $importador->ignoradas . ' filas sin clave o sin nombre se omitieron.)';
            }

            return redirect()->route('materias.index')->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }
}
