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
        // Eliminamos la tabla 'alumnos'
        Schema::dropIfExists('alumnos');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Si deshacemos, volvemos a crear la tabla 'alumnos'
        // (Esta es la estructura que tenías en tu DUMP)
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('matricula')->unique();
            $table->string('nombre_completo');
            $table->timestamps();
        });
    }
};
