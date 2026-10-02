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
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // El Profesor
            $table->foreignId('materia_id')->constrained('materias');
            $table->foreignId('grupo_id')->constrained('grupos');
            $table->foreignId('centro_computo_id')->constrained('centro_computos');
            $table->string('dia_semana'); // Ej: "Lunes"
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
