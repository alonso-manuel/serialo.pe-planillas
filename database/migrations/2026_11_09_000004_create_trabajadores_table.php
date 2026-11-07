<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->nullable()->constrained()->nullOnDelete();
            $table->char('tipo_documento', 2)->default('01'); // 01 DNI, 04 CE, 07 pasaporte (tabla 3 SUNAT)
            $table->string('numero_documento', 15);
            $table->string('apellido_paterno');
            $table->string('apellido_materno')->nullable();
            $table->string('nombres');
            $table->date('fecha_nacimiento')->nullable();
            $table->char('sexo', 1)->nullable();
            $table->string('cargo')->nullable();
            $table->date('fecha_ingreso');
            $table->date('fecha_cese')->nullable();
            $table->string('motivo_cese', 2)->nullable(); // tabla 17 SUNAT
            $table->string('tipo_contrato', 30)->default('indeterminado');
            $table->decimal('remuneracion_basica', 12, 2);
            $table->boolean('asignacion_familiar')->default(false);
            $table->string('sistema_pension', 10); // App\Enums\SistemaPension
            $table->foreignId('afp_id')->nullable()->constrained('afps')->nullOnDelete();
            $table->string('cuspp', 12)->nullable();
            $table->string('tipo_comision_afp', 10)->nullable(); // flujo | mixta
            $table->string('seguro_salud', 10)->default('essalud'); // App\Enums\SeguroSalud
            $table->string('cuenta_sueldo')->nullable();
            $table->string('cuenta_cts')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'tipo_documento', 'numero_documento']);
        });

        // Historial de cambios de sueldo y condiciones (no se pisa el dato anterior).
        Schema::create('trabajador_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->string('campo', 50);
            $table->string('valor_anterior')->nullable();
            $table->string('valor_nuevo')->nullable();
            $table->date('vigente_desde');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajador_historial');
        Schema::dropIfExists('trabajadores');
    }
};
