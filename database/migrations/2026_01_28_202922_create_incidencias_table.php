<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Alumno que reporta
            // Relación con el laboratorio (puede ser nulo si se borra el lab, pero idealmente no)
            $table->foreignId('centro_computo_id')->nullable()->constrained('centro_computos');
            $table->integer('numero_maquina');
            $table->string('categoria'); // Mouse, Teclado, Monitor, Software, Otro
            $table->text('descripcion')->nullable(); // Detalle: "No hace click"
            $table->string('estado')->default('pendiente'); // pendiente, en_revision, solucionado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
