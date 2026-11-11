<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\CertificadoQuinta;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Certificado anual de rentas y retenciones de quinta categoría. Datos ficticios. */
class CertificadoQuintaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create([
            'numero_documento' => '70000001', 'apellido_paterno' => 'Pérez', 'fecha_ingreso' => '2025-01-01',
            'remuneracion_basica' => '5000.00', 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);
    }

    private function mes(int $mes, bool $cerrar = true): void
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => $mes]);
        $periodo->lineas()->create(['trabajador_id' => $this->trabajador->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();
        if ($cerrar) {
            $periodo->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);
        }
    }

    #[Test]
    public function suma_las_rentas_y_retenciones_de_las_planillas_cerradas_del_anio(): void
    {
        $this->mes(1);
        $this->mes(2);
        $this->mes(3, cerrar: false); // abierta: no entra

        $c = app(CertificadoQuinta::class)->datos($this->trabajador, 2026);

        $this->assertSame('10000.00', $c['remuneraciones']);
        $this->assertSame('10000.00', $c['renta_bruta']);
        $this->assertSame('37450.00', $c['deduccion']);
        $this->assertSame('0.00', $c['impuesto']);
        $this->assertSame('513.00', $c['retenido']); // 256.50 × 2 (proyección del año completo)
        $this->assertSame('-513.00', $c['diferencia']);
        $this->assertSame(1, $c['pendientes']);
        $this->assertSame('01/01/2026', $c['desde']);
    }

    #[Test]
    public function descarga_el_pdf_de_un_trabajador_y_el_zip_de_todos(): void
    {
        $this->mes(1);

        $pdf = $this->actingAs($this->asistente)->get(route('trabajadores.certificado-quinta', [$this->trabajador, 2026]))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $zip = $this->get(route('certificados-quinta.lote', 2026))->assertOk();
        $this->assertSame('application/zip', $zip->headers->get('Content-Type'));

        $this->get(route('certificados-quinta.lote', 2025))->assertRedirect()
            ->assertInertiaFlash('toast.message', 'No hay planillas cerradas de 2025 para emitir certificados.');
    }

    #[Test]
    public function no_emite_certificados_de_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();

        $this->actingAs($this->asistente)->get(route('trabajadores.certificado-quinta', [$ajeno, 2026]))->assertForbidden();
    }
}
