<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\User; // 
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AlumnosImport;

class GrupoAlumnoController extends Controller
{
    /**
     * Los alumnos que aparecen en la pantalla de gestionar para una búsqueda.
     *
     * La usan la pantalla Y el guardado, porque al guardar se quita del grupo a
     * quien se veía en pantalla y quedó sin marcar. Antes cada una buscaba a su
     * manera: la pantalla por matrícula o nombre (y, por un paréntesis, dejaba
     * colarse a profesores y administradores), y el guardado también por
     * apellidos. Buscando "García", los alumnos inscritos apellidados García no
     * salían en pantalla pero el guardado los daba por desmarcados y los sacaba
     * del grupo.
     */
    private function alumnosVisibles($busqueda)
    {
        return \App\Support\Busqueda::todos(
            fn() => User::where('rol', 'Alumno')->activos()->orderBy('matricula', 'asc'),
            $busqueda,
            ['matricula', 'name', 'apellido_paterno', 'apellido_materno']
        );
    }

    /**
     * Muestra la página para gestionar (asignar/quitar) alumnos de un grupo.
     */
    public function index(Request $request, Grupo $grupo)
    {
        $busqueda = $request->input('busqueda');

        // 2. Los alumnos de la búsqueda (la misma lista que usa el guardado)
        $alumnos_todos = $this->alumnosVisibles($busqueda);

        // 3. CAMBIO: Obtenemos la colección (esto usa la relación 'alumnos()' que ya arreglamos)
        $alumnos_en_grupo_coleccion = $grupo->alumnos()->orderBy('matricula', 'asc')->get();

        // 4. CAMBIO: Hacemos pluck sobre 'users.id'
        $alumnos_en_grupo_ids = $grupo->alumnos()->pluck('users.id')->toArray();

        return view('admin.grupos.gestionar', [
            'grupo' => $grupo,
            'alumnos_todos' => $alumnos_todos,
            'alumnos_en_grupo_coleccion' => $alumnos_en_grupo_coleccion,
            'alumnos_en_grupo_ids' => $alumnos_en_grupo_ids,
        ]);
    }

    
    /**
     * Sincroniza de forma inteligente (respetando filtros de búsqueda).
     */
    public function store (Request $request, Grupo $grupo)
    {
        // 1. OBTENER LO QUE EL USUARIO MARCÓ
        // Estos son los IDs que tienen la palomita puesta
        // Sólo alumnos (un id de otro tipo de usuario no se inscribe)
        $idsMarcados = User::where('rol', 'Alumno')
            ->activos()
            ->whereIn('id', (array) $request->input('alumnos_ids', []))
            ->pluck('id')
            ->all();

        // 2. RECONSTRUIR LA BÚSQUEDA PARA SABER QUÉ VIO EL USUARIO
        // Necesitamos saber qué alumnos aparecieron en la pantalla para saber
        // cuáles desmarcó intencionalmente. Tiene que ser EXACTAMENTE la misma lista
        // que armó la pantalla (ver alumnosVisibles).
        $busqueda = $request->input('busqueda');

        // Obtenemos los IDs de TODOS los que salieron en la lista (marcados y no marcados)
        $idsVisiblesEnPantalla = $this->alumnosVisibles($busqueda)->pluck('id')->all();

        // 3. OPERACIÓN QUIRÚRGICA

        // A) AGREGAR: Los que marcaste (usamos syncWithoutDetaching para no borrar a nadie externo)
        if (!empty($idsMarcados)) {
            $grupo->alumnos()->syncWithoutDetaching($idsMarcados);
        }

        // B) ELIMINAR: Aquí está el truco.
        // Solo borramos aquellos que ESTABAN en pantalla PERO NO fueron marcados.
        // Matemáticamente: Visibles - Marcados = Los que desmarcaste.
        $idsParaBorrar = array_diff($idsVisiblesEnPantalla, $idsMarcados);

        if (!empty($idsParaBorrar)) {
            $grupo->alumnos()->detach($idsParaBorrar);
        }

        return redirect()->route('grupos.gestionar-alumnos', [
            'grupo' => $grupo->id,
            'busqueda' => $busqueda // Mantenemos la búsqueda para que no se pierda
        ])->with('success', '¡Lista actualizada correctamente sin afectar a otros alumnos!');
    }

    /**
     * Procesa el archivo Excel importado.
     */
    public function importarAlumnos(Request $request, Grupo $grupo)
    {
        // 1. Validaciones y Time Limit
        set_time_limit(0);
        $request->validate([
            'archivo' => 'required|mimes:xlsx,csv,xls'
        ]);

        try {
            // Instanciamos el importador
            $importador = new \App\Imports\AlumnosImport($grupo);

            // Todo o nada: si el archivo falla a la mitad no queda media lista inscrita.
            \Illuminate\Support\Facades\DB::transaction(function () use ($importador, $request) {
                \Maatwebsite\Excel\Facades\Excel::import($importador, $request->file('archivo'));
            });

            // 2. REVISAR ERRORES
            if (count($importador->errores) > 0) {
                // Si hubo alumnos no encontrados, preparamos un mensaje
                $totalErrores = count($importador->errores);
                $mensaje = "Se inscribieron {$importador->inscritos} alumnos, PERO $totalErrores no se pudieron inscribir:<br><ul>";

                // Mostramos los primeros 5 errores para no saturar la pantalla. Se
                // escapan: el nombre viene del Excel y el aviso se pinta como HTML.
                foreach (array_slice($importador->errores, 0, 5) as $error) {
                    $mensaje .= '<li>' . e($error) . '</li>';
                }
                if ($totalErrores > 5) $mensaje .= "<li>... y otros más.</li>";
                $mensaje .= "</ul><small>Los que no existen, regístralos primero en la sección de Alumnos; los que están dados de baja, reactívalos desde \"Dados de baja\" en esa misma sección.</small>";

                return back()->with('warning', $mensaje);
            }

            $mensaje = 'Importación terminada: ' . $importador->inscritos
                . ($importador->inscritos == 1 ? ' alumno inscrito' : ' alumnos inscritos') . ' en el grupo.';

            if ($importador->ignorados > 0) {
                $mensaje .= ' (' . $importador->ignorados . ' filas sin matrícula se omitieron.)';
            }

            return back()->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al leer el archivo: ' . $e->getMessage());
        }
    }
}
