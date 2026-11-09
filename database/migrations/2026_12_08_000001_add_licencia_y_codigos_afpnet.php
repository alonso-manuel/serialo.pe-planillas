<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Días de licencia sin goce de haber por trabajador y mes (suspensión perfecta, tabla 21 código 05).
 * - Código de cada AFP en AFPnet (columna «AFP» del archivo de planilla: HA, IN, PR, RI).
 */
return new class extends Migration
{
    /** codigo interno => código AFPnet (Guía rápida para Empleadores de AFPnet, act. 20.08.26). */
    private const AFPNET = ['HABITAT' => 'HA', 'INTEGRA' => 'IN', 'PRIMA' => 'RI', 'PROFUTURO' => 'PR'];

    public function up(): void
    {
        Schema::table('planilla_lineas', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_licencia')->default(0)->after('tipo_subsidio');
        });

        DB::table('tipos_suspension')->where('codigo', '05')->update(['uso' => 'licencia']);

        Schema::table('afps', function (Blueprint $table) {
            $table->string('codigo_afpnet', 2)->nullable()->after('codigo_sunat');
        });

        foreach (self::AFPNET as $codigo => $afpnet) {
            DB::table('afps')->where('codigo', $codigo)->update(['codigo_afpnet' => $afpnet]);
        }
    }

    public function down(): void
    {
        Schema::table('afps', fn (Blueprint $table) => $table->dropColumn('codigo_afpnet'));
        DB::table('tipos_suspension')->where('codigo', '05')->update(['uso' => null]);
        Schema::table('planilla_lineas', fn (Blueprint $table) => $table->dropColumn('dias_licencia'));
    }
};
