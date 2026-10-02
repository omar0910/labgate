<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dar de baja" en lugar de borrar usuarios.
 *
 * Borrar a un alumno borraba en cascada todo su historial de asistencias, y
 * borrar a alguien del personal se llevaba los reportes de fallas que levantó.
 * Ahora la cuenta sólo se desactiva: no puede entrar ni aparece en las listas,
 * pero su historial sigue ahí y se puede reactivar.
 *
 * Todos los usuarios existentes quedan activos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('rol')->index();
            $table->timestamp('fecha_baja')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['activo']);
            $table->dropColumn(['activo', 'fecha_baja']);
        });
    }
};
