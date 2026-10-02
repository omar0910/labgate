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
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();

            // Relación con el laboratorio
            $table->unsignedBigInteger('centro_computo_id');
            $table->foreign('centro_computo_id')->references('id')->on('centro_computos')->onDelete('cascade');

            $table->integer('numero_maquina');

            // El estado vital para bloquearla
            $table->enum('estado', ['disponible', 'mantenimiento', 'fallando'])->default('disponible');

            // El "Odómetro"
            $table->integer('usos_acumulados')->default(0);

            $table->timestamps();

            // Una regla lógica: No puede haber dos "PC #1" en el mismo laboratorio
            $table->unique(['centro_computo_id', 'numero_maquina']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
