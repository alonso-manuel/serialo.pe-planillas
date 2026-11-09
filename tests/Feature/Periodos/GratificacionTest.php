<?php

namespace Tests\Feature\Periodos;

use App\Enums\ClaveParametro;
use App\Enums\RegimenLaboral;
use App\Models\Empresa;
use App\Models\Parametro;
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

/** Gratificación de diciembre dentro de la planilla del mes. Datos ficticios. */
class GratificacionTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-10');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => RegimenLaboral::General]);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function trabajador(array $datos = []): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'fecha_cese' => null, ...$datos,
        ]);
    }

    private function periodo(int $mes, Trabajador ...$trabajadores): Periodo
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        foreach ($trabajadores as $t) {
            $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        }

        return $periodo;
    }

    /** @return array<string, string> código => monto */
    private function conceptos(PlanillaLinea $linea): array
    {
        return $linea->conceptos()->with('concepto')->get()
            ->mapWithKeys(fn ($c) => [$c->concepto->codigo_plame => (string) $c->monto_pagado])->all();
    }

    private function calcular(Periodo $periodo): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo))->assertRedirect();
        $this->assertSame('calculado', $periodo->refresh()->estado->value, implode(' ', session('problemas_calculo', [])));
    }

    #[Test]
    public function diciembre_agrega_gratificacion_y_bonificacion_sin_afectar_aportes(): void
    {
        $t = $this->trabajador();
        $diciembre = $this->periodo(12, $t);

        $this->calcular($diciembre);

        $c = $this->conceptos($diciembre->lineas()->sole());
        $this->assertSame('1500.00', $c['0406']);   // semestre completo
        $this->assertSame('135.00', $c['0312']);    // 9 %
        $this->assertSame('195.00', $c['0607']);    // ONP 13 % solo sobre el básico
        $this->assertSame('135.00', $c['0804']);    // EsSalud 9 % solo sobre el básico
        $this->assertSame('2940.00', $diciembre->lineas()->sole()->neto_pagar); // 1500 + 1500 + 135 − 195
    }

    #[Test]
    public function un_mes_que_no_es_julio_ni_diciembre_no_tiene_gratificacion(): void
    {
        $noviembre = $this->periodo(11, $this->trabajador());

        $this->calcular($noviembre);

        $this->assertArrayNotHasKey('0406', $this->conceptos($noviembre->lineas()->sole()));
    }

    #[Test]
    public function usa_las_faltas_y_horas_extra_de_los_meses_anteriores_del_semestre(): void
    {
        $t = $this->trabajador(['remuneracion_basica' => '1800.00']);

        // Julio–noviembre calculados: 2 faltas en agosto y horas extra en julio, agosto y octubre.
        foreach ([7, 8, 9, 10, 11] as $mes) {
            $p = $this->periodo($mes, $t);
            $p->lineas()->sole()->update([
                'dias_no_laborados' => $mes === 8 ? 2 : 0,
                'dias_laborados' => $mes === 8 ? 28 : 30,
                'horas_extra_25' => in_array($mes, [7, 8, 10], true) ? '8' : '0',
            ]);
            $this->calcular($p);
        }

        $diciembre = $this->periodo(12, $t);
        $this->calcular($diciembre);

        // Valor hora 1800/240 = 7.50; 8 h × 7.50 × 1.25 = 75.00 por mes → 3 meses = 225 / 6 = 37.50
        // Computable 1837.50 → 1837.50 − 1837.50/180 × 2 = 1817.08
        $c = $this->conceptos($diciembre->lineas()->sole());
        $this->assertSame('1817.08', $c['0406']);
        $this->assertSame('163.54', $c['0312']);
    }

    #[Test]
    public function pequena_empresa_recibe_la_mitad_y_microempresa_nada(): void
    {
        $this->empresa->update(['regimen_laboral' => RegimenLaboral::PequenaEmpresa]);
        $diciembre = $this->periodo(12, $this->trabajador());
        $this->calcular($diciembre);
        $this->assertSame('750.00', $this->conceptos($diciembre->lineas()->sole())['0406']);

        $micro = Empresa::factory()->create(['regimen_laboral' => RegimenLaboral::Microempresa, 'seguro_salud' => 'sis']);
        $this->asistente->empresas()->attach($micro);
        $t = Trabajador::factory()->for($micro)->create(['fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null, 'seguro_salud' => 'sis']);
        $p = Periodo::factory()->for($micro)->create(['anio' => 2026, 'mes' => 12]);
        $p->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->calcular($p);
        $this->assertArrayNotHasKey('0406', $this->conceptos($p->lineas()->sole()));
    }

    #[Test]
    public function quien_cesa_antes_del_dia_de_pago_no_la_recibe_en_la_planilla(): void
    {
        $cesado = $this->trabajador(['fecha_cese' => '2026-12-10', 'motivo_cese' => '01']);
        $diciembre = $this->periodo(12, $cesado);

        $this->calcular($diciembre);

        $this->assertArrayNotHasKey('0406', $this->conceptos($diciembre->lineas()->sole()));
    }

    #[Test]
    public function sin_el_parametro_de_bonificacion_no_calcula_diciembre(): void
    {
        Parametro::query()->where('clave', ClaveParametro::TasaBonificacionExtraordinaria->value)->delete();
        $diciembre = $this->periodo(12, $this->trabajador());

        $this->actingAs($this->asistente)->post(route('periodos.calcular', $diciembre));

        $this->assertSame('abierto', $diciembre->refresh()->estado->value);
        $this->assertStringContainsString('Bonificación extraordinaria', implode(' ', session('problemas_calculo')));
    }

    #[Test]
    public function el_periodo_muestra_el_detalle_de_la_gratificacion(): void
    {
        $this->trabajador(['apellido_paterno' => 'Alfa', 'fecha_ingreso' => '2026-09-10']);
        $diciembre = $this->periodo(12, ...Trabajador::all());

        $this->actingAs($this->asistente)->get(route('periodos.show', $diciembre))
            ->assertInertia(fn (Assert $page) => $page
                ->where('gratificacion.error', null)
                ->where('gratificacion.filas.0.meses', 3)
                ->where('gratificacion.filas.0.gratificacion', '750.00')
                ->where('gratificacion.filas.0.bonificacion', '67.50')
                ->where('gratificacion.filas.0.sin_planilla', ['Octubre', 'Noviembre']),
            );

        $noviembre = $this->periodo(11);
        $this->get(route('periodos.show', $noviembre))->assertInertia(fn (Assert $page) => $page->where('gratificacion', null));
    }
}
