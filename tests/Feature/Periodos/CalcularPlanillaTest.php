<?php

namespace Tests\Feature\Periodos;

use App\Enums\ClaveParametro;
use App\Enums\EstadoPeriodo;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\VerificadorCalculo;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cálculo de la planilla de un periodo, con revisión previa de datos. Datos ficticios. */
class CalcularPlanillaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(array $datos = []): Trabajador
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', ...$datos,
        ]);
        $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);

        return $t;
    }

    private function calcular()
    {
        return $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo));
    }

    #[Test]
    public function calcula_el_periodo_y_lo_deja_calculado(): void
    {
        $this->enPlanilla(['apellido_paterno' => 'Uno']);
        $this->enPlanilla(['apellido_paterno' => 'Dos', 'remuneracion_basica' => '2000.00']);

        $this->calcular()->assertRedirect(route('periodos.show', $this->periodo));

        $this->periodo->refresh();
        $this->assertSame(EstadoPeriodo::Calculado, $this->periodo->estado);
        // ONP 13 %: 1500 → 1305.00 ; 2000 → 1740.00
        $this->assertEqualsCanonicalizing(['1305.00', '1740.00'], $this->periodo->lineas()->pluck('neto_pagar')->all());
        $this->assertSame(0, $this->periodo->lineas()->whereNull('calculado_en')->count());

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.estado', 'calculado')
                ->where('lineas.0.neto_pagar', fn ($n) => in_array($n, ['1305.00', '1740.00'], true))
                ->where('problemas', [])
            );
    }

    #[Test]
    public function informa_todos_los_problemas_y_no_calcula_nada(): void
    {
        $afp = Afp::create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'activo' => true]);
        $this->enPlanilla(['apellido_paterno' => 'Bien']);
        $this->enPlanilla([
            'apellido_paterno' => 'Rojas', 'apellido_materno' => 'Paredes', 'nombres' => 'Milagros',
            'sistema_pension' => SistemaPension::Afp, 'afp_id' => $afp->id,
            'cuspp' => 'ABC123DEF456', 'tipo_comision_afp' => TipoComisionAfp::Flujo,
        ]);
        Parametro::where('clave', ClaveParametro::TasaEssalud)->delete();

        $this->calcular()->assertRedirect(route('periodos.show', $this->periodo));

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page->where('problemas', [
                'Falta el parámetro «Aporte a EsSalud» vigente en 11/2026.',
                'Falta la tasa AFP de AFP Habitat para 11/2026 (trabajadores: Rojas Paredes, Milagros).',
            ]));

        $this->periodo->refresh();
        $this->assertSame(EstadoPeriodo::Abierto, $this->periodo->estado);
        $this->assertSame(0, $this->periodo->lineas()->whereNotNull('calculado_en')->count(), 'Atómico: nadie quedó calculado.');
    }

    #[Test]
    public function un_periodo_sin_trabajadores_no_se_calcula(): void
    {
        $this->assertSame(['No hay trabajadores en este periodo.'], app(VerificadorCalculo::class)->problemas($this->periodo));
    }

    #[Test]
    public function un_trabajador_afp_sin_afp_asignada_se_reporta(): void
    {
        $this->enPlanilla(['apellido_paterno' => 'Sinafp', 'nombres' => 'Ana', 'apellido_materno' => null, 'sistema_pension' => SistemaPension::Afp]);

        $this->assertSame(
            ['Sinafp, Ana: es AFP pero no tiene una AFP asignada en su ficha.'],
            app(VerificadorCalculo::class)->problemas($this->periodo),
        );
    }

    #[Test]
    public function un_periodo_cerrado_no_se_calcula(): void
    {
        $this->enPlanilla();
        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);

        $this->calcular()->assertForbidden();
    }

    #[Test]
    public function asistente_no_calcula_periodos_de_otra_empresa(): void
    {
        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);

        $this->actingAs($this->asistente)->post(route('periodos.calcular', $ajeno))->assertForbidden();
    }
}
