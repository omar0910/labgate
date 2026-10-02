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
            // Agregamos la columna, pero permitimos que sea NULA para no romper los registros viejos
            $table->foreignId('semestre_id')
                ->nullable() // <--- ESTO SALVA TUS DATOS
                ->constrained('semestres')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('horarios', function (Blueprint $table) {
            // Si revertimos, borramos la columna
            $table->dropForeign(['semestre_id']);
            $table->dropColumn('semestre_id');
        });
    }
};
