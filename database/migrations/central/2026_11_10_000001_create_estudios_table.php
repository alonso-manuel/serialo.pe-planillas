<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL (solo modo SaaS): un estudio por cliente o demo. Sus datos viven en su propia base.
 * `id` es el subdominio (garcia → garcia.serialo.pe); `data` la usa stancl/tenancy para atributos extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudios', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('nombre');
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudios');
    }
};
