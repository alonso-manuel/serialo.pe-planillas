<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 15)->default('abierto'); // App\Enums\EstadoPeriodo
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });

        // Una línea por trabajador y periodo: entradas del mes + totales calculados.
        Schema::create('planilla_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            // Entradas (novedades)
            $table->unsignedTinyInteger('dias_laborados')->default(30);
            $table->unsignedTinyInteger('dias_no_laborados')->default(0);
            $table->unsignedTinyInteger('dias_subsidiados')->default(0);
            $table->unsignedSmallInteger('horas_ordinarias')->default(0);
            $table->unsignedTinyInteger('minutos_ordinarios')->default(0);
            $table->decimal('horas_extra_25', 6, 2)->default(0);
            $table->decimal('horas_extra_35', 6, 2)->default(0);
            // Resultado del cálculo
            $table->decimal('total_ingresos', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('total_aportes_empleador', 12, 2)->default(0);
            $table->decimal('neto_pagar', 12, 2)->default(0);
            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();
            $table->unique(['periodo_id', 'trabajador_id']);
        });

        // Montos manuales del mes: bonos, adelantos, descuentos, etc.
        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_linea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained();
            $table->decimal('monto', 12, 2);
            $table->string('glosa')->nullable();
            $table->timestamps();
        });

        // Detalle calculado por concepto (lo que va a la boleta y al .rem del PLAME).
        Schema::create('planilla_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_linea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained();
            $table->decimal('monto_devengado', 12, 2);
            $table->decimal('monto_pagado', 12, 2);
            $table->timestamps();
            $table->unique(['planilla_linea_id', 'concepto_id']);
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('auditable');
            $table->string('accion', 20); // creado, actualizado, eliminado, cerrado, reabierto
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('planilla_conceptos');
        Schema::dropIfExists('novedades');
        Schema::dropIfExists('planilla_lineas');
        Schema::dropIfExists('periodos');
    }
};
