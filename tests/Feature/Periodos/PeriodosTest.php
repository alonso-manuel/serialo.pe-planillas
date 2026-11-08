<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Enums\TipoConcepto;
use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Periodos de planilla de la empresa activa: abrir mes, líneas, sincronizar y eliminar. Datos ficticios. */
class PeriodosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function trabajador(string $apellido, string $ingreso, ?string $cese = null, ?Empresa $empresa = null): Trabajador
    {
        return Trabajador::factory()->for($empresa ?? $this->empresa)->create([
            'apellido_paterno' => $apellido, 'fecha_ingreso' => $ingreso, 'fecha_cese' => $cese,
        ]);
    }

    private function abrir(int $anio, int $mes)
    {
        return $this->actingAs($this->asistente)->post(route('periodos.store'), ['anio' => $anio, 'mes' => $mes]);
    }

    #[Test]
    public function abrir_un_mes_crea_lineas_solo_de_quienes_trabajaron_en_el(): void
    {
        $completo = $this->trabajador('Completo', '2025-03-01');
        $ingresa = $this->trabajador('Ingresa', '2026-11-16');
        $cesa = $this->trabajador('Cesa', '2025-03-01', '2026-11-10');
        $futuro = $this->trabajador('CeseFuturo', '2025-03-01', '2027-01-31');
        $this->trabajador('Posterior', '2026-12-01');
        $this->trabajador('Cesado', '2025-03-01', '2026-10-31');
        $this->trabajador('OtraEmpresa', '2025-03-01', null, Empresa::factory()->create());

        $this->abrir(2026, 11)->assertSessionHasNoErrors();

        $periodo = Periodo::sole();
        $this->assertSame($this->empresa->id, $periodo->empresa_id);
        $this->assertSame(EstadoPeriodo::Abierto, $periodo->estado);
        $this->assertSame(
            [$completo->id => 30, $ingresa->id => 15, $cesa->id => 10, $futuro->id => 30],
            $periodo->lineas()->orderBy('trabajador_id')->pluck('dias_laborados', 'trabajador_id')->all(),
        );
        $this->assertTrue(Auditoria::where('auditable_type', 'periodo')->where('accion', 'creado')->exists());
    }

    #[Test]
    public function tras_abrir_va_al_periodo(): void
    {
        $this->trabajador('Uno', '2025-03-01');

        $this->abrir(2026, 11)->assertRedirect(route('periodos.show', Periodo::sole()));

        $this->get(route('periodos.show', Periodo::sole()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('periodos/Show')
                ->where('periodo.etiqueta', 'Noviembre 2026')
                ->where('periodo.estado', 'abierto')
                ->where('periodo.puede_editar', true)
                ->has('lineas', 1)
                ->where('lineas.0.dias_laborados', 30)
            );
    }

    #[Test]
    public function el_primer_periodo_es_libre_y_luego_solo_el_mes_siguiente(): void
    {
        $this->abrir(2026, 6)->assertSessionHasNoErrors();

        $this->abrir(2026, 6)->assertSessionHasErrors(['mes' => 'El periodo 06/2026 ya está abierto.']);
        $this->abrir(2026, 8)->assertSessionHasErrors(['mes' => 'Solo puedes abrir 07/2026, el mes siguiente al último periodo.']);
        $this->abrir(2026, 5)->assertSessionHasErrors('mes');
        $this->abrir(2026, 7)->assertSessionHasNoErrors();

        $this->get(route('periodos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('periodos/Index')
                ->has('periodos', 2)
                ->where('periodos.0.etiqueta', 'Julio 2026')
                ->where('siguiente', ['anio' => 2026, 'mes' => 8])
            );
    }

    #[Test]
    public function diciembre_continua_en_enero(): void
    {
        $this->abrir(2026, 12);
        $this->abrir(2027, 1)->assertSessionHasNoErrors();
        $this->assertSame(2, Periodo::count());
    }

    #[Test]
    public function validaciones_del_mes(): void
    {
        $this->actingAs($this->asistente)
            ->post(route('periodos.store'), ['anio' => 2026, 'mes' => 13])
            ->assertSessionHasErrors('mes');
        $this->post(route('periodos.store'), ['anio' => 1990, 'mes' => 1])
            ->assertSessionHasErrors('anio');
    }

    #[Test]
    public function actualizar_trabajadores_agrega_y_quita_sin_tocar_lineas_con_novedades(): void
    {
        $queda = $this->trabajador('Queda', '2025-03-01');
        $sale = $this->trabajador('Sale', '2025-03-01');
        $conNovedad = $this->trabajador('ConNovedad', '2025-03-01');
        $this->abrir(2026, 11);
        $periodo = Periodo::sole();

        $concepto = Concepto::create([
            'codigo_plame' => '0701', 'nombre' => 'Adelanto', 'tipo' => TipoConcepto::DescuentoTrabajador,
            'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
            'sistema' => false, 'activo' => true,
        ]);
        $periodo->lineas()->where('trabajador_id', $conNovedad->id)->sole()
            ->novedades()->create(['concepto_id' => $concepto->id, 'monto' => '100.00']);

        // Después de abrir: llega uno nuevo y dos se dan de baja con fecha anterior al mes.
        $nuevo = $this->trabajador('Nuevo', '2026-11-20');
        $sale->update(['fecha_ingreso' => '2026-12-01']);
        $conNovedad->update(['fecha_ingreso' => '2026-12-01']);

        $this->post(route('periodos.sincronizar', $periodo))->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$queda->id, $conNovedad->id, $nuevo->id],
            $periodo->lineas()->pluck('trabajador_id')->all(),
        );
        $this->assertSame(11, $periodo->lineas()->where('trabajador_id', $nuevo->id)->sole()->dias_laborados);
    }

    #[Test]
    public function un_periodo_cerrado_no_se_actualiza_ni_elimina(): void
    {
        $periodo = Periodo::factory()->for($this->empresa)->cerrado()->create(['anio' => 2026, 'mes' => 10]);
        $this->trabajador('Nuevo', '2025-03-01');

        $this->actingAs($this->asistente);
        $this->post(route('periodos.sincronizar', $periodo))->assertForbidden();
        $this->delete(route('periodos.destroy', $periodo))->assertForbidden();

        $this->assertSame(0, $periodo->lineas()->count());
    }

    #[Test]
    public function solo_se_elimina_el_ultimo_periodo_abierto_y_sin_calcular(): void
    {
        $this->trabajador('Uno', '2025-03-01');
        $this->abrir(2026, 10);
        $this->abrir(2026, 11);
        [$octubre, $noviembre] = Periodo::orderBy('mes')->get()->all();

        $this->delete(route('periodos.destroy', $octubre))->assertRedirect();
        $this->assertModelExists($octubre);

        $noviembre->lineas()->update(['calculado_en' => now()]);
        $this->delete(route('periodos.destroy', $noviembre))->assertRedirect();
        $this->assertModelExists($noviembre);

        $noviembre->lineas()->update(['calculado_en' => null]);
        $this->delete(route('periodos.destroy', $noviembre))->assertRedirect(route('periodos.index'));
        $this->assertModelMissing($noviembre);
        $this->assertSame(0, PlanillaLinea::where('periodo_id', $noviembre->id)->count());
    }

    #[Test]
    public function asistente_no_ve_periodos_de_otra_empresa(): void
    {
        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);

        $this->actingAs($this->asistente);
        $this->get(route('periodos.show', $ajeno))->assertForbidden();
        $this->post(route('periodos.sincronizar', $ajeno))->assertForbidden();
        $this->delete(route('periodos.destroy', $ajeno))->assertForbidden();

        $this->get(route('periodos.index'))->assertInertia(fn (Assert $page) => $page->has('periodos', 0));
    }

    #[Test]
    public function sin_empresa_activa_vuelve_al_panel(): void
    {
        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('periodos.index'))
            ->assertRedirect(route('dashboard'));
    }
}
