<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class ProfesoresImport implements ToModel, WithHeadingRow
{
    /** Columna sin la cual el archivo no sirve. */
    const COLUMNA_CLAVE = 'rfc';

    /** Profesores dados de alta. */
    public $creados = 0;

    /** Profesores que ya existían y se actualizaron. */
    public $actualizados = 0;

    /** De los actualizados, los que estaban dados de baja y se reactivaron. */
    public $reactivados = 0;

    /** Filas descartadas por venir sin RFC. */
    public $ignorados = 0;

    /** Los encabezados se revisan una sola vez, con la primera fila. */
    protected $columnasRevisadas = false;

    public function model(array $row)
    {
        $this->revisarColumnas($row);

        // 1. VALIDAR CLAVE (RFC)
        if (empty($row['rfc'])) {
            $this->ignorados++;
            return null;
        }

        // LIMPIEZA EXTREMA DEL RFC: 
        // 1. Quitamos espacios invisibles raros de Excel (\x{00A0}, \x{200B}) y espacios normales
        $rfcLimpio = preg_replace('/[\s\x{200B}\x{00A0}]+/u', '', $row['rfc']);
        // 2. Lo pasamos a mayúsculas estrictas
        $rfc = strtoupper(trim($rfcLimpio));

        // 2. EXTRAER COLUMNAS DEL EXCEL
        $nombreCompleto = isset($row['nombre']) ? trim($row['nombre']) : '';
        $departamento   = isset($row['departamento']) ? trim(strtoupper($row['departamento'])) : null;

        // 3. ALGORITMO INTELIGENTE PARA SEPARAR NOMBRES Y APELLIDOS
        // Pasamos todo a minúsculas y quitamos espacios dobles para analizarlo bien
        $nombreLimpio = mb_strtolower($nombreCompleto, 'UTF-8');
        $nombreLimpio = preg_replace('/\s+/', ' ', $nombreLimpio);
        $palabras = explode(' ', $nombreLimpio);

        // Diccionario de conectores de apellidos compuestos
        $prefijos = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa', 'mac', 'mc', 'van', 'von'];

        $partesReales = [];
        $compuesto = '';

        foreach ($palabras as $palabra) {
            // Si la palabra es un conector, la guardamos temporalmente
            if (in_array($palabra, $prefijos)) {
                $compuesto .= $palabra . ' ';
            } else {
                // Si no es conector, la unimos con los conectores anteriores (si había) y la guardamos
                $partesReales[] = trim($compuesto . $palabra);
                $compuesto = ''; // Reseteamos el temporal
            }
        }

        $primerNombre = '';
        $paterno = '';
        $materno = '';
        $nombresGuardar = '';

        $totalPartes = count($partesReales);

        if ($totalPartes == 1) {
            // Solo tiene 1 palabra (ej: "Juan")
            $nombresGuardar = $partesReales[0];
            $primerNombre = $partesReales[0];
        } elseif ($totalPartes == 2) {
            // Tiene 2 palabras (ej: "Juan Pérez")
            $nombresGuardar = $partesReales[0];
            $primerNombre = $partesReales[0];
            $paterno = $partesReales[1];
        } elseif ($totalPartes >= 3) {
            // Tiene 3 o más palabras (ej: "Edgar", "de la Rosa", "Aguilar")
            $materno = array_pop($partesReales); // Saca la última pieza (Materno)
            $paterno = array_pop($partesReales); // Saca la penúltima pieza (Paterno)
            $nombresGuardar = implode(' ', $partesReales); // Lo que sobra son los nombres

            // Para el username, tomamos solo el primer nombre (ej: "Maria" de "Maria de los Angeles")
            $primerNombre = explode(' ', $nombresGuardar)[0];
        }

        // 4. GENERAR USERNAME Y CORREO BASE (INTACTO)
        $inicialPaterno = !empty($paterno) ? mb_substr($paterno, 0, 1, 'UTF-8') : '';
        $inicialMaterno = !empty($materno) ? mb_substr($materno, 0, 1, 'UTF-8') : '';

        $usernameBase = mb_strtolower($primerNombre . '.' . $inicialPaterno . $inicialMaterno, 'UTF-8');
        $usernameBase = Str::ascii($usernameBase); // Quita acentos y tildes
        $usernameBase = preg_replace('/[^a-z0-9.]/', '', $usernameBase); // Elimina cualquier símbolo raro extra

        $username = $usernameBase;
        $email = $username . '@' . config('marca.dominio_correo');

        // 5. GUARDAR O ACTUALIZAR EN BASE DE DATOS (INTACTO)
        $user = User::where('rfc', $rfc)->first();

        if ($user) {
            // Si estaba dado de baja y viene en la lista oficial de docentes, vuelve
            if (! $user->activo) {
                $user->reactivar();
                $this->reactivados++;
            }

            // Si el RFC ya existe, actualizamos los datos
            $user->update([
                'name'             => mb_strtoupper($nombresGuardar, 'UTF-8'),
                'apellido_paterno' => mb_strtoupper($paterno, 'UTF-8'),
                'apellido_materno' => mb_strtoupper($materno, 'UTF-8'),
                'academia'         => $departamento,
            ]);
            $this->actualizados++;
            return $user;
        } else {

            // --- SISTEMA ANTI-DUPLICADOS (INTACTO) ---
            $contador = 1;
            while (User::where('email', $email)->orWhere('username', $username)->exists()) {
                $username = $usernameBase . $contador;
                $email = $username . '@' . config('marca.dominio_correo');
                $contador++;
            }
            // ---------------------------------------

            $this->creados++;

            // CREAR PROFESOR NUEVO
            return new User([
                'rfc'              => $rfc,
                'name'             => mb_strtoupper($nombresGuardar, 'UTF-8'),
                'apellido_paterno' => mb_strtoupper($paterno, 'UTF-8'),
                'apellido_materno' => mb_strtoupper($materno, 'UTF-8'),
                'academia'         => $departamento,
                'username'         => $username,
                'email'            => $email,
                'password'         => Hash::make($rfc), // Contraseña por defecto: El RFC
                'rol'              => 'Profesor',
                'matricula'        => null,
            ]);
        }
    }

    /**
     * Corta la importación si el archivo no es el que se espera, en lugar de
     * recorrerlo entero sin registrar nada y terminar con un mensaje de éxito.
     */
    protected function revisarColumnas(array $row)
    {
        if ($this->columnasRevisadas) {
            return;
        }

        $this->columnasRevisadas = true;

        if (!array_key_exists(self::COLUMNA_CLAVE, $row)) {
            throw new \Exception(
                'El archivo no tiene la columna RFC en la primera fila. ' .
                'Revisa que sea el archivo correcto de profesores.'
            );
        }
    }
}
