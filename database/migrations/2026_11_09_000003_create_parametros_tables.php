<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parámetros legales con vigencia: RMV, UIT, tasas, etc. Nunca se sobrescriben: se agrega una nueva fila con su fecha.
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50);          // App\Enums\ClaveParametro
            $table->decimal('valor', 14, 4);
            $table->date('vigente_desde');
            $table->string('base_legal')->nullable();
            $table->timestamps();
            $table->unique(['clave', 'vigente_desde']);
        });

        Schema::create('afps', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // habitat, integra, prima, profuturo
            $table->string('nombre');
            $table->string('codigo_sunat', 2)->nullable(); // código del régimen pensionario en tablas SUNAT (verificar)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Tasas AFP por mes: SBS las publica mensualmente.
        Schema::create('tasas_afp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('aporte_obligatorio', 6, 4); // ej. 0.1000
            $table->decimal('prima_seguro', 6, 4);       // ej. 0.0137
            $table->decimal('comision_flujo', 6, 4);     // comisión sobre remuneración
            $table->decimal('comision_mixta', 6, 4)->default(0); // comisión sobre flujo en esquema mixto
            $table->decimal('remuneracion_maxima_asegurable', 12, 2); // tope para la prima de seguro
            $table->timestamps();
            $table->unique(['afp_id', 'anio', 'mes']);
        });

        // Conceptos de la tabla 22 del PLAME y su afectación.
        Schema::create('conceptos', function (Blueprint $table) {
            $table->id();
            $table->char('codigo_plame', 4)->unique();
            $table->string('nombre');
            $table->string('tipo', 20); // App\Enums\TipoConcepto
            $table->boolean('afecto_essalud')->default(false);
            $table->boolean('afecto_onp')->default(false);
            $table->boolean('afecto_afp')->default(false);
            $table->boolean('afecto_quinta')->default(false);
            $table->boolean('sistema')->default(false); // lo calcula el motor (no se ingresa a mano)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos');
        Schema::dropIfExists('tasas_afp');
        Schema::dropIfExists('afps');
        Schema::dropIfExists('parametros');
    }
};
