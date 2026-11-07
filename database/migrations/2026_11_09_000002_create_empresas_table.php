<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->char('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('regimen_laboral', 20);   // App\Enums\RegimenLaboral
            $table->string('seguro_salud', 10)->default('essalud'); // App\Enums\SeguroSalud (por defecto de la empresa)
            $table->string('representante_legal')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 4)->default('0000'); // código de establecimiento SUNAT
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_user', function (Blueprint $table) {
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_user');
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('empresas');
    }
};
