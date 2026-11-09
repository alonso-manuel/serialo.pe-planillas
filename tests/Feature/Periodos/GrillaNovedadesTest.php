<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Enums\TipoConcepto;
use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Novedad;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Grilla de novedades del periodo: días, horas extra y montos por concepto. Datos ficticios. */
class GrillaNovedadesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    private PlanillaLinea $linea;

    private Concepto $bono;

    private Concepto $adelanto;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->seed(ConceptoSeeder::class);
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);

        $trabajador = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00',
        ]);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
        $this->linea = $this->periodo->lineas()->create(['trabajador_id' => $trabajador->id, 'dias_laborados' => 30]);

        $this->bono = Concepto::create([
            'codigo_plame' => '0304', 'nombre' => 'Bonificación ficticia', 'tipo' => TipoConcepto::Ingreso,
            'afecto_essalud' => true, 'afecto_onp' => true, 'afecto_afp' => true, 'afecto_quinta' => true,
            'sistema' => false, 'activo' => true,
        ]);
        $this->adelanto = Concepto::where('codigo_plame', '0701')->sole();
    }

    /** @param array<string, mixed> $datos */
    private function guardar(array $datos, ?PlanillaLinea $linea = null)
    {
        return $this->actingAs($this->asistente)->putJson(
            route('periodos.lineas.update', [$this->periodo, $linea ?? $this->linea]),
            ['dias_no_laborados' => 0, 'dias_subsidiados' => 0, 'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [], ...$datos],
        );
    }

    #[Test]
    public function guarda_dias_horas_extra_y_novedades_de_una_fila(): void
    {
        $this->guardar([
            'dias_no_laborados' => 2,
            'dias_subsidiados' => 1,
            'horas_extra_25' => '4',
            'horas_extra_35' => '1.5',
            'novedades' => [$this->bono->id => '250', $this->adelanto->id => '300.50'],
        ])->assertOk()->assertJson([
            'dias_mes' => 30,
            'dias_no_laborados' => 2,
            'dias_subsidiados' => 1,
            'dias_laborados' => 27,
            'horas_extra_25' => '4.00',
            'horas_extra_35' => '1.50',
            'novedades' => [$this->bono->id => '250.00', $this->adelanto->id => '300.50'],
            'estado_periodo' => 'abierto',
        ]);

        $this->linea->refresh();
        $this->assertSame(27, $this->linea->dias_laborados);
        $this->assertSame('1.50', $this->linea->horas_extra_35);
        $this->assertSame(2, $this->linea->novedades()->count());
    }

    #[Test]
    public function editar_y_borrar_novedades_con_cero_o_vacio(): void
    {
        $this->guardar(['novedades' => [$this->bono->id => '250', $this->adelanto->id => '100']]);

        $this->guardar(['novedades' => [$this->bono->id => '300', $this->adelanto->id => '']])->assertOk();
        $this->assertSame('300.00', Novedad::where('concepto_id', $this->bono->id)->sole()->monto);
        $this->assertFalse(Novedad::where('concepto_id', $this->adelanto->id)->exists());

        $this->guardar(['novedades' => [$this->bono->id => '0']])->assertOk();
        $this->assertSame(0, Novedad::count());
    }

    #[Test]
    public function los_dias_del_mes_respetan_ingreso_y_cese(): void
    {
        $nuevo = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2026-11-16']);
        $linea = $this->periodo->lineas()->create(['trabajador_id' => $nuevo->id, 'dias_laborados' => 15]);

        $this->guardar(['dias_no_laborados' => 3], $linea)
            ->assertOk()
            ->assertJson(['dias_mes' => 15, 'dias_laborados' => 12]);

        $this->guardar(['dias_no_laborados' => 10, 'dias_subsidiados' => 6], $linea)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dias_no_laborados' => 'Faltas, días subsidiados y licencias suman 16: no pueden superar los 15 días del mes.']);
    }

    #[Test]
    public function validaciones_de_la_fila(): void
    {
        $sistema = Concepto::where('codigo_plame', '0121')->sole();
        $inactivo = Concepto::create([
            'codigo_plame' => '0107', 'nombre' => 'Inactivo', 'tipo' => TipoConcepto::Ingreso,
            'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
            'sistema' => false, 'activo' => false,
        ]);

        $this->guardar([
            'dias_no_laborados' => -1,
            'horas_extra_25' => '2.555',
            'novedades' => [$this->bono->id => '10.999'],
        ])->assertStatus(422)->assertJsonValidationErrors(['dias_no_laborados', 'horas_extra_25', "novedades.{$this->bono->id}"]);

        $this->guardar(['novedades' => [$sistema->id => '100']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(["novedades.{$sistema->id}" => 'Este concepto no se ingresa como novedad.']);

        $this->guardar(['novedades' => [$inactivo->id => '100']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(["novedades.{$inactivo->id}"]);

        $this->assertSame(0, Novedad::count());
    }

    #[Test]
    public function editar_un_periodo_calculado_lo_devuelve_a_abierto(): void
    {
        $this->periodo->update(['estado' => EstadoPeriodo::Calculado]);

        $this->guardar(['horas_extra_25' => '2'])->assertOk()->assertJson(['estado_periodo' => 'abierto']);

        $this->assertSame(EstadoPeriodo::Abierto, $this->periodo->refresh()->estado);
    }

    #[Test]
    public function un_periodo_cerrado_no_se_edita(): void
    {
        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);

        $this->guardar(['horas_extra_25' => '2'])->assertForbidden();
        $this->assertSame('0.00', $this->linea->refresh()->horas_extra_25);
    }

    #[Test]
    public function una_linea_de_otro_periodo_no_se_edita_desde_esta_url(): void
    {
        $otro = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12]);
        $ajena = $otro->lineas()->create(['trabajador_id' => $this->linea->trabajador_id, 'dias_laborados' => 30]);

        $this->guardar(['horas_extra_25' => '2'], $ajena)->assertNotFound();
    }

    #[Test]
    public function asistente_no_edita_periodos_de_otra_empresa(): void
    {
        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);
        $linea = $ajeno->lineas()->create(['trabajador_id' => Trabajador::factory()->create()->id, 'dias_laborados' => 30]);

        $this->actingAs($this->asistente)
            ->putJson(route('periodos.lineas.update', [$ajeno, $linea]), ['dias_no_laborados' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function queda_en_la_auditoria(): void
    {
        $this->guardar(['dias_no_laborados' => 1, 'novedades' => [$this->bono->id => '100']]);

        $this->assertTrue(Auditoria::where('auditable_type', 'planilla_linea')->where('accion', 'actualizado')->exists());
        $this->assertTrue(Auditoria::where('auditable_type', 'novedad')->where('accion', 'creado')->exists());
    }

    #[Test]
    public function el_detalle_trae_la_grilla_y_los_conceptos_disponibles(): void
    {
        $this->guardar(['novedades' => [$this->bono->id => '100']]);

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lineas.0.dias_mes', 30)
                ->where('lineas.0.novedades', [(string) $this->bono->id => '100.00'])
                ->where('columnas', [$this->bono->id])
                ->where('conceptos', fn ($c) => collect($c)->pluck('codigo_plame')->contains('0701')
                    && ! collect($c)->pluck('codigo_plame')->contains('0121'))
            );
    }

    #[Test]
    public function el_calculo_paga_solo_los_dias_laborados(): void
    {
        $this->seed(ParametroSeeder::class);
        $this->guardar(['dias_no_laborados' => 2]);

        app(CalcularPlanillaPeriodo::class)->ejecutar($this->periodo->refresh());

        $basico = $this->linea->refresh()->conceptos()
            ->whereHas('concepto', fn ($q) => $q->where('codigo_plame', '0121'))->sole();
        $this->assertSame('1400.00', $basico->monto_devengado);
    }
}
