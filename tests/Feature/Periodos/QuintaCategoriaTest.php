<?php

namespace Tests\Feature\Periodos;

use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\RentaOtroEmpleador;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Retención de quinta categoría dentro del cálculo del periodo (historial del año, otros empleadores, novedad manual). Datos ficticios. */
class QuintaCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => 'general']);
        $this->admin = User::factory()->admin()->create();
    }

    private function trabajador(string $sueldo = '5000.00', string $ingreso = '2025-01-01'): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => $ingreso, 'remuneracion_basica' => $sueldo]);
    }

    private function calcularMes(int $mes, Trabajador $t): PlanillaLinea
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);

        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        return $linea->refresh();
    }

    private function quinta(PlanillaLinea $linea): ?string
    {
        $concepto = $linea->conceptos()->whereHas('concepto', fn ($q) => $q->where('codigo_plame', '0605'))->first();

        return $concepto === null ? null : (string) $concepto->monto_pagado;
    }

    #[Test]
    public function retiene_cada_mes_con_el_historial_del_anio(): void
    {
        $t = $this->trabajador();

        $enero = $this->calcularMes(1, $t);
        $this->assertSame('256.50', $this->quinta($enero)); // 3,078 ÷ 12 (ver CalculadoraQuintaTest)
        $this->assertSame('4093.50', (string) $enero->neto_pagar); // 5,000 − ONP 650 − 256.50
        $this->assertSame('70900.00', $enero->detalle_quinta['renta_proyectada'] ?? null);
        $this->assertSame(12, $enero->detalle_quinta['divisor'] ?? null);

        // El detalle de la línea muestra el cálculo.
        $this->get(route('periodos.lineas.show', [$enero->periodo_id, $enero->id]))
            ->assertInertia(fn (Assert $p) => $p->where('quinta.retencion', '256.50')->where('quinta.impuesto_anual', '3078.00'));

        $febrero = $this->calcularMes(2, $t);
        $this->assertSame('256.50', $this->quinta($febrero));

        // Abril: usa lo pagado y retenido en enero a marzo.
        $this->calcularMes(3, $t);
        $abril = $this->calcularMes(4, $t);
        $this->assertSame('256.50', $this->quinta($abril));
        $this->assertSame('769.50', $abril->detalle_quinta['retenciones_consideradas'] ?? null);
        $this->assertSame(9, $abril->detalle_quinta['divisor'] ?? null);
    }

    #[Test]
    public function sueldo_bajo_no_tiene_retencion(): void
    {
        $linea = $this->calcularMes(1, $this->trabajador('1500.00'));

        $this->assertNull($this->quinta($linea));
        $this->assertSame('0.00', $linea->detalle_quinta['retencion'] ?? null);
    }

    #[Test]
    public function suma_la_renta_y_retencion_de_otros_empleadores(): void
    {
        $t = $this->trabajador('4000.00', '2026-05-01');
        RentaOtroEmpleador::query()->create(['trabajador_id' => $t->id, 'anio' => 2026, 'empleador' => 'Empleador Anterior Ficticio', 'renta' => '16000.00', 'retencion' => '300.00']);

        $mayo = $this->calcularMes(5, $t);

        $this->assertSame('126.13', $this->quinta($mayo)); // ver CalculadoraQuintaTest::ingreso_en_mayo…
        $this->assertSame('53813.33', $mayo->detalle_quinta['renta_proyectada'] ?? null);
    }

    #[Test]
    public function el_0605_registrado_como_novedad_reemplaza_el_calculo(): void
    {
        $t = $this->trabajador();
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 1]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $linea->novedades()->create(['concepto_id' => Concepto::query()->where('codigo_plame', '0605')->value('id'), 'monto' => '100.00']);

        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();
        $linea->refresh();

        $this->assertSame('100.00', $this->quinta($linea));
        $this->assertNull($linea->detalle_quinta);
    }

    #[Test]
    public function otra_empresa_del_estudio_no_cuenta_como_historial(): void
    {
        $t = $this->trabajador();
        $this->calcularMes(1, $t);

        // Mismo mes de febrero, pero el historial de enero es de esta empresa: otra empresa empieza de cero.
        $otra = Empresa::factory()->create();
        $t2 = Trabajador::factory()->for($otra)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '5000.00']);
        $periodo = Periodo::factory()->for($otra)->create(['anio' => 2026, 'mes' => 4]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $t2->id, 'dias_laborados' => 30]);
        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo));

        $this->assertSame('0.00', $linea->refresh()->detalle_quinta['retenciones_consideradas'] ?? null);
    }
}
