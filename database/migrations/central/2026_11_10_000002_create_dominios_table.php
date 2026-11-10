<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Base CENTRAL: subdominios de cada estudio. `tenant_id` es el nombre que espera stancl/tenancy. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dominios', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('tenant_id', 30);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('estudios')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dominios');
    }
};
