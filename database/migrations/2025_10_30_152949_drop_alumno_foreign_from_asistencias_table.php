<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // Eliminamos la llave foránea que apunta a 'alumnos'
            // El nombre 'asistencias_alumno_id_foreign' lo vimos en tu DUMP
            $table->dropForeign('asistencias_alumno_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // Si deshacemos, volvemos a crear la llave foránea
            $table->foreign('alumno_id')->references('id')->on('alumnos');
        });
    }
};
