<?php

use App\Models\Auditoria;
use App\Models\Parametro;
use Database\Seeders\ParametroSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuarta categoría (v1.1): prestadores de servicios y sus recibos por honorarios por periodo (mes de pago),
 * con la retención calculada; parámetros con vigencia (tasa y monto mínimo para retener).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestadores_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_documento', 2)->default('06');      // tabla 3: 06 RUC (domiciliado)
            $table->string('numero_documento', 15);
            $table->string('apellido_paterno', 40);
            $table->string('apellido_materno', 40)->nullable();
            $table->string('nombres', 40);
            $table->boolean('domiciliado')->default(true);
            $table->unsignedSmallInteger('suspension_anio')->nullable(); // ejercicio de su constancia de suspensión de retenciones
            $table->timestamps();
            $table->unique(['empresa_id', 'tipo_documento', 'numero_documento'], 'prestadores_documento_unique');
        });

        Schema::create('recibos_honorarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained()->cascadeOnDelete();   // mes de pago
            $table->foreignId('prestador_id')->constrained('prestadores_servicio')->cascadeOnDelete();
            $table->char('tipo_comprobante', 1)->default('R');                  // tabla 23
            $table->string('serie', 4);
            $table->string('numero', 8);
            $table->date('fecha_emision');
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->decimal('retencion', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['prestador_id', 'tipo_comprobante', 'serie', 'numero'], 'recibos_comprobante_unique');
        });

        Auditoria::sinRegistrar(function () {
            if (! Parametro::query()->exists()) {
                return; // instalación nueva o tests: los carga ParametroSeeder
            }
            foreach (ParametroSeeder::cuarta() as [$clave, $valor, $desde, $base]) {
                if (! Parametro::query()->where('clave', $clave->value)->exists()) {
                    Parametro::query()->create(['clave' => $clave->value, 'vigente_desde' => $desde, 'valor' => $valor, 'base_legal' => $base]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_honorarios');
        Schema::dropIfExists('prestadores_servicio');
    }
};
