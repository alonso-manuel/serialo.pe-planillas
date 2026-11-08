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
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Detalle del resultado de un trabajador en un periodo. Datos ficticios. */
class DetalleLineaTest extends TestCase
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

    private function enPlanilla(string $paterno, array $datos = []): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'apellido_materno' => null, 'nombres' => 'Prueba',
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null, ...$datos,
        ]);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    private function adelanto(PlanillaLinea $linea, string $monto): void
    {
        $linea->novedades()->create(['concepto_id' => Concepto::query()->where('codigo_plame', '0701')->value('id'), 'monto' => $monto]);
    }

    #[Test]
    public function muestra_los_conceptos_calculados_por_grupo_y_el_neto(): void
    {
        $linea = $this->enPlanilla('Alfa');
        $this->adelanto($linea, '100.00');
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertRedirect();

        $this->get(route('periodos.lineas.show', [$this->periodo, $linea]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('periodos/Linea')
                ->where('linea.calculada', true)
                ->where('linea.desactualizada', false)
                ->where('trabajador.nombre', 'Alfa, Prueba')
                ->where('trabajador.pension', 'ONP')
                ->where('grupos.ingresos.conceptos.0.codigo', '0121')
                ->where('grupos.ingresos.conceptos.0.monto', '1500.00')
                ->where('grupos.ingresos.total', '1500.00')
                ->where('grupos.aportes_trabajador.conceptos.0.codigo', '0607')
                ->where('grupos.aportes_trabajador.total', '195.00')
                ->where('grupos.descuentos.conceptos.0.codigo', '0701')
                ->where('grupos.descuentos.total', '100.00')
                ->where('grupos.aportes_empleador.conceptos.0.codigo', '0804')
                ->where('grupos.aportes_empleador.total', '135.00')
                ->where('linea.neto_pagar', '1205.00')
                ->where('afectas.essalud', '1500.00')
                ->where('afectas.pension', '1500.00')
                ->where('anterior', null)
                ->where('siguiente', null),
            );
    }

    #[Test]
    public function navega_al_anterior_y_siguiente_en_orden_alfabetico(): void
    {
        $c = $this->enPlanilla('Cuarto');
        $a = $this->enPlanilla('Alfa');
        $b = $this->enPlanilla('Beta');

        $this->actingAs($this->asistente)->get(route('periodos.lineas.show', [$this->periodo, $b]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('anterior', $a->id)
                ->where('siguiente', $c->id)
                ->where('posicion', 2)
                ->where('total', 3)
                ->where('linea.calculada', false),
            );
    }

    #[Test]
    public function avisa_si_el_periodo_cambio_despues_del_calculo(): void
    {
        $linea = $this->enPlanilla('Alfa');
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo));
        $this->periodo->refresh()->update(['estado' => 'abierto']);

        $this->get(route('periodos.lineas.show', [$this->periodo, $linea]))
            ->assertInertia(fn (Assert $page) => $page->where('linea.desactualizada', true));
    }

    #[Test]
    public function no_muestra_lineas_de_otra_empresa_ni_de_otro_periodo(): void
    {
        $otra = Empresa::factory()->create();
        $periodoAjeno = Periodo::factory()->for($otra)->create(['anio' => 2026, 'mes' => 11]);
        $ajeno = Trabajador::factory()->for($otra)->create(['fecha_ingreso' => '2025-01-01']);
        $lineaAjena = $periodoAjeno->lineas()->create(['trabajador_id' => $ajeno->id]);

        $this->actingAs($this->asistente)
            ->get(route('periodos.lineas.show', [$periodoAjeno, $lineaAjena]))
            ->assertForbidden();

        // Línea de otro periodo bajo la URL de un periodo propio
        $this->get(route('periodos.lineas.show', [$this->periodo, $lineaAjena]))->assertNotFound();
    }
}
