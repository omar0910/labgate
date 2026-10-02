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
        Schema::table('incidencias', function (Blueprint $table) {
            // Agregamos la columna para la nota, permitiendo que sea nula por si el admin tiene prisa y no escribe nada
            $table->text('nota_resolucion')->nullable()->after('estado');
        });
    }

    public function down()
    {
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropColumn('nota_resolucion');
        });
    }
};
