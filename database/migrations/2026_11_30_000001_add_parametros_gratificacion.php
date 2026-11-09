<?php

use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Parametro;
use Database\Seeders\ParametroSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Gratificación (semana 4) en instalaciones existentes:
 * - parámetros con vigencia (bonificación extraordinaria y factor por régimen);
 * - los conceptos 0406 y 0312 pasan a ser «de sistema» (los genera el motor).
 * Solo agrega lo que falta: no toca valores que el cliente ya haya registrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Auditoria::sinRegistrar(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        // Instalación nueva (o tests): los parámetros los carga ParametroSeeder.
        if (! Parametro::query()->exists()) {
            return;
        }

        foreach (ParametroSeeder::gratificacion() as [$clave, $valor, $desde, $base]) {
            if (! Parametro::query()->where('clave', $clave->value)->exists()) {
                Parametro::query()->create(['clave' => $clave->value, 'vigente_desde' => $desde, 'valor' => $valor, 'base_legal' => $base]);
            }
        }

        Concepto::query()->whereIn('codigo_plame', ['0406', '0312'])->update(['sistema' => true, 'activo' => true]);
    }

    public function down(): void
    {
        Concepto::query()->whereIn('codigo_plame', ['0406', '0312'])->update(['sistema' => false]);
    }
};
