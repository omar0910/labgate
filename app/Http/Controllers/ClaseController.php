<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\Horario;
use Carbon\Carbon;

class ClaseController extends Controller
{
    /**
     * Libera todas las computadoras de una clase específica.
     * Funciona para Admin y Encargado.
     */
    public function liberarClase($horario_id)
    {
        $now = Carbon::now();
        $hoy = Carbon::today();

        // 1. Buscamos el horario para saber de qué materia hablamos (opcional, para logs)
        $horario = Horario::findOrFail($horario_id);

        // 2. Actualizamos asistencias:
        // - Que sean de ese horario
        // - Que sean de HOY
        // - Que sean tipo 'Clase'
        // - Que sigan abiertas (fecha_hora_salida es NULL)
        $afectados = Asistencia::where('horario_id', $horario_id)
            ->whereDate('fecha', $hoy)
            ->where('tipo', 'Clase')
            ->whereNull('fecha_hora_salida')
            // CORRECCIÓN AQUÍ: Cambiamos $now->toTimeString() por $now
            ->update(['fecha_hora_salida' => $now]);

        return back()->with('success', "Clase finalizada manualmente. Se liberaron $afectados computadoras.");
    }
}
