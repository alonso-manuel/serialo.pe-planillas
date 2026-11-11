<?php

namespace Tests\Feature\Reportes;

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

/** Comparativo con el mes anterior: variación por trabajador, altas y bajas. Datos ficticios. */
class ComparativoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-25 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function trabajador(string $paterno): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);
    }

    /** @param  array<int, array{0: Trabajador, 1: array<string, mixed>}>  $lineas */
    private function mes(int $mes, array $lineas): Periodo
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        foreach ($lineas as [$t, $datos]) {
            $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30, ...$datos]);
        }
        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        return $periodo->refresh();
    }

    #[Test]
    public function compara_cada_trabajador_con_el_mes_anterior_y_marca_altas_y_bajas(): void
    {
        $sigue = $this->trabajador('Alfa');
        $sale = $this->trabajador('Beta');
        $entra = $this->trabajador('Gamma');

        $this->mes(10, [[$sigue, []], [$sale, []]]);
        $noviembre = $this->mes(11, [[$sigue, ['horas_extra_25' => '10']], [$entra, []]]);

        $tabla = app(ReportesPlanilla::class)->generar($noviembre, 'comparativo')['tablas'][0];
        $filas = collect($tabla['filas']);

        // Octubre: 1,500 − ONP 195 = 1,305. Noviembre: + 10 HE al 25 % (78.13) = 1,578.13 − ONP 205.16 = 1,372.97
        $alfa = $filas->first(fn ($f) => str_starts_with((string) $f['trabajador'], 'Alfa'));
        $this->assertSame('', $alfa['estado']);
        $this->assertSame('1305.00', $alfa['neto_anterior']);
        $this->assertSame('1372.97', $alfa['neto_actual']);
        $this->assertSame('67.97', $alfa['variacion']);
        $this->assertSame('5.2 %', $alfa['porcentaje']);

        $this->assertSame('Baja', $filas->first(fn ($f) => str_starts_with((string) $f['trabajador'], 'Beta'))['estado']);
        $this->assertSame('Alta', $filas->first(fn ($f) => str_starts_with((string) $f['trabajador'], 'Gamma'))['estado']);
    }

    #[Test]
    public function sin_mes_anterior_todos_son_altas(): void
    {
        $noviembre = $this->mes(11, [[$this->trabajador('Alfa'), []]]);

        $reporte = app(ReportesPlanilla::class)->generar($noviembre, 'comparativo');

        $this->assertSame('Alta', $reporte['tablas'][0]['filas'][0]['estado']);
        $this->assertSame(['No hay planilla de Octubre 2026: todos figuran como altas.'], $reporte['avisos']);
    }
}
