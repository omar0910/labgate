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
        Schema::table('asistencias', function (Blueprint $table) {
            // Agregamos la columna para el número de PC.
            // La ponemos nullable por si hay registros viejos, pero en el futuro será obligatoria.
            $table->integer('numero_maquina')->nullable()->after('horario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('numero_maquina');
        });
    }
};
