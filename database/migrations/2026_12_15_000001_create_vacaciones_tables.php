<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones (semana 6, D.Leg. 713):
 * - goces de vacaciones por trabajador (fecha de inicio y días calendario);
 * - días de vacaciones de cada línea de planilla (se derivan de los goces);
 * - tabla 21 código 23 (S.I. descanso vacacional) para el .snl del PLAME;
 * - concepto 0118 (remuneración vacacional) pasa a ser de sistema;
 * - días de vacaciones por régimen como parámetros con vigencia (si la instalación ya tiene parámetros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones_goce', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->unsignedSmallInteger('dias');
            $table->date('fecha_fin');
            $table->string('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['trabajador_id', 'fecha_inicio']);
        });

        Schema::table('planilla_lineas', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_vacaciones')->default(0)->after('dias_licencia');
        });

        DB::table('tipos_suspension')->where('codigo', '23')->update(['uso' => 'vacaciones']);
        DB::table('conceptos')->where('codigo_plame', '0118')->update(['sistema' => true, 'activo' => true]);

        if (DB::table('parametros')->exists()) {
            foreach ([['dias_vac_general', '30.0000', '1991-11-01', 'D.Leg. 713, art. 10'],
                ['dias_vac_pequena', '15.0000', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE)'],
                ['dias_vac_micro', '15.0000', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE)']] as [$clave, $valor, $desde, $base]) {
                if (! DB::table('parametros')->where('clave', $clave)->exists()) {
                    DB::table('parametros')->insert([
                        'clave' => $clave, 'valor' => $valor, 'vigente_desde' => $desde, 'base_legal' => $base,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('parametros')->whereIn('clave', ['dias_vac_general', 'dias_vac_pequena', 'dias_vac_micro'])->delete();
        DB::table('conceptos')->where('codigo_plame', '0118')->update(['sistema' => false]);
        DB::table('tipos_suspension')->where('codigo', '23')->update(['uso' => null]);
        Schema::table('planilla_lineas', fn (Blueprint $table) => $table->dropColumn('dias_vacaciones'));
        Schema::dropIfExists('vacaciones_goce');
    }
};
