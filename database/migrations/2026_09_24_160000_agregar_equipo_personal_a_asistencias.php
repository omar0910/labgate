<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencias a clase con equipo personal (la laptop del alumno).
 *
 * Hasta ahora el alumno tenía que escribir el número de una PC del laboratorio
 * para registrar su asistencia, aunque trabajara en su propia computadora. Con
 * esta marca queda registrado que estuvo en clase sin ocupar una PC: sin número
 * de máquina, no suma desgaste a ningún equipo y no desbloquea ninguno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->boolean('equipo_personal')->default(false)->after('numero_maquina');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('equipo_personal');
        });
    }
};
