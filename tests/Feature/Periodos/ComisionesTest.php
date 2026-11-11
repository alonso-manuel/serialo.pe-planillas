<?php

namespace Tests\Feature\Periodos;

use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comisiones (0103): remunerativas y parte del promedio de variables de la gratificación cuando son regulares.
 * ⚠ Validar con el contador.
 */
class ComisionesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-25 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => 'general']);
        $this->admin = User::factory()->admin()->create();
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'seguro_salud' => 'essalud',
        ]);
    }

    private function mes(int $mes, ?string $comision): PlanillaLinea
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $this->trabajador->id, 'dias_laborados' => 30]);
        if ($comision !== null) {
            $linea->novedades()->create(['concepto_id' => Concepto::query()->where('codigo_plame', '0103')->value('id'), 'monto' => $comision]);
        }
        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        return $linea->refresh();
    }

    private function concepto(PlanillaLinea $linea, string $codigo): ?string
    {
        $c = $linea->conceptos()->whereHas('concepto', fn ($q) => $q->where('codigo_plame', $codigo))->first();

        return $c === null ? null : (string) $c->monto_pagado;
    }

    #[Test]
    public function la_comision_es_remunerativa_y_entra_al_promedio_de_la_gratificacion(): void
    {
        $enero = $this->mes(1, '600.00');
        // Afecta a ONP: (1,500 + 600) × 13 % = 273
        $this->assertSame('273.00', $this->concepto($enero, '0607'));

        foreach ([2, 3, 4, 5, 6] as $mes) {
            $this->mes($mes, '600.00');
        }

        // Julio: gratificación = básico 1,500 + promedio de comisiones (600, en 6 de 6 meses) = 2,100; bonificación 9 % = 189
        $julio = $this->mes(7, null);
        $this->assertSame('2100.00', $this->concepto($julio, '0406'));
        $this->assertSame('189.00', $this->concepto($julio, '0312'));
    }

    #[Test]
    public function comisiones_en_menos_de_tres_meses_no_entran_al_promedio(): void
    {
        $this->mes(1, '600.00');
        $this->mes(2, '600.00');
        foreach ([3, 4, 5, 6] as $mes) {
            $this->mes($mes, null);
        }

        $this->assertSame('1500.00', $this->concepto($this->mes(7, null), '0406'));
    }
}
