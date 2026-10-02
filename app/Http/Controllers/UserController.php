<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Busqueda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // La consulta base va en una función porque, si la búsqueda no encuentra
        // nada, Busqueda la reconstruye para reintentarla tolerando erratas.
        // Por omisión, los activos; con ?bajas=1, los dados de baja (para reactivarlos).
        $verBajas = $request->boolean('bajas');

        $consultaBase = function () use ($verBajas) {
            return User::whereIn('rol', ['Administrador', 'Encargado'])
                ->where('activo', ! $verBajas)
                ->orderBy('name', 'asc');
        };

        // Se busca palabra por palabra, así el nombre completo también encuentra.
        $usuarios = Busqueda::paginar(
            $consultaBase,
            $request->input('search'),
            ['name', 'apellido_paterno', 'apellido_materno', 'username', 'email'],
            10
        );

        $totalBajas = User::whereIn('rol', ['Administrador', 'Encargado'])->dadosDeBaja()->count();

        return view('admin.usuarios.index', ['usuarios' => $usuarios, 'verBajas' => $verBajas, 'totalBajas' => $totalBajas]);
    }

    public function create()
    {
        return view('admin.usuarios.create');
    }

    public function store(Request $request)
    {
        // 1. VALIDACIÓN FLEXIBLE
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255', // Ahora opcional
            'apellido_materno' => 'nullable|string|max:255', // Ahora opcional
            'username'         => 'required|string|max:255|unique:users',
            'email'            => 'nullable|string|email|max:255|unique:users', // Ahora opcional
            'password'         => 'required|string|min:8',
            'rol'              => 'required|string|in:Administrador,Encargado', // Solo staff
        ]);

        // 2. GUARDAR DATOS (Quitando RFC y Academia)
        User::create([
            'name'             => $request->nombres,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'username'         => strtolower($request->username),
            'email'            => $request->email ? strtolower($request->email) : null,
            'password'         => Hash::make($request->password),
            'rol'              => $request->rol,
            'rfc'              => null, // Limpiamos rfc por si acaso
            'academia'         => null, // Limpiamos academia por si acaso
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario del Staff creado exitosamente.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.edit', ['usuario' => $usuario]);
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'username'         => 'required|string|max:255|unique:users,username,' . $usuario->id,
            'email'            => 'nullable|email|max:255|unique:users,email,' . $usuario->id,
            'rol'              => 'required|string|in:Administrador,Encargado',
            // Igual que al crear: si se cambia, mínimo 8 caracteres
            'password'         => 'nullable|string|min:8',
        ]);

        // Que un administrador no se quite a sí mismo el rol: se quedaría fuera de
        // todas las pantallas de administración.
        if ($usuario->id === auth()->id() && $request->rol !== 'Administrador') {
            return back()->withInput()->withErrors(['rol' => 'No puedes quitarte a ti mismo el rol de Administrador.']);
        }

        $data = [
            'name'             => $request->nombres,
            // Si el campo viene vacío, lo guardamos como NULL en vez de una cadena vacía ""
            'apellido_paterno' => $request->apellido_paterno ?: null,
            'apellido_materno' => $request->apellido_materno ?: null,
            'username'         => strtolower($request->username),
            'email'            => $request->email ? strtolower($request->email) : null,
            'rol'              => $request->rol,
        ];

        if (!empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        // 1. Protección extra: Evitar que el administrador se borre a sí mismo accidentalmente
        if (auth()->id() == $usuario->id) {
            return redirect()->back()->with('error', 'Por seguridad, no puedes eliminar tu propia cuenta mientras estás en sesión.');
        }

        $nombre = trim($usuario->name . ' ' . $usuario->apellido_paterno);

        // Un profesor con clases en el semestre en curso no se da de baja: sus
        // clases se quedarían sin nadie que pase lista. Primero hay que asignarlas
        // a otro profesor. Las de semestres pasados no estorban: son su historial.
        if ($usuario->rol === 'Profesor') {
            $semestre = \App\Models\Semestre::activo();
            $clasesVigentes = $semestre
                ? \App\Models\Horario::where('user_id', $usuario->id)->where('semestre_id', $semestre->id)->count()
                : 0;

            if ($clasesVigentes > 0) {
                return redirect()->back()->with('error', 'No se puede dar de baja a ' . $nombre . ': tiene '
                    . ($clasesVigentes == 1 ? '1 clase' : "{$clasesVigentes} clases") . ' en el semestre actual.'
                    . ' Asígnalas a otro profesor primero.');
            }
        }

        // 2. Se da de baja en lugar de borrar: borrar se llevaba en cascada su
        //    historial (los reportes de fallas que levantó, por ejemplo). Ya no
        //    puede entrar ni aparece en las listas, y se puede reactivar.
        $usuario->darDeBaja();

        $mensaje = 'Se dio de baja a ' . $nombre . '. Ya no puede entrar, pero su historial se conserva. Puedes reactivarlo en "Dados de baja".';

        // 3. Redirección Inteligente (Controlador de tráfico)
        if ($usuario->rol === 'Profesor') {
            return redirect()->route('admin.reportes.profesores')->with('success', $mensaje);
        } elseif ($usuario->rol === 'Alumno') {
            // Te devuelve justo a la pestaña donde estabas
            return redirect()->back()->with('success', $mensaje);
        }

        // 4. Redirección por defecto (Para Staff, Administradores, etc.)
        return redirect()->route('usuarios.index')->with('success', $mensaje);
    }

    /**
     * Vuelve a activar una cuenta dada de baja (personal o profesor).
     */
    public function reactivar(User $usuario)
    {
        $usuario->reactivar();

        $mensaje = 'Se reactivó a ' . trim($usuario->name . ' ' . $usuario->apellido_paterno) . '. Ya puede entrar de nuevo.';

        return $usuario->rol === 'Profesor'
            ? redirect()->route('admin.reportes.profesores')->with('success', $mensaje)
            : redirect()->route('usuarios.index')->with('success', $mensaje);
    }
}
