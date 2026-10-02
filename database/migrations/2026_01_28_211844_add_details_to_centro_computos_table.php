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
        Schema::table('centro_computos', function (Blueprint $table) {
            // Agregamos capacidad (por defecto 30, por si ya tienes datos)
            $table->integer('capacidad')->default(30)->after('nombre_centro');

            // Agregamos bandera de uso libre (por defecto sí, true)
            $table->boolean('permite_uso_libre')->default(true)->after('capacidad');
        });
    }

    public function down()
    {
        Schema::table('centro_computos', function (Blueprint $table) {
            $table->dropColumn(['capacidad', 'permite_uso_libre']);
        });
    }
};
