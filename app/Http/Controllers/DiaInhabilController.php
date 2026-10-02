<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DiaInhabil;
use App\Models\Semestre;
use Illuminate\Http\Request;

class DiaInhabilController extends Controller
{
    public function index()
    {
        $dias = DiaInhabil::with('semestre')->orderBy('fecha', 'asc')->get();
        $semestres = Semestre::all();
        return view('admin.configuracion.dias_inhabiles', compact('dias', 'semestres'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'motivo' => 'required|string|max:255', // <-- ¡El cambio está aquí! Usamos : en lugar de ()
            'semestre_id' => 'required|exists:semestres,id'
        ], [
            'fecha.date_format' => 'La fecha no es válida.',
        ]);

        // Un día fuera del semestre elegido no tiene efecto (ninguna clase de ese
        // semestre cae ahí), y repetido se contaba dos veces.
        $semestre = Semestre::findOrFail($request->semestre_id);
        if (! $semestre->contieneFecha($request->fecha)) {
            return redirect()->back()->withInput()->with('error', 'El ' . \Carbon\Carbon::parse($request->fecha)->format('d/m/Y')
                . ' no está dentro del semestre «' . $semestre->nombre . '» ('
                . \Carbon\Carbon::parse($semestre->fecha_inicio)->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($semestre->fecha_fin)->format('d/m/Y') . ').');
        }

        if (DiaInhabil::where('semestre_id', $semestre->id)->whereDate('fecha', $request->fecha)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El ' . \Carbon\Carbon::parse($request->fecha)->format('d/m/Y') . ' ya está registrado como día inhábil.');
        }

        DiaInhabil::create($request->only(['fecha', 'motivo', 'semestre_id']));

        return redirect()->back()->with('success', 'Día inhábil registrado correctamente.');
    }

    public function destroy($id)
    {
        DiaInhabil::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Día eliminado.');
    }
}
