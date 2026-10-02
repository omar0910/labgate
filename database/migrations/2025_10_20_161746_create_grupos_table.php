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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
        $table->string('nombre_grupo'); // Ej: "Lista-Fundamentos-Lunes"

        // La conexión a la tabla 'semestres'
        $table->foreignId('semestre_id')->constrained('semestres')
              ->onDelete('cascade'); // Si se borra un semestre, se borran sus grupos

        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
