<?php

use App\Enums\TipoConcepto;
use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Parametro;
use Database\Seeders\ParametroSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indemnización por despido arbitrario en la liquidación (v1.1):
 * - columnas de la liquidación (si corresponde, vencimiento del plazo fijo y los montos 0501 / 0503);
 * - conceptos 0501 y 0503 de la tabla 22 y parámetros por régimen;
 * - corrección: la indemnización por vacaciones no gozadas es el 0504 de la tabla 22, no el 0911
 *   («Participación en las utilidades»). Se renombra el concepto, sin tocar su cuenta contable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->boolean('despido_arbitrario')->default(false)->after('estado');
            $table->date('fin_contrato')->nullable()->after('despido_arbitrario');
            $table->decimal('indemnizacion_despido', 12, 2)->default(0)->after('vacaciones_truncas');
            $table->decimal('indemnizacion_plazo_fijo', 12, 2)->default(0)->after('indemnizacion_despido');
        });

        Auditoria::sinRegistrar(function () {
            // Instalación nueva (o tests): conceptos y parámetros los cargan sus seeders.
            if (! Concepto::query()->exists()) {
                return;
            }

            if (! Concepto::query()->where('codigo_plame', '0504')->exists()) {
                Concepto::query()->where('codigo_plame', '0911')->where('sistema', true)
                    ->update(['codigo_plame' => '0504', 'nombre' => 'Indemnización por vacaciones no gozadas']);
            }

            foreach (['0501' => 'Indemnización por despido injustificado u hostilidad', '0503' => 'Indemnización por resolución de contrato sujeto a modalidad'] as $codigo => $nombre) {
                if (! Concepto::query()->where('codigo_plame', $codigo)->exists()) {
                    Concepto::query()->create([
                        'codigo_plame' => $codigo, 'nombre' => $nombre, 'tipo' => TipoConcepto::Ingreso,
                        'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
                        'cuenta_cargo' => '623', 'sistema' => true, 'activo' => true,
                    ]);
                }
            }

            if (Parametro::query()->exists()) {
                foreach (ParametroSeeder::indemnizacion() as [$clave, $valor, $desde, $base]) {
                    if (! Parametro::query()->where('clave', $clave->value)->exists()) {
                        Parametro::query()->create(['clave' => $clave->value, 'vigente_desde' => $desde, 'valor' => $valor, 'base_legal' => $base]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['despido_arbitrario', 'fin_contrato', 'indemnizacion_despido', 'indemnizacion_plazo_fijo']);
        });
    }
};
