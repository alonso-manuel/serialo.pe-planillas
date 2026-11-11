<?php

namespace Tests\Feature\Reportes;

use App\Models\Afp;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Reportes\ReportesPlanilla;
use Brick\Math\BigDecimal;
use Database\Seeders\AfpSeeder;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\TasaAfpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Reportes de la planilla. Datos ficticios. */
class ReportesPlanillaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class, AfpSeeder::class, TasaAfpSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);

        $onp = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => 'Alfa', 'fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);
        $afp = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => 'Beta', 'fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '2000.00',
            'asignacion_familiar' => true, 'sistema_pension' => 'afp', 'tipo_comision_afp' => 'flujo',
            'afp_id' => Afp::query()->where('codigo', 'INTEGRA')->value('id'), 'cuspp' => 'DEMO00000001',
        ]);
        $linea = $this->periodo->lineas()->create(['trabajador_id' => $onp->id, 'dias_laborados' => 30]);
        $linea->novedades()->create(['concepto_id' => Concepto::query()->where('codigo_plame', '0701')->value('id'), 'monto' => '100.00']);
        $this->periodo->lineas()->create(['trabajador_id' => $afp->id, 'dias_laborados' => 30]);

        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertRedirect();
    }

    private function reporte(string $tipo): array
    {
        return app(ReportesPlanilla::class)->generar($this->periodo->refresh(), $tipo);
    }

    /** @return array<string, array<string, mixed>> filas por la columna indicada */
    private function filasPor(array $tabla, string $clave): array
    {
        return collect($tabla['filas'])->keyBy($clave)->all();
    }

    #[Test]
    public function planilla_con_una_columna_por_concepto_y_totales(): void
    {
        $tabla = $this->reporte('planilla')['tablas'][0];

        $claves = array_column($tabla['columnas'], 'clave');
        $this->assertContains('c0121', $claves);
        $this->assertContains('c0607', $claves);
        $this->assertContains('c0804', $claves);
        $this->assertSame('1500.00', $tabla['filas'][0]['c0121']);   // Alfa
        $this->assertSame('3500.00', $tabla['totales']['c0121']);
        $this->assertSame('2 trabajadores', $tabla['totales']['trabajador']);
    }

    #[Test]
    public function resumen_con_lo_que_se_paga_a_cada_entidad(): void
    {
        $pagos = $this->filasPor($this->reporte('resumen')['tablas'][0], 'entidad');

        $this->assertSame('195.00', $pagos['ONP']['monto']);             // 13 % de 1500
        $this->assertArrayHasKey('AFP Integra', $pagos);
        $this->assertSame('325.17', $pagos['EsSalud']['monto']);         // 9 % de 1500 + 9 % de 2113
    }

    #[Test]
    public function la_centralizacion_cuadra_debe_y_haber(): void
    {
        $reporte = $this->reporte('centralizacion');
        $tabla = $reporte['tablas'][0];
        $cuentas = $this->filasPor($tabla, 'cuenta');

        $this->assertSame($tabla['totales']['debe'], $tabla['totales']['haber']);
        $this->assertSame('3500.00', $cuentas['6211']['debe']);         // básicos
        $this->assertSame('113.00', $cuentas['6221']['debe']);          // asignación familiar
        $this->assertSame('195.00', $cuentas['4032']['haber']);         // ONP
        $this->assertSame('100.00', $cuentas['1411']['haber']);         // adelanto
        $this->assertSame('325.17', $cuentas['4031']['haber']);         // EsSalud por pagar
        $this->assertArrayHasKey('4111', $cuentas);                     // neto
        $this->assertSame([], $reporte['avisos']);
    }

    #[Test]
    public function avisa_los_conceptos_sin_cuenta_contable(): void
    {
        Concepto::query()->where('codigo_plame', '0701')->update(['cuenta_abono' => null]);

        $reporte = $this->reporte('centralizacion');

        $this->assertStringContainsString('0701 Adelanto', $reporte['avisos'][0]);
        $this->assertArrayHasKey('—', $this->filasPor($reporte['tablas'][0], 'cuenta'));
    }

    #[Test]
    public function costo_laboral_con_provisiones(): void
    {
        $filas = $this->filasPor($this->reporte('costo')['tablas'][0], 'trabajador');
        $alfa = collect($filas)->first(fn ($f, $k) => str_starts_with($k, 'Alfa'));

        // Bruto 1500 + EsSalud 135 + provisiones (gratificación 250 + bonif. 22.50, CTS 145.83, vacaciones 125)
        $this->assertSame('272.50', $alfa['gratificacion']);
        $this->assertSame('145.83', $alfa['cts']);
        $this->assertSame('125.00', $alfa['vacaciones']);
        $this->assertSame((string) BigDecimal::of('1500')->plus('135')->plus('272.50')->plus('145.83')->plus('125')->toScale(2), $alfa['costo']);
    }

    #[Test]
    public function descarga_en_excel_y_pdf(): void
    {
        // Todos los reportes del sistema, también los que se agreguen después.
        foreach (array_keys(ReportesPlanilla::TIPOS) as $tipo) {
            $xlsx = $this->get(route('reportes.descargar', [$this->periodo, $tipo, 'xlsx']))->assertOk();
            $this->assertStringStartsWith('PK', (string) file_get_contents($xlsx->baseResponse->getFile()->getPathname()));

            $pdf = $this->get(route('reportes.descargar', [$this->periodo, $tipo, 'pdf']))->assertOk();
            $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        }
    }

    #[Test]
    public function la_pantalla_lista_los_periodos_calculados(): void
    {
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12]); // sin calcular

        $this->get(route('reportes.index'))->assertInertia(fn (Assert $page) => $page
            ->component('reportes/Index')
            ->has('periodos', 1)
            ->where('periodos.0.etiqueta', 'Noviembre 2026')
            ->has('tipos', 7)); // + costo por centro, asiento por centro y comparativo
    }

    #[Test]
    public function sin_calcular_avisa_y_otra_empresa_no_descarga(): void
    {
        $diciembre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12]);
        $this->get(route('reportes.descargar', [$diciembre, 'resumen', 'pdf']))
            ->assertRedirect(route('reportes.index'))->assertInertiaFlash('toast.type', 'error');

        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());
        $this->actingAs($intruso)->get(route('reportes.descargar', [$this->periodo, 'planilla', 'xlsx']))->assertForbidden();
    }
}
