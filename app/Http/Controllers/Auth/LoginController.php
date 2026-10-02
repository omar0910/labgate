<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Asistencia;
use App\Services\AvisosCorreo;
use App\Support\DesbloqueoDePersonal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Maneja la petición de login.
     */
    public function login(Request $request)
    {
        // 1. Validar los datos de entrada
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->input('login');

        // 2. Límite de intentos fallidos por cuenta y por equipo. Sin él se podía
        //    probar contraseñas sin fin, y muchas siguen siendo la matrícula o el
        //    RFC, que son datos conocidos. En un laboratorio todos salen por la misma
        //    IP, por eso la llave incluye la cuenta: un alumno no bloquea a otro.
        $llaveIntentos = 'login|' . Str::lower(trim($loginInput)) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($llaveIntentos, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Demasiados intentos fallidos. Espera ' . RateLimiter::availableIn($llaveIntentos)
                    . ' segundos e inténtalo de nuevo.',
            ]);
        }

        // 3. LÓGICA DE BÚSQUEDA DIRECTA (Sin adivinar)
        // Buscamos lo que escribió el usuario en las 3 columnas al mismo tiempo
        $user = User::where('username', $loginInput)
            ->orWhere('rfc', $loginInput)
            ->orWhere('matricula', $loginInput)
            ->first();

        // 4. Verificamos al usuario y la contraseña
        if ($user && Hash::check($request->input('password'), $user->password)) {

            // Una cuenta dada de baja ya no entra (su historial se conserva)
            if (! $user->activo) {
                throw ValidationException::withMessages([
                    'login' => 'Tu cuenta está dada de baja. Si crees que es un error, acude al centro de cómputo.',
                ]);
            }

            // 5. Autenticación exitosa.
            RateLimiter::clear($llaveIntentos);
            Auth::login($user);
            $request->session()->regenerate();

            // Aquí se sabe la contraseña tal cual la escribió: es el único momento
            // en que se puede comprobar si sigue siendo la de arranque.
            $this->revisarContrasenaDeArranque($request, $user, $request->input('password'));

            // Si entró desde la pantalla de bloqueo de un laboratorio y es del
            // personal, esa computadora queda liberada mientras trabaje en ella.
            $this->liberarEquipoSiEsPersonal($request, $user);

            // 6. Redirección por Rol
            if ($user->rol == 'Administrador') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->rol == 'Profesor') {
                return redirect()->intended('/profesor/dashboard');
            } elseif ($user->rol == 'Encargado') {
                return redirect()->intended('/encargado/inicio');
            } elseif ($user->rol == 'Alumno') {
                return redirect()->intended('/alumno/dashboard');
            }

            return $this->logout($request);
        }

        // 7. Autenticación fallida: cuenta como intento durante un minuto.
        RateLimiter::hit($llaveIntentos, 60);

        throw ValidationException::withMessages([
            'login' => 'Las credenciales no coinciden con nuestros registros.',
        ]);
    }

    /**
     * Deja anotado en la sesión si el usuario sigue con la contraseña que le dio
     * el sistema, para que el aviso salga en su foto de perfil.
     *
     * 'password_por_defecto' dura toda la sesión: el punto rojo y el recordatorio
     * del menú siguen ahí hasta que la cambie. 'avisar_password' es de un solo
     * uso: es el mensaje que aparece nada más entrar.
     */
    private function revisarContrasenaDeArranque(Request $request, User $user, ?string $enClaro): void
    {
        // Así Intelephense sabe que es el almacén de sesión completo y no marca
        // flash() como inexistente: la interfaz no lo declara, la clase sí.
        /** @var \Illuminate\Session\Store $sesion */
        $sesion = $request->session();

        if (! $user->recibeAvisoDeContrasena() || ! $user->esContrasenaPorDefecto($enClaro)) {
            $sesion->forget('password_por_defecto');
            return;
        }

        $sesion->put('password_por_defecto', true);
        $sesion->flash('avisar_password', true);
    }

    /**
     * Libera la computadora desde la que entró, si quien entra es del personal.
     *
     * Los equipos con bloqueo abren el sistema diciendo qué máquina son
     * (/redirect?equipo=1-33) y RecordarEquipo lo deja apuntado en la sesión. El
     * alumno se libera registrando su entrada; el personal no tiene nada que
     * registrar, así que le basta con iniciar sesión ahí.
     */
    private function liberarEquipoSiEsPersonal(Request $request, User $user): void
    {
        $equipo = $request->session()->get('equipo_del_laboratorio');

        if (! is_array($equipo) || ! DesbloqueoDePersonal::puedeLiberar($user)) {
            return;
        }

        DesbloqueoDePersonal::abrir($equipo['centro'], $equipo['maquina'], $user);

        // Se guarda para poder devolver el equipo al bloqueo al cerrar sesión.
        $request->session()->put('equipo_liberado', $equipo);
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request)
    {
        $usuario = Auth::user();

        // El equipo que se liberó al entrar vuelve a bloquearse al salir.
        $equipoLiberado = $request->session()->get('equipo_liberado');
        if (is_array($equipoLiberado)) {
            DesbloqueoDePersonal::cerrar($equipoLiberado['centro'], $equipoLiberado['maquina']);
        }

        // Si el alumno se va dejando abierta su sesión de uso libre, se cierra
        // aquí: la computadora queda libre para el siguiente y, en los equipos
        // con bloqueo, vuelve a pedir registro en lugar de quedarse a su nombre.
        $terminoUsoLibre = ($usuario && $usuario->rol === 'Alumno')
            ? $this->terminarUsoLibreAbierto($usuario)
            : false;

        // La PC del laboratorio desde la que se entró sigue siendo la misma después de
        // cerrar sesión: se conserva para que el siguiente alumno que entre en ella
        // también sea detectado (invalidate() vacía la sesión completa).
        $equipoDelLaboratorio = $request->session()->get(\App\Support\EquipoDelLaboratorio::LLAVE);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        \App\Support\EquipoDelLaboratorio::conservar($request, $equipoDelLaboratorio);

        $salida = redirect(route('login'));

        return $terminoUsoLibre
            ? $salida->with('success', 'Cerraste sesión y se registró tu salida de Uso Libre.')
            : $salida;
    }

    /**
     * Cierra la sesión de uso libre que el alumno tenga abierta hoy.
     * Devuelve true si había una.
     */
    private function terminarUsoLibreAbierto(User $alumno): bool
    {
        $zonaHoraria = 'America/Hermosillo';

        $sesion = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha_hora_registro', Carbon::today($zonaHoraria)->toDateString())
            ->whereNull('fecha_hora_salida')
            ->first();

        if (! $sesion) {
            return false;
        }

        $sesion->update(['fecha_hora_salida' => Carbon::now($zonaHoraria)]);

        // El mismo resumen de la visita que al pulsar "Terminar sesión".
        AvisosCorreo::salidaUsoLibre($alumno, $sesion);

        return true;
    }
}
