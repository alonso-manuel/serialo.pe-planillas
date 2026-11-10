<?php

use App\Models\Auditoria;
use App\Models\Parametro;
use Database\Seeders\ParametroSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renta de quinta categoría (v1.1):
 * - parámetros con vigencia (deducción 7 UIT, cortes y tasas de los tramos) en instalaciones existentes;
 * - detalle del cálculo en cada línea de planilla;
 * - renta y retenciones de otros empleadores del año (certificado que entrega el trabajador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_lineas', function (Blueprint $table) {
            $table->json('detalle_quinta')->nullable()->after('neto_pagar');
        });

        Schema::create('rentas_otros_empleadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->string('ruc_empleador', 11)->nullable();
            $table->string('empleador')->nullable();
            $table->decimal('renta', 12, 2);
            $table->decimal('retencion', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['trabajador_id', 'anio']);
        });

        Auditoria::sinRegistrar(function () {
            // Instalación nueva (o tests): los parámetros los carga ParametroSeeder.
            if (! Parametro::query()->exists()) {
                return;
            }

            foreach (ParametroSeeder::quinta() as [$clave, $valor, $desde, $base]) {
                if (! Parametro::query()->where('clave', $clave->value)->exists()) {
                    Parametro::query()->create(['clave' => $clave->value, 'vigente_desde' => $desde, 'valor' => $valor, 'base_legal' => $base]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentas_otros_empleadores');

        Schema::table('planilla_lineas', function (Blueprint $table) {
            $table->dropColumn('detalle_quinta');
        });
    }
};
