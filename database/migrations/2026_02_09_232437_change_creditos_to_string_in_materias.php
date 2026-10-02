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
            // Cambiamos la columna de integer a string para que acepte "2--2--4"
            $table->string('creditos', 50)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('materias', function (Blueprint $table) {
            $table->integer('creditos')->nullable()->change();
        });
    }
};
