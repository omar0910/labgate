<?php

namespace App\Http\Controllers;

use App\Models\CentroComputo;
use Illuminate\Http\Request;

class CentroComputoController extends Controller
{
    /**
     * Muestra la lista de centros.
     */
    public function index()
    {
        $centrosComputo = CentroComputo::all();
        return view('admin.centros-computo.index', ['centrosComputo' => $centrosComputo]);
    }

    /**
     * Muestra el formulario para crear.
     */
    public function create()
    {
        return view('admin.centros-computo.create');
    }

    /**
     * Guarda el nuevo centro.
     */
    public function store(Request $request)
    {
        // 1. Validamos los 3 campos
        $request->validate([
            'nombre_centro' => 'required|string|max:255|unique:centro_computos',
            'capacidad' => 'required|integer|min:1',       // Nuevo: Capacidad
            'permite_uso_libre' => 'required|boolean',      // Nuevo: Si/No
            'uso_libre_solo_en_sus_pcs' => 'nullable|boolean',
        ]);

        // 2. Creamos el registro con todos los datos
        CentroComputo::create([
            'nombre_centro' => $request->nombre_centro,
            'capacidad' => $request->capacidad,
            'permite_uso_libre' => $request->permite_uso_libre,
            'uso_libre_solo_en_sus_pcs' => $request->boolean('uso_libre_solo_en_sus_pcs'),
        ]);

        return redirect()->route('centros-computo.index')
            ->with('success', '¡Centro de Cómputo creado exitosamente!');
    }

    /**
     * Muestra el formulario para editar.
     */
    public function edit(CentroComputo $centro_computo)
    {
        // Pasamos el objeto a la vista para rellenar el formulario
        return view('admin.centros-computo.edit', ['centro' => $centro_computo]);
    }

    /**
     * Actualiza el centro.
     */
    public function update(Request $request, CentroComputo $centro_computo)
    {
        // 1. Validamos (incluyendo la excepción del unique para el ID actual)
        $request->validate([
            'nombre_centro' => 'required|string|max:255|unique:centro_computos,nombre_centro,' . $centro_computo->id,
            'capacidad' => 'required|integer|min:1',       // Nuevo
            'permite_uso_libre' => 'required|boolean',      // Nuevo
            'uso_libre_solo_en_sus_pcs' => 'nullable|boolean',
        ]);

        // 2. Actualizamos los campos manualmente
        $centro_computo->nombre_centro = $request->input('nombre_centro');
        $centro_computo->capacidad = $request->input('capacidad');             // Nuevo
        $centro_computo->permite_uso_libre = $request->input('permite_uso_libre'); // Nuevo
        // Uso libre sólo desde sus computadoras (no desde un equipo personal)
        $centro_computo->uso_libre_solo_en_sus_pcs = $request->boolean('uso_libre_solo_en_sus_pcs');

        // 3. Guardamos cambios
        $centro_computo->save();

        return redirect()->route('centros-computo.index')
            ->with('success', '¡Centro de Cómputo actualizado exitosamente!');
    }

    /**
     * Elimina el centro.
     */
    public function destroy(CentroComputo $centro_computo)
    {
        // La base no deja borrar un laboratorio con clases o reportes de fallas:
        // antes eso terminaba en error 500. Se avisa en su lugar.
        $clases = \App\Models\Horario::withTrashed()->where('centro_computo_id', $centro_computo->id)->count();
        $fallas = \App\Models\Incidencia::where('centro_computo_id', $centro_computo->id)->count();

        if ($clases > 0 || $fallas > 0) {
            $motivos = array_filter([
                $clases > 0 ? ($clases == 1 ? '1 clase en los horarios' : "{$clases} clases en los horarios") : null,
                $fallas > 0 ? ($fallas == 1 ? '1 reporte de falla' : "{$fallas} reportes de fallas") : null,
            ]);

            return redirect()->route('centros-computo.index')->with('error', 'No se puede eliminar «' . $centro_computo->nombre_centro
                . '»: tiene ' . implode(' y ', $motivos) . ' (contando la papelera de horarios).');
        }

        $centro_computo->delete();

        return redirect()->route('centros-computo.index')
            ->with('success', '¡Centro de Cómputo eliminado exitosamente!');
    }
}
