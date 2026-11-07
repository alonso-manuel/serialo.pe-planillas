<?php

namespace Tests\Feature\TasasAfp;

use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\TasaAfp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tasas AFP mensuales (valores ficticios, no son las publicadas por la SBS). */
class GrillaTasasAfpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, Afp> */
    private array $afps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        foreach (['HABITAT' => 'AFP Habitat', 'INTEGRA' => 'AFP Integra', 'PRIMA' => 'Prima AFP', 'PROFUTURO' => 'Profuturo AFP'] as $codigo => $nombre) {
            $this->afps[$codigo] = Afp::create(['codigo' => $codigo, 'nombre' => $nombre, 'activo' => true]);
        }
    }

    private function tasa(string $afp, int $anio, int $mes, string $comision = '0.0150'): TasaAfp
    {
        return TasaAfp::create([
            'afp_id' => $this->afps[$afp]->id, 'anio' => $anio, 'mes' => $mes,
            'aporte_obligatorio' => '0.1000', 'prima_seguro' => '0.0137',
            'comision_flujo' => $comision, 'comision_mixta' => '0.0000',
            'remuneracion_maxima_asegurable' => '12000.00',
        ]);
    }

    /** @return array<string, mixed> */
    private function filas(array $cambios = []): array
    {
        $filas = [];
        foreach ($this->afps as $codigo => $afp) {
            $filas[] = [
                'afp_id' => $afp->id,
                'aporte_obligatorio' => '10',
                'prima_seguro' => '1.37',
                'comision_flujo' => '1.55',
                'comision_mixta' => '0',
                'remuneracion_maxima_asegurable' => '12500.50',
                ...($cambios[$codigo] ?? []),
            ];
        }

        return ['filas' => $filas];
    }

    #[Test]
    public function resumen_del_anio_marca_meses_completos_incompletos_y_vacios(): void
    {
        foreach (array_keys($this->afps) as $codigo) {
            $this->tasa($codigo, 2026, 1);
        }
        $this->tasa('HABITAT', 2026, 2);

        $this->actingAs($this->admin)
            ->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasas-afp/Index')
                ->where('anio', 2026)
                ->where('mes', 1)
                ->has('resumen', 12)
                ->where('resumen.0.estado', 'completo')
                ->where('resumen.1.estado', 'incompleto')
                ->where('resumen.1.cargadas', 1)
                ->where('resumen.2.estado', 'vacio')
                ->has('afps', 4)
            );
    }

    #[Test]
    public function grilla_del_mes_muestra_porcentajes_y_ofrece_el_mes_anterior(): void
    {
        $this->tasa('HABITAT', 2025, 12, '0.0147');
        $this->tasa('HABITAT', 2026, 1, '0.0155');

        $this->actingAs($this->admin)
            ->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('grilla.0.codigo', 'HABITAT')
                ->where('grilla.0.comision_flujo', '1.55')
                ->where('grilla.0.aporte_obligatorio', '10.00')
                ->where('grilla.0.remuneracion_maxima_asegurable', '12000.00')
                ->where('grilla.1.comision_flujo', null)
                ->where('anterior.0.comision_flujo', '1.47')
                ->where('bloqueado', false)
            );
    }

    #[Test]
    public function guarda_la_grilla_convirtiendo_porcentajes_a_fraccion(): void
    {
        $this->tasa('HABITAT', 2026, 3, '0.0100');

        $this->actingAs($this->admin)
            ->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 3]), $this->filas(['PRIMA' => ['comision_flujo' => '1.6']]))
            ->assertRedirect(route('tasas-afp.index', ['anio' => 2026, 'mes' => 3]))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, TasaAfp::where(['anio' => 2026, 'mes' => 3])->count());
        $prima = $this->afps['PRIMA']->tasaDe(2026, 3);
        $this->assertSame('0.1000', $prima->aporte_obligatorio);
        $this->assertSame('0.0137', $prima->prima_seguro);
        $this->assertSame('0.0160', $prima->comision_flujo);
        $this->assertSame('12500.50', $prima->remuneracion_maxima_asegurable);
        $this->assertSame('0.0155', $this->afps['HABITAT']->tasaDe(2026, 3)->comision_flujo, 'Actualiza la existente.');
    }

    #[Test]
    public function valida_rangos_y_decimales(): void
    {
        $this->actingAs($this->admin)
            ->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 3]), $this->filas([
                'HABITAT' => ['aporte_obligatorio' => '25'],
                'INTEGRA' => ['prima_seguro' => '1.375'],
                'PRIMA' => ['comision_flujo' => ''],
                'PROFUTURO' => ['remuneracion_maxima_asegurable' => '0'],
            ]))
            ->assertSessionHasErrors([
                'filas.0.aporte_obligatorio',
                'filas.1.prima_seguro',
                'filas.2.comision_flujo',
                'filas.3.remuneracion_maxima_asegurable',
            ]);

        $this->assertSame(0, TasaAfp::count());
    }

    #[Test]
    public function exige_las_cuatro_afp_y_mes_valido(): void
    {
        $datos = $this->filas();
        array_pop($datos['filas']);

        $this->actingAs($this->admin)
            ->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 3]), $datos)
            ->assertSessionHasErrors('filas');

        $this->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 13]), $this->filas())
            ->assertNotFound();
    }

    #[Test]
    public function mes_con_periodo_cerrado_no_se_modifica(): void
    {
        $this->tasa('HABITAT', 2026, 3, '0.0100');
        Periodo::factory()->for(Empresa::factory())->cerrado()->create(['anio' => 2026, 'mes' => 3]);

        $this->actingAs($this->admin)
            ->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 3]))
            ->assertInertia(fn (Assert $page) => $page->where('bloqueado', true)->where('resumen.2.bloqueado', true));

        $this->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 3]), $this->filas())
            ->assertSessionHasErrors(['filas' => 'Hay periodos cerrados de 03/2026: sus tasas AFP ya no se pueden modificar.']);

        $this->assertSame('0.0100', $this->afps['HABITAT']->tasaDe(2026, 3)->comision_flujo);
    }

    #[Test]
    public function asistente_no_gestiona_tasas_afp(): void
    {
        $this->actingAs(User::factory()->asistente()->create());

        $this->get(route('tasas-afp.index'))->assertForbidden();
        $this->put(route('tasas-afp.update', ['anio' => 2026, 'mes' => 3]), $this->filas())->assertForbidden();
        $this->get(route('tasas-afp.plantilla'))->assertForbidden();

        $this->assertSame(0, TasaAfp::count());
    }
}
