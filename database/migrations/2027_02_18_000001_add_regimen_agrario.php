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
 * Régimen laboral agrario (v2.0, Ley 31110 y D.S. 005-2021-MIDAGRI):
 * - empresas.essalud_agrario_mayor: tamaño del empleador para la tasa de EsSalud (art. 57 del reglamento);
 * - trabajadores.prorrateo_agrario: gratificaciones y CTS prorrateadas en la remuneración diaria (art. 7 y 8);
 * - concepto 0314 (BETA) de la tabla 22 y parámetros con vigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('essalud_agrario_mayor')->default(false)->after('regimen_laboral');
        });
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->boolean('prorrateo_agrario')->default(true)->after('asignacion_familiar');
        });

        Auditoria::sinRegistrar(function () {
            if (Concepto::query()->exists() && ! Concepto::query()->where('codigo_plame', '0314')->exists()) {
                Concepto::query()->create([
                    'codigo_plame' => '0314', 'nombre' => 'Bonificación especial por trabajo agrario (BETA) – Ley 31110',
                    'tipo' => TipoConcepto::Ingreso, 'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false,
                    'afecto_quinta' => true, 'cuenta_cargo' => '6214', 'sistema' => true, 'activo' => true,
                ]);
            }

            if (Parametro::query()->exists()) {
                foreach (ParametroSeeder::agrario() as [$clave, $valor, $desde, $base]) {
                    Parametro::query()->firstOrCreate(
                        ['clave' => $clave->value, 'vigente_desde' => $desde],
                        ['valor' => $valor, 'base_legal' => $base],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('essalud_agrario_mayor'));
        Schema::table('trabajadores', fn (Blueprint $table) => $table->dropColumn('prorrateo_agrario'));
    }
};
