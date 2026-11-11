<?php

use App\Enums\TipoConcepto;
use App\Models\Auditoria;
use App\Models\Concepto;
use Illuminate\Database\Migrations\Migration;

/**
 * Comisiones (v1.2): conceptos 0103 (comisiones o destajo) y 0104 (comisiones eventuales) de la tabla 22,
 * remunerativos y afectos a todo. Se registran como novedad del mes y entran al promedio de beneficios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Auditoria::sinRegistrar(function () {
            if (! Concepto::query()->exists()) {
                return; // instalación nueva o tests: los carga ConceptoSeeder
            }

            foreach (['0103' => 'Comisiones o destajo', '0104' => 'Comisiones eventuales a trabajadores'] as $codigo => $nombre) {
                if (! Concepto::query()->where('codigo_plame', $codigo)->exists()) {
                    Concepto::query()->create([
                        'codigo_plame' => $codigo, 'nombre' => $nombre, 'tipo' => TipoConcepto::Ingreso,
                        'afecto_essalud' => true, 'afecto_onp' => true, 'afecto_afp' => true, 'afecto_quinta' => true,
                        'cuenta_cargo' => '6212', 'sistema' => false, 'activo' => true,
                    ]);
                }
            }
        });
    }

    public function down(): void {}
};
