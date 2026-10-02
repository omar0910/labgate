<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    /** Palabra que hay que escribir para confirmar una restauración. */
    const CONFIRMACION = 'RESTAURAR';

    /**
     * Datos de la conexión a la base, leídos de la configuración de Laravel y no
     * con env(): si en el servidor se cachea la configuración (config:cache u
     * optimize), env() devuelve vacío y el respaldo dejaba de funcionar.
     */
    private function conexion(): array
    {
        $db = config('database.connections.' . config('database.default'));

        return [$db['database'] ?? '', $db['username'] ?? '', $db['password'] ?? '', $db['host'] ?? '127.0.0.1', (string) ($db['port'] ?? '3306')];
    }

    /** Carpeta del servidor donde se guardan los respaldos. */
    private function carpeta(): string
    {
        $directorio = storage_path('app/backups');
        if (!File::exists($directorio)) {
            File::makeDirectory($directorio, 0755, true);
        }

        return $directorio;
    }

    /**
     * Vuelca la base completa a un archivo .sql. Devuelve [bien, mensajes].
     */
    private function volcarBase(string $rutaCompleta): array
    {
        [$dbName, $userName, $password, $host, $port] = $this->conexion();

        // BLINDAJE LINUX: escapeshellarg() protege caracteres especiales en contraseñas
        $passString = empty($password) ? "" : "--password=" . escapeshellarg($password);
        $dumpPath = env('DB_DUMP_PATH', 'mysqldump');

        // Armamos el comando protegiendo todas las variables. --single-transaction
        // saca una foto consistente sin bloquear las tablas: mientras se respalda,
        // el sistema sigue funcionando normal (sin él, el respaldo bloqueaba tablas).
        $comando = "{$dumpPath} --single-transaction --user=" . escapeshellarg($userName) . " {$passString} --host=" . escapeshellarg($host) . " --port=" . escapeshellarg($port) . " " . escapeshellarg($dbName) . " > " . escapeshellarg($rutaCompleta);

        // SOLUCIÓN WINDOWS/LINUX: Usamos 'exec' nativo de PHP
        exec($comando . ' 2>&1', $salida, $codigoDeSalida);

        // Un volcado que "terminó bien" pero quedó vacío tampoco sirve
        $bien = $codigoDeSalida === 0 && File::exists($rutaCompleta) && File::size($rutaCompleta) > 0;

        return [$bien, $salida];
    }

    public function index()
    {
        // Esta función solo muestra la página principal de respaldos
        return view('admin.mantenimiento.backup');
    }

    public function exportar()
    {
        [$dbName] = $this->conexion();

        $fecha = now()->format('Y-m-d_H-i-s');
        $rutaCompleta = $this->carpeta() . "/respaldo_{$dbName}_{$fecha}.sql";

        try {
            [$bien, $salida] = $this->volcarBase($rutaCompleta);

            if (! $bien) {
                return back()->with('error', 'Error del servidor al generar el respaldo: ' . implode("\n", $salida));
            }

            return response()->download($rutaCompleta)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->with('error', 'Excepción al intentar exportar: ' . $e->getMessage());
        }
    }

    public function importar(Request $request)
    {
        $request->validate([
            'backup_file'  => 'required|file',
            'confirmacion' => 'required|string',
        ], [
            'backup_file.required'  => 'Debes seleccionar un archivo de respaldo.',
            'backup_file.file'      => 'El archivo subido no es válido.',
            'confirmacion.required' => 'Escribe ' . self::CONFIRMACION . ' para confirmar la restauración.',
        ]);

        // Restaurar reemplaza TODA la base: hay que escribir la palabra a propósito,
        // no basta con un clic en un aviso.
        if (mb_strtoupper(trim($request->input('confirmacion')), 'UTF-8') !== self::CONFIRMACION) {
            return back()->with('error', 'No se restauró nada: para confirmar hay que escribir ' . self::CONFIRMACION . '.');
        }

        $archivo = $request->file('backup_file');

        if ($archivo->getClientOriginalExtension() !== 'sql') {
            return back()->with('error', 'El archivo debe tener la extensión .sql');
        }

        [$dbName, $userName, $password, $host, $port] = $this->conexion();

        // Red de seguridad: antes de reemplazar nada, se guarda en el servidor una
        // copia de cómo está la base ahora. Si se restauró el archivo equivocado, se
        // puede volver atrás con esa copia. Si la copia falla, no se restaura.
        $nombreCopia = 'antes_de_restaurar_' . now()->format('Y-m-d_H-i-s') . '.sql';

        try {
            [$copiaBien, $salidaCopia] = $this->volcarBase($this->carpeta() . '/' . $nombreCopia);
        } catch (\Exception $e) {
            $copiaBien = false;
            $salidaCopia = [$e->getMessage()];
        }

        if (! $copiaBien) {
            return back()->with('error', 'No se restauró nada: no se pudo guardar primero la copia de seguridad de los datos actuales. '
                . implode("\n", $salidaCopia));
        }

        $rutaCompleta = $archivo->getRealPath();

        // BLINDAJE LINUX: escapeshellarg() protege caracteres especiales
        $passString = empty($password) ? "" : "--password=" . escapeshellarg($password);
        $mysqlPath = env('DB_MYSQL_PATH', 'mysql');

        // Armamos el comando protegiendo todas las variables
        $comando = "{$mysqlPath} --user=" . escapeshellarg($userName) . " {$passString} --host=" . escapeshellarg($host) . " --port=" . escapeshellarg($port) . " " . escapeshellarg($dbName) . " < " . escapeshellarg($rutaCompleta);

        try {
            // SOLUCIÓN WINDOWS/LINUX: Usamos 'exec' nativo de PHP
            exec($comando . ' 2>&1', $salida, $codigoDeSalida);

            if ($codigoDeSalida !== 0) {
                return back()->with('error', 'Error del servidor al restaurar: ' . implode("\n", $salida)
                    . ' (La copia de los datos anteriores quedó guardada como ' . $nombreCopia . '.)');
            }

            return back()->with('success', '¡Restauración exitosa! La base de datos ha regresado al estado del respaldo. '
                . 'Por si hiciera falta, los datos que había antes quedaron guardados en el servidor como ' . $nombreCopia
                . ' (carpeta storage/app/backups).');
        } catch (\Exception $e) {
            return back()->with('error', 'Excepción al intentar importar: ' . $e->getMessage());
        }
    }
}
