<?php

namespace Tests\Feature\Empresas;

use App\Models\CentroCosto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\ReportesPlanilla;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Centros de costo: catálogo, asignación con vigencia, foto en la planilla y reporte de costo. Datos ficticios. */
class CentrosCostoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function centro(string $codigo, string $nombre): CentroCosto
    {
        $this->actingAs($this->admin)->post(route('empresas.centros-costo.store', $this->empresa), ['codigo' => $codigo, 'nombre' => $nombre])->assertSessionHasNoErrors();

        return CentroCosto::query()->where('codigo', mb_strtoupper($codigo))->sole();
    }

    #[Test]
    public function el_administrador_gestiona_el_catalogo_y_uno_usado_se_desactiva(): void
    {
        $ventas = $this->centro('vta', 'Ventas');
        $this->assertSame('VTA', $ventas->codigo);

        $this->post(route('empresas.centros-costo.store', $this->empresa), ['codigo' => 'VTA', 'nombre' => 'Otra'])
            ->assertSessionHasErrors(['codigo' => 'Ya existe un centro de costo con ese código.']);

        Trabajador::factory()->for($this->empresa)->create(['centro_costo_id' => $ventas->id]);
        $this->delete(route('empresas.centros-costo.destroy', [$this->empresa, $ventas]));
        $this->assertFalse($ventas->refresh()->activo, 'Con trabajadores no se borra: se desactiva');

        $libre = $this->centro('ADM', 'Administración');
        $this->delete(route('empresas.centros-costo.destroy', [$this->empresa, $libre]));
        $this->assertNull(CentroCosto::query()->find($libre->id));
    }

    #[Test]
    public function un_asistente_no_gestiona_centros_ni_asigna_uno_de_otra_empresa(): void
    {
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($this->empresa);
        $this->actingAs($asistente)->post(route('empresas.centros-costo.store', $this->empresa), ['codigo' => 'X', 'nombre' => 'X'])->assertForbidden();

        $ajeno = CentroCosto::query()->create(['empresa_id' => Empresa::factory()->create()->id, 'codigo' => 'AJ', 'nombre' => 'Ajeno']);
        $t = Trabajador::factory()->for($this->empresa)->create();
        $this->put(route('trabajadores.update', $t), [
            'tipo_documento' => $t->tipo_documento->value, 'numero_documento' => $t->numero_documento, 'apellido_paterno' => 'X', 'nombres' => 'Y',
            'fecha_ingreso' => '2025-01-01', 'tipo_contrato' => 'indeterminado', 'remuneracion_basica' => '1500.00',
            'sistema_pension' => 'onp', 'seguro_salud' => 'essalud', 'centro_costo_id' => $ajeno->id,
        ])->assertSessionHasErrors('centro_costo_id');
    }

    #[Test]
    public function el_cambio_de_centro_rige_desde_su_mes_y_el_reporte_agrupa_el_costo(): void
    {
        $ventas = $this->centro('VTA', 'Ventas');
        $planta = $this->centro('PLT', 'Planta');
        $a = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '2000.00', 'sistema_pension' => 'onp', 'afp_id' => null, 'centro_costo_id' => $ventas->id]);
        Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null]);

        $octubre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 10]);
        foreach (Trabajador::all() as $t) {
            $octubre->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        }
        $this->post(route('periodos.calcular', $octubre))->assertSessionHasNoErrors();

        // Desde noviembre pasa a Planta; octubre (recalculado) sigue en Ventas.
        $this->post(route('trabajadores.cambios.store', $a), ['grupo' => 'centro_costo', 'vigente_desde' => '2026-11', 'centro_costo_id' => $planta->id])->assertSessionHasNoErrors();
        $this->post(route('periodos.calcular', $octubre))->assertSessionHasNoErrors();
        $this->assertSame($ventas->id, $octubre->lineas()->where('trabajador_id', $a->id)->value('centro_costo_id'));
        $this->assertSame($planta->id, $a->refresh()->centro_costo_id);

        $reporte = app(ReportesPlanilla::class)->generar($octubre->refresh(), 'centros');
        $filas = $reporte['tablas'][0]['filas'];
        $this->assertSame(['VTA · Ventas', 'Sin centro de costo'], array_column($filas, 'centro')); // sin centro, al final
        $this->assertSame('2000.00', $filas[0]['ingresos']);
        $this->assertContains('Hay trabajadores sin centro de costo: asígnalo en su ficha y vuelve a calcular la planilla.', $reporte['avisos']);
    }

    #[Test]
    public function el_asiento_separa_los_cargos_por_centro_de_costo_y_cuadra(): void
    {
        $ventas = $this->centro('VTA', 'Ventas');
        $planta = $this->centro('PLT', 'Planta');
        $datos = ['fecha_ingreso' => '2025-01-01', 'sistema_pension' => 'onp', 'afp_id' => null];
        $a = Trabajador::factory()->for($this->empresa)->create([...$datos, 'remuneracion_basica' => '2000.00', 'centro_costo_id' => $ventas->id]);
        $b = Trabajador::factory()->for($this->empresa)->create([...$datos, 'remuneracion_basica' => '1500.00', 'centro_costo_id' => $planta->id]);
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 10]);
        foreach ([$a, $b] as $t) {
            $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        }
        $this->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        $asiento = app(ReportesPlanilla::class)->generar($periodo->refresh(), 'asiento')['tablas'][0];
        $sueldos = array_values(array_filter($asiento['filas'], fn (array $f) => $f['cuenta'] === '6211'));

        $this->assertSame([['PLT', '1500.00'], ['VTA', '2000.00']], array_map(fn (array $f) => [$f['centro'], $f['debe']], $sueldos));
        $this->assertSame($asiento['totales']['debe'], $asiento['totales']['haber'], 'Debe y haber cuadran');

        // La centralización de siempre no separa por centro.
        $centralizacion = app(ReportesPlanilla::class)->generar($periodo, 'centralizacion')['tablas'][0];
        $this->assertCount(1, array_filter($centralizacion['filas'], fn (array $f) => $f['cuenta'] === '6211'));
        $this->assertArrayNotHasKey('centro', $centralizacion['filas'][0]);
    }
}
