<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién resolvió cada reporte de falla.
 *
 * Hasta ahora sólo quedaba la fecha (y ni eso bien: se tomaba la de la última
 * modificación del registro). La columna 'fecha_resolucion' ya existía pero nadie
 * la llenaba: desde ahora se llena al cerrar el reporte, junto con quién lo cerró.
 *
 * A los que ya estaban resueltos se les pone como fecha la de su última
 * modificación, que es cuando se cerraron; quién los cerró no se sabe y se queda
 * vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            // Si alguna vez se borrara el usuario, el reporte se conserva sin ese dato
            $table->foreignId('resuelta_por')->nullable()->after('nota_resolucion')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('incidencias')
            ->whereIn('estado', ['resuelta', 'resuelto'])
            ->whereNull('fecha_resolucion')
            ->update(['fecha_resolucion' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resuelta_por');
        });
    }
};
