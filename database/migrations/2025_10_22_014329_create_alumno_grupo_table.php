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
        Schema::create('alumno_grupo', function (Blueprint $table) {
            $table->id();

        // Conexión a la tabla 'alumnos'
        $table->foreignId('alumno_id')->constrained('alumnos')
              ->onDelete('cascade'); // Si se borra un alumno, se borra de la lista

        // Conexión a la tabla 'grupos'
        $table->foreignId('grupo_id')->constrained('grupos')
              ->onDelete('cascade'); // Si se borra un grupo, se borran sus asignaciones

        $table->timestamps();

        // Opcional: Evitar duplicados (que un alumno esté 2 veces en el mismo grupo)
        $table->unique(['alumno_id', 'grupo_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumno_grupo');
    }
};
