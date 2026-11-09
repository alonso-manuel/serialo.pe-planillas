<?php

namespace Tests\Feature\Beneficios;

use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\VacacionGoce;
use App\Services\Planilla\PeriodosPlanilla;
use App\Services\Sunat\ExportacionPlame;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Vacaciones: saldos, goce y efecto en la planilla. Datos ficticios. */
class VacacionesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Trabajador $trabajador;

    private Periodo $noviembre;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044', 'regimen_laboral' => RegimenLaboral::General]);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => '70000001', 'apellido_paterno' => 'Alfa',
            'fecha_ingreso' => '2024-01-01', 'fecha_cese' => null, 'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);
        $this->noviembre = app(PeriodosPlanilla::class)->abrir($this->empresa, 2026, 11);
    }

    private function registrar(string $inicio, int $dias)
    {
        return $this->actingAs($this->asistente)->post(route('vacaciones.store', $this->trabajador), ['fecha_inicio' => $inicio, 'dias' => $dias]);
    }

    #[Test]
    public function registra_el_goce_y_lo_pasa_a_la_planilla_del_mes(): void
    {
        $this->registrar('2026-11-25', 30)->assertRedirect(route('vacaciones.index'))->assertSessionHasNoErrors();

        $goce = VacacionGoce::query()->sole();
        $this->assertSame('2026-12-24', $goce->fecha_fin->format('Y-m-d'));

        $linea = $this->noviembre->lineas()->sole();
        $this->assertSame(6, $linea->dias_vacaciones);
        $this->assertSame(24, $linea->dias_laborados);

        $this->get(route('vacaciones.index'))->assertInertia(fn (Assert $page) => $page
            ->component('vacaciones/Index')
            ->where('trabajadores.0.saldo', 30)          // 60 de dos años cumplidos − 30
            ->where('trabajadores.0.periodos.2.saldo', 0) // 2024: gozado completo
            ->where('trabajadores.0.goces.0.dias', 30));
    }

    #[Test]
    public function el_calculo_paga_la_remuneracion_vacacional_y_el_plame_declara_el_codigo_23(): void
    {
        $this->registrar('2026-11-25', 30);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->noviembre))->assertRedirect();

        $conceptos = $this->noviembre->lineas()->sole()->conceptos()->with('concepto')->get()
            ->mapWithKeys(fn ($c) => [$c->concepto->codigo_plame => (string) $c->monto_pagado])->all();
        $this->assertSame('1200.00', $conceptos['0121']);   // 24 días
        $this->assertSame('300.00', $conceptos['0118']);    // 6 días de vacaciones
        $this->assertSame('195.00', $conceptos['0607']);    // ONP sobre 1500 (la vacacional está afecta)

        $this->noviembre->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);
        $archivos = app(ExportacionPlame::class)->archivos($this->noviembre->refresh());
        $this->assertSame("01|70000001|23|6|\r\n", $archivos['060120261120000000044.snl']);
        $this->assertSame("01|70000001|192|0|0|0|\r\n", $archivos['060120261120000000044.jor']); // 24 días × 8 h
    }

    #[Test]
    public function al_abrir_el_mes_siguiente_trae_sus_dias_de_vacaciones(): void
    {
        $this->registrar('2026-11-25', 30);

        $diciembre = app(PeriodosPlanilla::class)->abrir($this->empresa, 2026, 12);

        $linea = $diciembre->lineas()->sole();
        $this->assertSame(24, $linea->dias_vacaciones);
        $this->assertSame(6, $linea->dias_laborados);
    }

    #[Test]
    public function valida_saldo_cruces_y_planillas_cerradas(): void
    {
        $this->registrar('2026-11-01', 40)->assertSessionHasNoErrors();                        // saldo 60 → 20
        $this->registrar('2027-01-05', 25)->assertSessionHasErrors(['dias' => 'Solo tiene 20 días de vacaciones pendientes.']);
        $this->registrar('2026-12-01', 5)->assertSessionHasErrors('fecha_inicio');             // se cruza

        $octubre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 10, 'estado' => EstadoPeriodo::Cerrado]);
        $this->registrar('2026-10-20', 5)->assertSessionHasErrors('fecha_inicio');
        $this->assertNotNull($octubre);
    }

    #[Test]
    public function eliminar_el_goce_devuelve_los_dias_a_la_planilla(): void
    {
        $this->registrar('2026-11-25', 30);
        $goce = VacacionGoce::query()->sole();

        $this->delete(route('vacaciones.destroy', $goce))->assertRedirect();

        $this->assertSame(0, VacacionGoce::query()->count());
        $linea = $this->noviembre->lineas()->sole();
        $this->assertSame(0, $linea->dias_vacaciones);
        $this->assertSame(30, $linea->dias_laborados);
    }

    #[Test]
    public function la_grilla_no_deja_superar_los_dias_del_mes_con_vacaciones(): void
    {
        $this->registrar('2026-11-11', 20);
        $linea = $this->noviembre->lineas()->sole();

        $this->putJson(route('periodos.lineas.update', [$this->noviembre, $linea]), [
            'dias_no_laborados' => 11, 'dias_subsidiados' => 0, 'dias_licencia' => 0,
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertJsonValidationErrors('dias_no_laborados');

        $this->putJson(route('periodos.lineas.update', [$this->noviembre, $linea]), [
            'dias_no_laborados' => 2, 'dias_subsidiados' => 0, 'dias_licencia' => 0,
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertOk()->assertJsonPath('dias_laborados', 8)->assertJsonPath('dias_vacaciones', 20);
    }

    #[Test]
    public function en_pequena_empresa_el_derecho_es_de_15_dias(): void
    {
        $this->empresa->update(['regimen_laboral' => RegimenLaboral::PequenaEmpresa]);

        $this->actingAs($this->asistente)->get(route('vacaciones.index'))
            ->assertInertia(fn (Assert $page) => $page->where('trabajadores.0.saldo', 30)  // 2 años × 15
                ->where('trabajadores.0.periodos.1.derecho', 15));
    }

    #[Test]
    public function un_usuario_de_otra_empresa_no_registra_ni_borra_vacaciones(): void
    {
        $this->registrar('2026-11-25', 5);
        $goce = VacacionGoce::query()->sole();
        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($intruso);
        $this->post(route('vacaciones.store', $this->trabajador), ['fecha_inicio' => '2026-12-10', 'dias' => 5])->assertForbidden();
        $this->delete(route('vacaciones.destroy', $goce))->assertForbidden();
        $this->assertSame(1, VacacionGoce::query()->count());
    }
}
