<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Busqueda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AlumnosMaestraImport; // <--- Tu nuevo importador

class AlumnoController extends Controller
{
    // Asegúrate de importar Request arriba si no lo tienes:
    // use Illuminate\Http\Request;

    public function index(Request $request)
    {
        // La consulta base se arma en una función porque, si la búsqueda no
        // encuentra nada, Busqueda la vuelve a construir para reintentarla
        // tolerando erratas. El orden por apellido se ve más ordenado en pantalla.
        // Por omisión, los activos; con ?bajas=1, los dados de baja (para reactivarlos).
        $verBajas = $request->boolean('bajas');

        $consultaBase = function () use ($verBajas) {
            return User::where('rol', 'Alumno')
                ->where('activo', ! $verBajas)
                ->orderBy('apellido_paterno', 'asc')
                ->orderBy('apellido_materno', 'asc');
        };

        // Busca cada palabra por separado, así "ROCIO AMADOR" encuentra a la
        // alumna aunque su nombre y su apellido estén en columnas distintas.
        $alumnos = Busqueda::paginar(
            $consultaBase,
            $request->input('search'),
            ['matricula', 'name', 'apellido_paterno', 'apellido_materno'],
            10
        );

        $totalBajas = User::where('rol', 'Alumno')->dadosDeBaja()->count();

        return view('admin.alumnos.index', compact('alumnos', 'verBajas', 'totalBajas'));
    }

    /**
     * Vuelve a activar a un alumno dado de baja: puede entrar otra vez y reaparece
     * en sus grupos, con todo su historial.
     */
    public function reactivar(User $alumno)
    {
        $alumno->reactivar();

        return redirect()->route('alumnos.index')
            ->with('success', 'Se reactivó a ' . trim($alumno->name . ' ' . $alumno->apellido_paterno) . '. Ya puede entrar de nuevo y vuelve a aparecer en sus grupos.');
    }

    public function create()
    {
        // Las carreras que ya existen en el padrón, para ofrecerlas en el buscador.
        // Así se evita que la misma carrera acabe escrita de tres formas distintas.
        $carreras = self::carrerasRegistradas();

        return view('admin.alumnos.create', compact('carreras'));
    }

    /**
     * Nombres de carrera distintos que ya están capturados, ordenados alfabéticamente.
     */
    public static function carrerasRegistradas()
    {
        return User::where('rol', 'Alumno')
            ->whereNotNull('carrera')
            ->where('carrera', '!=', '')
            ->distinct()
            ->orderBy('carrera')
            ->pluck('carrera');
    }

    public function store(Request $request)
    {
        // Si la matrícula es de un alumno dado de baja, se dice claro qué hacer en
        // lugar del genérico "ya está en uso".
        $deBaja = User::where('matricula', $request->matricula)->dadosDeBaja()->first();
        if ($deBaja) {
            return back()->withInput()->withErrors([
                'matricula' => 'Esa matrícula es de ' . trim($deBaja->name . ' ' . $deBaja->apellido_paterno)
                    . ', que está dado de baja. Reactívalo desde "Dados de baja" en la lista de alumnos.',
            ]);
        }

        // 1. VALIDACIÓN
        $request->validate([
            'matricula'        => 'required|string|max:20|unique:users,matricula',
            'nombres'          => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'required|string|max:255',
            'carrera'          => 'nullable|string|max:100', // <--- AGREGADO
            'email'            => 'required|email|max:255|unique:users,email',
            'password'         => 'required|string|min:8',
        ]);

        // 2. CREACIÓN
        User::create([
            'matricula'        => $request->matricula,
            'username'         => $request->matricula,
            'name'             => $request->nombres,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'carrera'          => $request->carrera, // <--- AGREGADO
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'rol'              => 'Alumno',
            'rfc'              => null,
        ]);

        return redirect()->route('alumnos.index')
            ->with('success', '¡Alumno registrado correctamente!');
    }

    public function edit(User $alumno)
    {
        // Las mismas carreras que en el alta, para que al corregir una ficha no se
        // introduzcan variantes nuevas de una carrera que ya existe.
        $carreras = self::carrerasRegistradas();

        return view('admin.alumnos.edit', ['alumno' => $alumno, 'carreras' => $carreras]);
    }

    public function update(Request $request, User $alumno)
    {
        // 1. VALIDACIÓN
        $request->validate([
            'matricula' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($alumno->id),
            ],
            'nombres'          => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'required|string|max:255',
            'carrera'          => 'nullable|string|max:100',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($alumno->id),
            ],
            'password' => 'nullable|string|min:8',
        ]);

        // 2. PREPARAR DATOS
        $data = [
            'matricula'        => $request->matricula,
            'username'         => $request->matricula,
            'name'             => $request->nombres,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'carrera'          => $request->carrera, // <--- AGREGADO
            'email'            => $request->email,
        ];

        // Solo actualizamos password si escribieron algo
        if (!empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        $alumno->update($data);

        return redirect()->route('alumnos.index')
            ->with('success', '¡Datos del alumno actualizados correctamente!');
    }

    public function destroy(User $alumno)
    {
        // Se da de baja en lugar de borrarlo: borrar se llevaba en cascada todo su
        // historial de asistencias, y con él las cifras de los reportes.
        $alumno->darDeBaja();

        return redirect()->route('alumnos.index')
            ->with('success', 'Se dio de baja a ' . trim($alumno->name . ' ' . $alumno->apellido_paterno)
                . '. Ya no puede entrar ni aparece en sus grupos, pero su historial se conserva. Puedes reactivarlo en "Dados de baja".');
    }

    public function importar(Request $request)
    {
        // 1. AUMENTAR EL TIEMPO LÍMITE
        // '0' significa "sin límite de tiempo". 
        // También puedes poner 300 (5 minutos) si prefieres.
        set_time_limit(0);

        // 2. AUMENTAR LA MEMORIA (Opcional, pero recomendado para excels grandes)
        ini_set('memory_limit', '-1');

        $request->validate([
            'archivo' => 'required|mimes:xlsx,csv,xls'
        ]);

        try {
            $importador = new AlumnosMaestraImport;

            // Todo o nada: si el archivo falla a la mitad no quedan alumnos a medias.
            DB::transaction(function () use ($importador, $request) {
                Excel::import($importador, $request->file('archivo'));
            });

            // Ninguna hoja traía un encabezado reconocible
            if ($importador->hojasLeidas === 0) {
                return back()->with('error', 'No se encontró el encabezado en el archivo. Hace falta al menos una columna con la '
                    . 'matrícula (MATRICULA o NUMERO DE CONTROL) y otra con el NOMBRE, en alguna de las primeras filas.');
            }

            $mensaje = 'Importación terminada: ' . $importador->creados . ' alumnos nuevos, '
                . $importador->actualizados . ' actualizados.';

            // Columnas del archivo que no se usan (p. ej. la CURP): se dice, para que
            // se sepa que se leyeron y se dejaron fuera a propósito.
            if (! empty($importador->columnasIgnoradas)) {
                $mensaje .= ' Columnas que no se usan y se ignoraron: ' . implode(', ', $importador->columnasIgnoradas) . '.';
            }

            if (! empty($importador->sinNombre)) {
                $total = count($importador->sinNombre);
                $mensaje .= ' ' . ($total == 1 ? '1 matrícula nueva no se dio de alta' : "{$total} matrículas nuevas no se dieron de alta")
                    . ' por venir sin nombre: ' . implode(', ', array_slice($importador->sinNombre, 0, 5)) . ($total > 5 ? '…' : '') . '.';
            }

            // Los que estaban dados de baja y vienen en la lista oficial se reactivan
            if ($importador->reactivados > 0) {
                $mensaje .= ' ' . ($importador->reactivados == 1 ? 'Se reactivó 1 alumno que estaba' : 'Se reactivaron ' . $importador->reactivados . ' alumnos que estaban')
                    . ' dado' . ($importador->reactivados == 1 ? '' : 's') . ' de baja.';
            }

            if ($importador->ignorados > 0) {
                $mensaje .= ' (' . $importador->ignorados . ' filas sin matrícula se omitieron.)';
            }

            if ($importador->repetidas > 0) {
                $mensaje .= ' (' . $importador->repetidas . ($importador->repetidas == 1 ? ' fila repetía una matrícula' : ' filas repetían una matrícula')
                    . ' ya leída; se tomó sólo la primera.)';
            }

            return back()->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }
}
