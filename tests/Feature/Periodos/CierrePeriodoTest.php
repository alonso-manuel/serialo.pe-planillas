<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Models\Auditoria;
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

/** Cerrar y reabrir un periodo: solo admin, auditado, periodo cerrado inmutable. Datos ficticios. */
class CierrePeriodoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $asistente;

    private Periodo $periodo;

    private PlanillaLinea $linea;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 10:30');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create();
        $this->admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
        $t = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);
        $this->linea = $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    private function calculado(): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertRedirect();
        $this->assertSame(EstadoPeriodo::Calculado, $this->periodo->refresh()->estado);
    }

    #[Test]
    public function el_admin_cierra_un_periodo_calculado_y_queda_auditado(): void
    {
        $this->calculado();
        $actualizados = Auditoria::query()->where('auditable_type', 'periodo')->where('accion', 'actualizado')->count();

        $this->actingAs($this->admin)->post(route('periodos.cerrar', $this->periodo))->assertRedirect();

        $this->periodo->refresh();
        $this->assertSame(EstadoPeriodo::Cerrado, $this->periodo->estado);
        $this->assertSame($this->admin->id, $this->periodo->cerrado_por);
        $this->assertSame('2026-11-15 10:30', $this->periodo->cerrado_en?->format('Y-m-d H:i'));

        $fila = Auditoria::query()->where('auditable_type', 'periodo')->where('accion', 'cerrado')->sole();
        $this->assertSame($this->admin->id, $fila->user_id);
        $this->assertSame(['estado' => ['calculado', 'cerrado']], $fila->cambios);
        // No se registra además un «actualizado» por el mismo cambio.
        $this->assertSame($actualizados, Auditoria::query()->where('auditable_type', 'periodo')->where('accion', 'actualizado')->count());

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.puede_editar', false)
                ->where('cierre.puede_cerrar', false)
                ->where('cierre.puede_reabrir', true)
                ->where('cierre.cerrado_por', 'Ana Admin')
                ->where('cierre.cerrado_en', '15/11/2026 10:30'),
            );
    }

    #[Test]
    public function no_se_cierra_un_periodo_sin_calcular(): void
    {
        $this->actingAs($this->admin)->post(route('periodos.cerrar', $this->periodo))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(EstadoPeriodo::Abierto, $this->periodo->refresh()->estado);
        $this->assertSame(0, Auditoria::query()->where('accion', 'cerrado')->count());
    }

    #[Test]
    public function el_asistente_no_puede_cerrar_ni_reabrir(): void
    {
        $this->calculado();

        $this->actingAs($this->asistente)->post(route('periodos.cerrar', $this->periodo))->assertForbidden();

        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);
        $this->actingAs($this->asistente)->post(route('periodos.reabrir', $this->periodo), ['motivo' => 'Corrección de horas extra del mes'])
            ->assertForbidden();

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page->where('cierre.puede_cerrar', false)->where('cierre.puede_reabrir', false));
    }

    #[Test]
    public function un_periodo_cerrado_no_se_edita_ni_se_recalcula(): void
    {
        $this->calculado();
        $this->actingAs($this->admin)->post(route('periodos.cerrar', $this->periodo));

        $this->actingAs($this->asistente);
        $this->post(route('periodos.calcular', $this->periodo))->assertForbidden();
        $this->post(route('periodos.sincronizar', $this->periodo))->assertForbidden();
        $this->delete(route('periodos.destroy', $this->periodo))->assertForbidden();
        $this->putJson(route('periodos.lineas.update', [$this->periodo, $this->linea]), [
            'dias_no_laborados' => 1, 'dias_subsidiados' => 0, 'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertForbidden();

        // El admin tampoco puede editar sin reabrir.
        $this->actingAs($this->admin)->post(route('periodos.calcular', $this->periodo))->assertForbidden();
        $this->assertSame(EstadoPeriodo::Cerrado, $this->periodo->refresh()->estado);
    }

    #[Test]
    public function el_admin_reabre_con_motivo_y_vuelve_a_calculado(): void
    {
        $this->calculado();
        $neto = $this->linea->refresh()->neto_pagar;
        $this->actingAs($this->admin)->post(route('periodos.cerrar', $this->periodo));

        $this->post(route('periodos.reabrir', $this->periodo), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertTrue($this->periodo->refresh()->estaCerrado());

        $this->post(route('periodos.reabrir', $this->periodo), ['motivo' => '  Rectificatoria PLAME: faltó un bono  '])->assertRedirect();

        $this->periodo->refresh();
        $this->assertSame(EstadoPeriodo::Calculado, $this->periodo->estado);
        $this->assertNull($this->periodo->cerrado_por);
        $this->assertNull($this->periodo->cerrado_en);
        $this->assertSame($neto, $this->linea->refresh()->neto_pagar);

        $fila = Auditoria::query()->where('accion', 'reabierto')->sole();
        $this->assertSame([
            'estado' => ['cerrado', 'calculado'],
            'motivo' => [null, 'Rectificatoria PLAME: faltó un bono'],
        ], $fila->cambios);

        // Ya se puede editar (una novedad lo devuelve a «abierto»).
        $this->assertTrue($this->asistente->can('update', $this->periodo));
    }

    #[Test]
    public function avisa_del_orden_entre_meses_sin_bloquear(): void
    {
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 10, 'estado' => EstadoPeriodo::Calculado]);
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12, 'estado' => EstadoPeriodo::Cerrado]);
        Periodo::factory()->for($this->empresa)->create(['anio' => 2027, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $this->actingAs($this->admin)->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cierre.anterior_sin_cerrar', 'Octubre 2026')
                ->where('cierre.posteriores_cerrados', ['Diciembre 2026', 'Enero 2027']),
            );

        $this->calculado();
        $this->actingAs($this->admin)->post(route('periodos.cerrar', $this->periodo));
        $this->assertTrue($this->periodo->refresh()->estaCerrado());
    }

    #[Test]
    public function un_asistente_no_ve_el_cierre_de_otra_empresa(): void
    {
        $otra = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11, 'estado' => EstadoPeriodo::Calculado]);

        $this->actingAs($this->asistente)->post(route('periodos.cerrar', $otra))->assertForbidden();
        $this->assertSame(EstadoPeriodo::Calculado, $otra->refresh()->estado);
    }
}
