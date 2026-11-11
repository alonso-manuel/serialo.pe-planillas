<?php

namespace Tests\Feature\Periodos;

use App\Models\Empresa;
use App\Models\EntidadFinanciera;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\PagosPlanilla;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Archivo genérico de pagos de la planilla para el banco. Datos ficticios. */
class PagosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-25 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        EntidadFinanciera::query()->firstOrCreate(['codigo' => 'BCP'], ['nombre' => 'Banco de Crédito del Perú', 'activo' => true]);
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $paterno, ?string $cuenta, ?string $banco): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'numero_documento' => (string) random_int(10000000, 99999999), 'fecha_ingreso' => '2025-01-01',
            'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null, 'cuenta_sueldo' => $cuenta, 'entidad_sueldo' => $banco,
        ]);
        $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    #[Test]
    public function separa_depositos_y_efectivo_y_resume_por_banco(): void
    {
        $this->enPlanilla('Alfa', '191-1234567-0-01', 'BCP');
        $this->enPlanilla('Beta', '191 7654321 0 02', 'BCP');
        $this->enPlanilla('Gamma', null, null);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertSessionHasNoErrors();

        $d = app(PagosPlanilla::class)->datos($this->periodo->refresh());

        $this->assertCount(2, $d['depositos']);
        $this->assertSame(['Banco de Crédito del Perú', '1911234567001'], [$d['depositos'][0][3], $d['depositos'][0][4]]); // cuenta sin guiones ni espacios
        $this->assertSame('1305.00', $d['depositos'][0][5]);
        $this->assertCount(1, $d['efectivo']);
        $this->assertSame(['Banco de Crédito del Perú' => ['cantidad' => 2, 'total' => '2610.00']], $d['bancos']);

        $this->get(route('periodos.pagos', [$this->periodo, 'xlsx']))->assertOk()->assertDownload('pagos-noviembre-2026.xlsx');
        $csv = $this->get(route('periodos.pagos', [$this->periodo, 'csv']))->assertOk();
        $this->assertStringContainsString('tipo_documento;numero_documento;trabajador;banco;cuenta;neto', (string) file_get_contents($csv->baseResponse->getFile()->getPathname()));
    }

    #[Test]
    public function sin_calcular_avisa_y_no_ve_pagos_de_otra_empresa(): void
    {
        $this->enPlanilla('Alfa', '191', 'BCP');
        $this->actingAs($this->asistente)->get(route('periodos.pagos', [$this->periodo, 'xlsx']))
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Calcula la planilla antes de generar los pagos.');

        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);
        $this->get(route('periodos.pagos', [$ajeno, 'xlsx']))->assertForbidden();
    }
}
