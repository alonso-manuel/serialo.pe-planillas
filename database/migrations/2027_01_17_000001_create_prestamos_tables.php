<?php

use App\Enums\TipoConcepto;
use App\Models\Auditoria;
use App\Models\Concepto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préstamos al trabajador en cuotas mensuales (v1.1). Cada cuota se descuenta en la planilla de su mes con el
 * concepto 0706 de la tabla 22 («Otros descuentos no deducibles de la base imponible»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->unsignedSmallInteger('numero_cuotas');
            $table->string('glosa', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('prestamo_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('monto', 12, 2);
            $table->index(['anio', 'mes']);
        });

        Auditoria::sinRegistrar(function () {
            if (Concepto::query()->exists() && ! Concepto::query()->where('codigo_plame', '0706')->exists()) {
                Concepto::query()->create([
                    'codigo_plame' => '0706', 'nombre' => 'Otros descuentos no deducibles de la base imponible',
                    'tipo' => TipoConcepto::DescuentoTrabajador, 'afecto_essalud' => false, 'afecto_onp' => false,
                    'afecto_afp' => false, 'afecto_quinta' => false, 'cuenta_abono' => '1411', 'sistema' => false, 'activo' => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_cuotas');
        Schema::dropIfExists('prestamos');
    }
};
