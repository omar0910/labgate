<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uso libre sólo desde las computadoras del laboratorio.
 *
 * El uso libre es para usar las PCs del centro: quien trae su laptop no ocupa
 * ninguna. Con esta opción encendida, el uso libre de ese laboratorio sólo se
 * puede registrar desde una de sus computadoras (las que identifica el script de
 * bloqueo), no desde un equipo personal.
 *
 * Viene apagada: se enciende por laboratorio cuando el script ya está instalado
 * en sus PCs. Si se encendiera antes, desde las PCs sin script no se podría
 * registrar el uso libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_computos', function (Blueprint $table) {
            $table->boolean('uso_libre_solo_en_sus_pcs')->default(false)->after('permite_uso_libre');
        });
    }

    public function down(): void
    {
        Schema::table('centro_computos', function (Blueprint $table) {
            $table->dropColumn('uso_libre_solo_en_sus_pcs');
        });
    }
};
