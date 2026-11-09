<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\BoletaPago;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Boletas de pago en PDF (una, lote y zip). Datos ficticios. */
class BoletaPagoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $paterno, string $nombres, string $dni): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'apellido_materno' => 'Prueba', 'nombres' => $nombres,
            'numero_documento' => $dni, 'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00',
            'sistema_pension' => 'onp', 'afp_id' => null,
        ]);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    private function calcular(): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertRedirect();
    }

    #[Test]
    public function genera_la_boleta_de_un_trabajador_en_pdf(): void
    {
        $linea = $this->enPlanilla('Núñez', 'Ana', '70000101');
        $this->calcular();

        $respuesta = $this->get(route('periodos.lineas.boleta', [$this->periodo, $linea]))->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());
        $this->assertStringContainsString('2026-11 70000101 Nunez Prueba Ana.pdf', (string) $respuesta->headers->get('Content-Disposition'));
    }

    #[Test]
    public function el_zip_trae_un_pdf_por_trabajador_calculado(): void
    {
        $this->enPlanilla('Beta', 'Bruno', '70000102');
        $this->enPlanilla('Alfa', 'Ana', '70000101');
        $this->calcular();

        $respuesta = $this->get(route('periodos.boletas.zip', $this->periodo))->assertOk();
        $this->assertSame('application/zip', $respuesta->headers->get('Content-Type'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        $nombres = [$zip->getNameIndex(0), $zip->getNameIndex(1)];
        $zip->close();

        $this->assertSame(['2026-11 70000101 Alfa Prueba Ana.pdf', '2026-11 70000102 Beta Prueba Bruno.pdf'], $nombres);
    }

    #[Test]
    public function el_pdf_de_lote_junta_todas_las_boletas(): void
    {
        $this->enPlanilla('Alfa', 'Ana', '70000101');
        $this->enPlanilla('Beta', 'Bruno', '70000102');
        $this->calcular();

        $respuesta = $this->get(route('periodos.boletas.pdf', $this->periodo))->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('Boletas 2026-11 20000000044.pdf', (string) $respuesta->headers->get('Content-Disposition'));
    }

    #[Test]
    public function sin_calcular_no_genera_boletas(): void
    {
        $linea = $this->enPlanilla('Alfa', 'Ana', '70000101');

        $this->actingAs($this->asistente)->get(route('periodos.boletas.zip', $this->periodo))
            ->assertRedirect(route('periodos.show', $this->periodo))
            ->assertInertiaFlash('toast.type', 'error');

        $this->get(route('periodos.lineas.boleta', [$this->periodo, $linea]))
            ->assertRedirect(route('periodos.lineas.show', [$this->periodo, $linea]));
    }

    #[Test]
    public function lleva_marca_de_borrador_hasta_que_se_cierra_el_periodo(): void
    {
        $this->enPlanilla('Alfa', 'Ana', '70000101');
        $this->calcular();
        $boletas = app(BoletaPago::class);

        $vista = $boletas->vista($this->periodo->refresh(), $boletas->lineas($this->periodo));
        $this->assertTrue($vista['borrador']);
        $this->assertSame('MIL TRESCIENTOS CINCO CON 00/100 SOLES', $vista['boletas'][0]['neto_letras']); // 1500 − ONP 195

        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);
        $this->assertFalse($boletas->vista($this->periodo, $boletas->lineas($this->periodo))['borrador']);
    }

    #[Test]
    public function un_usuario_de_otra_empresa_no_descarga_boletas(): void
    {
        $linea = $this->enPlanilla('Alfa', 'Ana', '70000101');
        $this->calcular();
        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($intruso);
        $this->get(route('periodos.lineas.boleta', [$this->periodo, $linea]))->assertForbidden();
        $this->get(route('periodos.boletas.zip', $this->periodo))->assertForbidden();
        $this->get(route('periodos.boletas.pdf', $this->periodo))->assertForbidden();
    }

    #[Test]
    public function abrir_un_periodo_de_otra_empresa_visible_la_vuelve_activa(): void
    {
        $otra = Empresa::factory()->create(['razon_social' => 'Zeta Demo S.A.C.']);
        $this->asistente->empresas()->attach($otra);
        $periodoOtra = Periodo::factory()->for($otra)->create(['anio' => 2026, 'mes' => 11]);

        $this->actingAs($this->asistente)
            ->withSession(['empresa_id' => $this->empresa->id])
            ->get(route('periodos.show', $periodoOtra))
            ->assertOk()
            ->assertSessionHas('empresa_id', $otra->id);
    }
}
