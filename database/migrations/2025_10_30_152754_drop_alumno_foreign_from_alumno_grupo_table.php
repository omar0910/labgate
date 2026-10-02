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
        Schema::table('alumno_grupo', function (Blueprint $table) {
            // Eliminamos la llave foránea que apunta a la tabla 'alumnos'
            // El nombre 'alumno_grupo_alumno_id_foreign' lo crea Laravel por defecto
            $table->dropForeign('alumno_grupo_alumno_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumno_grupo', function (Blueprint $table) {
            // Si deshacemos la migración, volvemos a crear la llave foránea
            $table->foreign('alumno_id')->references('id')->on('alumnos')->onDelete('cascade');
        });
    }
};
