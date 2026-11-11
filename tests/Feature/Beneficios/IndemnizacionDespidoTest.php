<?php

namespace Tests\Feature\Beneficios;

use App\Enums\MotivoCese;
use App\Enums\RegimenLaboral;
use App\Enums\TipoContrato;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Indemnización por despido arbitrario dentro de la liquidación (cese 20/09/2026, sueldo 3,000, sin variables).
 * Montos calculados a mano (ver CalculadoraIndemnizacionDespidoTest): ⚠ pendientes de validar con el contador.
 */
class IndemnizacionDespidoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 09:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => RegimenLaboral::General]);
        $this->admin = User::factory()->admin()->create();
    }

    private function despedido(array $datos = []): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'fecha_cese' => '2026-09-20', 'motivo_cese' => MotivoCese::Despido,
            'remuneracion_basica' => '3000.00', 'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null,
            'entidad_cts' => 'BCP', 'tipo_contrato' => TipoContrato::Indeterminado, ...$datos,
        ]);
    }

    private function liquidacion(Trabajador $t): Liquidacion
    {
        return Liquidacion::query()->where('trabajador_id', $t->id)->sole();
    }

    #[Test]
    public function despido_arbitrario_indeterminado_suma_el_0501_y_se_paga_en_la_planilla_del_cese(): void
    {
        $t = $this->despedido();
        $setiembre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 9]);
        $setiembre->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 20]);

        // Sin marcar: no hay indemnización.
        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t))->assertRedirect();
        $sin = $this->liquidacion($t);
        $this->assertSame('0.00', $sin->indemnizacion_despido);
        $totalSin = $sin->total;

        // 1 año, 8 meses y 20 días · 100 diarios × 45 × (1 + 8/12 + 20/360) = 7,750
        $this->post(route('liquidaciones.calcular', $t), ['despido_arbitrario' => true])->assertRedirect();
        $l = $this->liquidacion($t);
        $this->assertTrue($l->despido_arbitrario);
        $this->assertSame('7750.00', $l->indemnizacion_despido);
        $this->assertSame(bcadd($totalSin, '7750.00', 2), $l->total);
        $this->assertSame('7750.00', $l->conceptos()['0501']);

        // Al recalcular sin indicarlo, se conserva.
        $this->post(route('liquidaciones.calcular', $t))->assertRedirect();
        $this->assertSame('7750.00', $this->liquidacion($t)->indemnizacion_despido);

        // La planilla del mes del cese la paga como 0501 (no afecta a ONP).
        $this->post(route('periodos.calcular', $setiembre))->assertSessionHasNoErrors();
        $linea = PlanillaLinea::query()->where('periodo_id', $setiembre->id)->with('conceptos.concepto')->sole();
        $conceptos = $linea->conceptos->mapWithKeys(fn ($c) => [$c->concepto->codigo_plame => (string) $c->monto_pagado])->all();
        $this->assertSame('7750.00', $conceptos['0501']);
        $this->assertArrayNotHasKey('0911', $conceptos);

        // La hoja de liquidación se genera con la sección de la indemnización.
        $this->get(route('liquidaciones.pdf', $l))->assertOk();
        $this->get(route('liquidaciones.index'))->assertInertia(fn (Assert $p) => $p
            ->where('filas.0.es_despido', true)
            ->where('filas.0.liquidacion.despido_arbitrario', true));
    }

    #[Test]
    public function plazo_fijo_pide_el_vencimiento_y_paga_el_0503(): void
    {
        $t = $this->despedido(['tipo_contrato' => TipoContrato::PlazoFijo]);

        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t), ['despido_arbitrario' => true])
            ->assertInertiaFlash('toast.message', 'Indica la fecha de vencimiento del contrato a plazo fijo para calcular la indemnización.');
        $this->assertSame(0, Liquidacion::query()->count());

        // Faltaban 3 meses y 11 días (21/09 al 31/12) · 4,500 × (3 + 11/30) = 15,150
        $this->post(route('liquidaciones.calcular', $t), ['despido_arbitrario' => true, 'fin_contrato' => '2026-12-31'])->assertRedirect();
        $l = $this->liquidacion($t);
        $this->assertSame('15150.00', $l->indemnizacion_plazo_fijo);
        $this->assertSame('0.00', $l->indemnizacion_despido);
        $this->assertSame('2026-12-31', $l->fin_contrato?->toDateString());
        $this->assertSame('15150.00', $l->conceptos()['0503']);
    }

    #[Test]
    public function dentro_del_periodo_de_prueba_avisa_y_no_paga(): void
    {
        $t = $this->despedido(['fecha_ingreso' => '2026-07-01']);

        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t), ['despido_arbitrario' => true])->assertRedirect();

        $l = $this->liquidacion($t);
        $this->assertSame('0.00', $l->indemnizacion_despido);
        $this->assertContains('No corresponde indemnización: el cese ocurrió dentro del periodo de prueba (3 meses).', $l->avisos ?? []);
    }
}
