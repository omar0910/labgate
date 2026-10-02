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
        Schema::table('materias', function (Blueprint $table) {
            // Agregamos la columna 'creditos' después del nombre
            $table->integer('creditos')->nullable()->after('nombre_materia');
        });
    }

    public function down()
    {
        Schema::table('materias', function (Blueprint $table) {
            $table->dropColumn('creditos');
        });
    }
};
