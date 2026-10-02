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
        Schema::table('horarios', function (Blueprint $table) {
            // Agregamos solo la columna que falta
            // La ponemos después del ID para que sea fácil de ver
            $table->string('tipo_reserva')->default('recurrente')->after('id');
        });
    }

    public function down()
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropColumn('tipo_reserva');
        });
    }
};
