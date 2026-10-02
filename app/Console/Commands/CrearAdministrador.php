<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CrearAdministrador extends Command
{
    /**
     * El nombre del comando que escribiremos en la terminal.
     *
     * Se puede usar de dos formas:
     *   php artisan admin:crear                       (pregunta los datos, la contraseña no se ve al teclearla)
     *   php artisan admin:crear --usuario=jefe        (pregunta sólo lo que falte)
     */
    protected $signature = 'admin:crear
                            {--usuario= : Nombre de usuario con el que se inicia sesión}
                            {--nombre= : Nombre de la persona}
                            {--password= : Contraseña (si se omite, se pide en pantalla sin mostrarla)}
                            {--forzar : Restablece la contraseña de un usuario existente sin pedir confirmación}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Crea un usuario Administrador, o restablece la contraseña de uno que ya exista. Sirve para recuperar el acceso al sistema.';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $this->newLine();
        $this->line('  <fg=green>Alta de administrador</> — ' . config('marca.sistema'));
        $this->newLine();

        // --- Usuario ---
        $usuario = $this->option('usuario') ?: $this->ask('Nombre de usuario para iniciar sesión');
        $usuario = trim($usuario);

        if ($usuario === '') {
            $this->error('El nombre de usuario no puede quedar vacío.');
            return Command::FAILURE;
        }

        $existente = User::where('username', $usuario)->first();

        // --- Si ya existe, sólo se le cambia la contraseña (previa confirmación) ---
        if ($existente) {
            $this->newLine();
            $this->warn("Ya existe un usuario llamado «{$usuario}»:");
            $this->line("    Nombre: {$existente->name}");
            $this->line("    Rol:    {$existente->rol}");
            $this->newLine();

            // --forzar evita la pregunta: útil en servidores donde la terminal no es interactiva
            if (!$this->option('forzar')) {
                if (!$this->confirm('¿Quieres restablecerle la contraseña y dejarlo como Administrador?', false)) {
                    $this->line('  No se hizo ningún cambio. (Usa --forzar para hacerlo sin preguntar.)');
                    return Command::SUCCESS;
                }
            }
        }

        // --- Nombre (sólo si es alta nueva) ---
        $nombre = $this->option('nombre');
        if (!$existente && !$nombre) {
            $nombre = $this->ask('Nombre de la persona', 'Administrador');
        }

        // --- Contraseña ---
        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Contraseña (no se muestra al teclearla)');
            $confirmacion = $this->secret('Repite la contraseña');

            if ($password !== $confirmacion) {
                $this->error('Las contraseñas no coinciden. No se hizo ningún cambio.');
                return Command::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');
            return Command::FAILURE;
        }

        // --- Guardar. El modelo User hashea la contraseña automáticamente ---
        if ($existente) {
            $existente->password = $password;
            $existente->rol = 'Administrador';
            $existente->save();

            $this->newLine();
            $this->info("  Listo. Se restableció la contraseña de «{$usuario}» y quedó como Administrador.");
        } else {
            User::create([
                'name'     => $nombre,
                'username' => $usuario,
                'password' => $password,
                'rol'      => 'Administrador',
            ]);

            $this->newLine();
            $this->info("  Listo. Se creó el administrador «{$usuario}».");
        }

        $this->newLine();
        $this->line('  Ya puedes entrar en la pantalla de acceso con ese usuario y contraseña.');
        $this->newLine();

        return Command::SUCCESS;
    }
}
