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
        Schema::table('equipos', function (Blueprint $table) {
            $table->integer('usos_historicos')->default(0)->after('usos_acumulados');
            $table->timestamp('ultimo_mantenimiento')->nullable()->after('usos_historicos');
        });
    }

    public function down()
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['usos_historicos', 'ultimo_mantenimiento']);
        });
    }
};
