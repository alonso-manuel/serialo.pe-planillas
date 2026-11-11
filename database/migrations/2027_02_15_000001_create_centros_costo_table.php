<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros de costo (v1.2): catálogo por empresa, el del trabajador (con vigencia en su historial) y el que tenía
 * en cada línea de planilla (foto al calcular, para que los reportes de meses pasados no cambien).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo'], 'centros_costo_codigo_unique');
        });

        Schema::table('trabajadores', function (Blueprint $table) {
            $table->foreignId('centro_costo_id')->nullable()->after('establecimiento_id')->constrained('centros_costo')->nullOnDelete();
        });

        Schema::table('planilla_lineas', function (Blueprint $table) {
            $table->foreignId('centro_costo_id')->nullable()->after('trabajador_id')->constrained('centros_costo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planilla_lineas', fn (Blueprint $t) => $t->dropConstrainedForeignId('centro_costo_id'));
        Schema::table('trabajadores', fn (Blueprint $t) => $t->dropConstrainedForeignId('centro_costo_id'));
        Schema::dropIfExists('centros_costo');
    }
};
