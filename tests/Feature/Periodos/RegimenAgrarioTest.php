<?php

namespace Tests\Feature\Periodos;

use App\Models\Empresa;
use App\Models\LiquidacionCts;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\LiquidacionesBeneficios;
use Brick\Math\BigDecimal;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Régimen agrario (Ley 31110) de punta a punta: planilla, gratificación de julio, depósito CTS y ficha.
 * Datos ficticios. ⚠ Validar con el contador.
 */
class RegimenAgrarioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => 'agrario', 'essalud_agrario_mayor' => false]);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function trabajador(string $paterno, bool $prorrateo = true): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'fecha_ingreso' => '2025-01-01', 'fecha_cese' => null,
            'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false, 'prorrateo_agrario' => $prorrateo,
            'sistema_pension' => 'onp', 'afp_id' => null, 'seguro_salud' => 'essalud',
            'entidad_cts' => 'BCP', 'cuenta_cts' => '191-0000001-0-00',
        ]);
    }

    /** @return array<int, PlanillaLinea> trabajador_id => línea calculada */
    private function planilla(int $mes, Trabajador ...$trabajadores): array
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        foreach ($trabajadores as $t) {
            $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        }
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        return $periodo->lineas()->get()->keyBy('trabajador_id')->all();
    }

    private function concepto(PlanillaLinea $linea, string $codigo): ?string
    {
        $c = $linea->conceptos()->whereHas('concepto', fn ($q) => $q->where('codigo_plame', $codigo))->first();

        return $c === null ? null : (string) $c->monto_pagado;
    }

    #[Test]
    public function la_planilla_paga_beneficios_prorrateados_beta_y_essalud_agrario(): void
    {
        $t = $this->trabajador('Alfa');
        $linea = $this->planilla(11, $t)[$t->id];

        $this->assertSame('249.90', $this->concepto($linea, '0406'));
        $this->assertSame('22.49', $this->concepto($linea, '0312'));
        $this->assertSame('145.80', $this->concepto($linea, '0904'));
        $this->assertSame('339.00', $this->concepto($linea, '0314'));
        $this->assertSame('90.00', $this->concepto($linea, '0804')); // 6 %: menos de 100 trabajadores

        $this->empresa->update(['essalud_agrario_mayor' => true]);
        $otro = $this->trabajador('Beta');
        $this->assertSame('135.00', $this->concepto($this->planilla(10, $otro)[$otro->id], '0804')); // 9 % desde 2025
    }

    #[Test]
    public function en_julio_solo_recibe_gratificacion_quien_no_la_prorratea(): void
    {
        $prorrateado = $this->trabajador('Alfa');
        $legal = $this->trabajador('Beta', prorrateo: false);
        foreach ([1, 2, 3, 4, 5, 6] as $mes) {
            $this->planilla($mes, $prorrateado, $legal);
        }

        $julio = $this->planilla(7, $prorrateado, $legal);

        $this->assertSame('249.90', $this->concepto($julio[$prorrateado->id], '0406'), 'Solo la proporción del mes.');
        $this->assertSame('1500.00', $this->concepto($julio[$legal->id], '0406'), 'Gratificación de julio completa.');
        $this->assertSame('135.00', $this->concepto($julio[$legal->id], '0312'));
        $this->assertNull($this->concepto($julio[$legal->id], '0904'));
    }

    #[Test]
    public function el_deposito_cts_excluye_a_quien_la_cobra_cada_mes(): void
    {
        $this->trabajador('Alfa');
        $legal = $this->trabajador('Beta', prorrateo: false);

        $this->actingAs($this->asistente)->post(route('cts.calcular'), ['anio' => 2026, 'mes' => 11])->assertRedirect();

        $this->assertSame([$legal->id], LiquidacionCts::query()->sole()->lineas()->pluck('trabajador_id')->all());
    }

    #[Test]
    public function la_liquidacion_no_paga_truncas_de_lo_ya_prorrateado(): void
    {
        $prorrateado = $this->trabajador('Alfa');
        $legal = $this->trabajador('Beta', prorrateo: false);
        foreach ([$prorrateado, $legal] as $t) {
            $t->update(['fecha_cese' => '2026-11-15', 'motivo_cese' => '01']);
        }

        $sinTruncas = app(LiquidacionesBeneficios::class)->calcular($prorrateado->refresh());
        $this->assertSame('0.00', (string) $sinTruncas->gratificacion_trunca);
        $this->assertSame('0.00', (string) $sinTruncas->cts);
        $this->assertTrue(BigDecimal::of((string) $sinTruncas->vacaciones_truncas)->isPositive());

        $conTruncas = app(LiquidacionesBeneficios::class)->calcular($legal->refresh());
        $this->assertTrue(BigDecimal::of((string) $conTruncas->gratificacion_trunca)->isPositive());
        $this->assertTrue(BigDecimal::of((string) $conTruncas->cts)->isPositive());
    }

    #[Test]
    public function la_empresa_guarda_su_tamano_para_essalud_y_no_lo_ve_otra_empresa(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('empresas.update', $this->empresa), [
            'razon_social' => $this->empresa->razon_social, 'regimen_laboral' => 'agrario',
            'essalud_agrario_mayor' => true, 'seguro_salud' => 'essalud',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->empresa->refresh()->essalud_agrario_mayor);

        $ajena = Empresa::factory()->create(['regimen_laboral' => 'agrario']);
        $this->actingAs($this->asistente)->get(route('empresas.edit', $ajena))->assertForbidden();
    }
}
