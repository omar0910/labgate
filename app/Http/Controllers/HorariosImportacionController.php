<?php

namespace App\Http\Controllers;

use App\Imports\HorariosImport;
use App\Models\CentroComputo;
use App\Models\Materia;
use App\Models\Semestre;
use App\Models\User;
use App\Support\PlanDeHorarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa la rejilla de horarios de los laboratorios.
 *
 * Son dos pasos a propósito: primero se revisa el archivo y se enseña TODO lo
 * que pasaría (qué clases son nuevas, cuáles cambian de hora, cuáles ya no
 * aparecen y qué no se pudo reconocer), y sólo cuando la persona confirma se
 * guarda. Así una importación nunca sorprende ni borra nada por su cuenta.
 */
class HorariosImportacionController extends Controller
{
    /** Donde se deja el archivo mientras se revisa. */
    const CARPETA = 'importaciones';

    /**
     * Paso 1: leer el archivo y mostrar el plan.
     */
    public function revisar(Request $request)
    {
        $semestre = Semestre::where('es_activo', 1)->first();

        if (! $semestre) {
            return back()->with('error', 'No puedes importar horarios porque no hay ningún semestre activo.');
        }

        $this->limpiarRevisionesAbandonadas();

        // Se entra aquí dos veces: al subir el archivo, y otra vez desde la misma
        // revisión cuando la persona ya dijo a qué corresponde lo que faltaba.
        if ($request->filled('token') && Storage::exists(self::CARPETA . '/' . basename($request->input('token')))) {
            $ruta = self::CARPETA . '/' . basename($request->input('token'));
        } else {
            $request->validate([
                'archivo' => 'required|mimes:xlsx,xls,csv|max:10240',
            ]);

            // El archivo se guarda con un nombre al azar para poder volver a leerlo
            // al confirmar, sin pedir que se suba dos veces.
            $token = Str::uuid()->toString();
            $ruta = $request->file('archivo')->storeAs(self::CARPETA, $token . '.' . $request->file('archivo')->getClientOriginalExtension());
        }

        try {
            $lectura = $this->leerArchivo($ruta);
        } catch (\Throwable $e) {
            Storage::delete($ruta);
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        if ($lectura->hojasLeidas === 0) {
            Storage::delete($ruta);
            return back()->with('error', 'No se encontraron las columnas necesarias en ninguna hoja. Hacen falta DIA, INICIO, TERMINO, MATERIA y GRUPO (el área puede venir en una columna AREA o ser el nombre de la hoja).');
        }

        if (empty($lectura->clases)) {
            Storage::delete($ruta);
            return back()->with('error', 'El archivo se leyó, pero no traía ninguna clase: todas las horas estaban vacías.');
        }

        $decisiones = [
            'area'    => $this->soloNumeros($request->input('area', [])),
            'materia' => $this->soloNumeros($request->input('materia', [])),
            'docente' => $this->soloNumeros($request->input('docente', [])),
        ];

        $plan = (new PlanDeHorarios($semestre->id, $decisiones))->analizar($lectura->clases);

        return view('admin.horarios.importar', [
            'plan'       => $plan,
            'decisiones' => $decisiones,
            'problemas' => $lectura->problemas,
            'token'     => basename($ruta),
            'semestre'  => $semestre,
            'centros'   => CentroComputo::orderBy('nombre_centro')->pluck('nombre_centro', 'id'),
            // Con la clave a la vista: el catálogo tiene materias repetidas
            // (ACC0906 y ACC-0906) y sólo por la clave se distinguen.
            'materias'  => Materia::orderBy('nombre_materia')->get()
                ->mapWithKeys(function ($m) {
                    return [$m->id => $m->nombre_materia . ' (' . $m->clave . ')'];
                }),
            'docentes'  => User::whereIn('rol', ['Profesor', 'Administrador', 'Encargado'])
                ->activos()
                ->orderBy('name')->get()
                ->mapWithKeys(function ($u) {
                    return [$u->id => $u->nombre_completo . ' (' . $u->rol . ')'];
                }),
        ]);
    }

    /**
     * Paso 2: guardar lo revisado, con las decisiones que tomó la persona.
     */
    public function confirmar(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $semestre = Semestre::where('es_activo', 1)->first();

        if (! $semestre) {
            return redirect()->route('horarios.index')->with('error', 'No hay ningún semestre activo.');
        }

        $ruta = self::CARPETA . '/' . basename($request->input('token'));

        if (! Storage::exists($ruta)) {
            return redirect()->route('horarios.index')
                ->with('error', 'El archivo de la revisión ya no está disponible. Vuelve a subirlo.');
        }

        $decisiones = [
            'area'    => $this->soloNumeros($request->input('area', [])),
            'materia' => $this->soloNumeros($request->input('materia', [])),
            'docente' => $this->soloNumeros($request->input('docente', [])),
        ];

        try {
            $lectura = $this->leerArchivo($ruta);

            $planificador = new PlanDeHorarios($semestre->id, $decisiones);
            $plan = $planificador->analizar($lectura->clases);

            $hecho = DB::transaction(function () use ($planificador, $plan, $request) {
                return $planificador->aplicar($plan, $request->boolean('quitar_sobrantes'));
            });
        } catch (\Throwable $e) {
            return redirect()->route('horarios.index')
                ->with('error', 'No se pudo guardar la importación: ' . $e->getMessage());
        }

        Storage::delete($ruta);

        return redirect()->route('horarios.index')->with('success', $this->resumen($hecho, $semestre));
    }

    /**
     * Borra los archivos de revisiones que nadie confirmó.
     *
     * Si alguien sube el archivo, mira la revisión y cancela, el archivo se queda
     * en el servidor. Con un día de margen ya nadie va a volver a confirmarlo.
     */
    protected function limpiarRevisionesAbandonadas(): void
    {
        $limite = now()->subDay()->getTimestamp();

        foreach (Storage::files(self::CARPETA) as $archivo) {
            try {
                if (Storage::lastModified($archivo) < $limite) {
                    Storage::delete($archivo);
                }
            } catch (\Throwable $e) {
                // Un archivo que no se pudo revisar no debe impedir la importación.
            }
        }
    }

    /** Lee el archivo y devuelve el importador con las clases encontradas. */
    protected function leerArchivo(string $ruta): HorariosImport
    {
        $lectura = new HorariosImport();
        Excel::import($lectura, Storage::path($ruta));

        return $lectura;
    }

    /**
     * Las decisiones de la revisión, como números: el id elegido, o 0 si se
     * eligió "No importar". El 0 se conserva a propósito: es una decisión, y
     * sin él volvería a aplicarse la propuesta automática.
     */
    protected function soloNumeros($valores): array
    {
        if (! is_array($valores)) {
            return [];
        }

        return array_map('intval', $valores);
    }

    protected function resumen(array $hecho, Semestre $semestre): string
    {
        $partes = [];

        if ($hecho['creadas'])      { $partes[] = $hecho['creadas'] . ' clases nuevas'; }
        if ($hecho['actualizadas']) { $partes[] = $hecho['actualizadas'] . ' actualizadas'; }
        if ($hecho['iguales'])      { $partes[] = $hecho['iguales'] . ' sin cambios'; }
        if ($hecho['grupos'])       { $partes[] = $hecho['grupos'] . ' grupos nuevos'; }
        if ($hecho['retiradas'])    { $partes[] = $hecho['retiradas'] . ' enviadas a la papelera'; }
        if ($hecho['omitidas'])     { $partes[] = $hecho['omitidas'] . ' omitidas por datos sin reconocer'; }

        return 'Importación terminada: ' . (empty($partes) ? 'no hubo cambios' : implode(', ', $partes))
            . '. Semestre: ' . $semestre->nombre . '.';
    }
}
