<?php

namespace Tests\Feature\Empresas;

use App\Enums\EstadoPeriodo;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\FirmaDigital;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\BoletaPago;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConCertificado;
use Tests\TestCase;

/** Firma digital de documentos laborales con el certificado de la empresa. Certificados autofirmados de prueba. */
class FirmaDigitalTest extends TestCase
{
    use ConCertificado;
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->admin = User::factory()->admin()->create();
    }

    private function registrar(?string $p12 = null, string $clave = 'Clave-Cert-2026')
    {
        return $this->actingAs($this->admin)->post(route('empresas.firma.store', $this->empresa), [
            'certificado' => $this->archivoCertificado($p12 ?? $this->certificadoP12()),
            'clave' => $clave,
        ]);
    }

    private function boleta(bool $cerrar = true): array
    {
        $t = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'numero_documento' => '70000101']);
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => (int) now()->year, 'mes' => (int) now()->month]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo))->assertRedirect();
        if ($cerrar) {
            $periodo->update(['estado' => EstadoPeriodo::Cerrado]);
        }

        return [$periodo->refresh(), $linea->refresh()];
    }

    #[Test]
    public function registra_el_certificado_cifrado_y_sin_secretos_en_la_auditoria(): void
    {
        $this->registrar()->assertSessionHasNoErrors();

        $firma = FirmaDigital::query()->sole();
        $this->assertSame('REPRESENTANTE FICTICIO PRUEBA', $firma->titular);
        $this->assertSame('DNI 00000000', $firma->documento);
        $this->assertSame(now()->addDays(365)->toDateString(), $firma->valido_hasta->toDateString());

        // En la base solo hay texto cifrado: ni la clave ni el certificado en claro.
        $fila = (array) DB::table('firmas_digitales')->first();
        $this->assertStringNotContainsString('Clave-Cert-2026', (string) $fila['clave']);
        $this->assertStringNotContainsString(base64_encode($this->certificadoP12()), (string) $fila['certificado']);

        $auditoria = Auditoria::query()->where('auditable_type', 'firma_digital')->sole();
        $this->assertSame('creado', $auditoria->accion);
        $this->assertArrayNotHasKey('clave', $auditoria->cambios ?? []);
        $this->assertArrayNotHasKey('certificado', $auditoria->cambios ?? []);

        $this->get(route('empresas.edit', $this->empresa))->assertInertia(fn (Assert $p) => $p
            ->where('firma.titular', 'REPRESENTANTE FICTICIO PRUEBA')
            ->where('firma.vigente', true)
            ->missing('firma.clave')
            ->missing('firma.certificado'));
    }

    #[Test]
    public function rechaza_clave_incorrecta_archivo_invalido_y_certificado_vencido(): void
    {
        $this->registrar(clave: 'otra-clave')->assertSessionHasErrors(['certificado' => 'No se pudo abrir el certificado: la clave es incorrecta o el archivo no es un .pfx/.p12.']);
        $this->registrar('no es un certificado')->assertSessionHasErrors('certificado');

        $p12 = $this->certificadoP12(dias: 1);
        $this->travel(3)->days();
        $this->registrar($p12)->assertSessionHasErrors('certificado');

        $this->assertSame(0, FirmaDigital::query()->count());
    }

    #[Test]
    public function solo_el_administrador_registra_o_quita_la_firma(): void
    {
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($this->empresa);

        $this->actingAs($asistente)->post(route('empresas.firma.store', $this->empresa), [
            'certificado' => $this->archivoCertificado($this->certificadoP12()), 'clave' => 'Clave-Cert-2026',
        ])->assertForbidden();
        $this->delete(route('empresas.firma.destroy', $this->empresa))->assertForbidden();
        $this->assertSame(0, FirmaDigital::query()->count());
    }

    #[Test]
    public function la_boleta_de_un_periodo_cerrado_sale_firmada_y_detecta_cambios(): void
    {
        $this->registrar()->assertSessionHasNoErrors();
        [$periodo, $linea] = $this->boleta();

        $pdf = (string) $this->get(route('periodos.lineas.boleta', [$periodo, $linea]))->assertOk()->getContent();

        $this->assertTrue($this->firmaValida($pdf));
        $this->assertStringContainsString('/adbe.pkcs7.detached', $pdf);
        $this->assertFalse($this->firmaValida(substr_replace($pdf, 'X', 200, 1)), 'Un byte cambiado invalida la firma');

        // El lote para imprimir también.
        $this->assertTrue($this->firmaValida((string) $this->get(route('periodos.boletas.pdf', $periodo))->getContent()));

        // La pantalla del periodo avisa quién firma.
        $this->get(route('periodos.show', $periodo))
            ->assertInertia(fn (Assert $p) => $p->where('periodo.firma_digital', 'REPRESENTANTE FICTICIO PRUEBA'));
    }

    #[Test]
    public function el_borrador_y_la_empresa_sin_certificado_no_se_firman(): void
    {
        [$periodo, $linea] = $this->boleta(cerrar: false);
        $this->registrar()->assertSessionHasNoErrors();

        $pdf = (string) $this->get(route('periodos.lineas.boleta', [$periodo, $linea]))->getContent();
        $this->assertStringNotContainsString('/ByteRange', $pdf, 'Un borrador no se firma');

        $this->delete(route('empresas.firma.destroy', $this->empresa))->assertRedirect();
        $periodo->update(['estado' => EstadoPeriodo::Cerrado]);
        $pdf = (string) $this->get(route('periodos.lineas.boleta', [$periodo, $linea]))->getContent();
        $this->assertStringNotContainsString('/ByteRange', $pdf);
    }

    #[Test]
    public function con_el_certificado_vencido_los_documentos_salen_sin_firma(): void
    {
        $this->registrar($this->certificadoP12(dias: 2))->assertSessionHasNoErrors();
        [$periodo, $linea] = $this->boleta();

        $this->travel(4)->days();
        $pdf = (string) $this->get(route('periodos.lineas.boleta', [$periodo, $linea]))->getContent();
        $this->assertStringNotContainsString('/ByteRange', $pdf);

        $this->get(route('empresas.edit', $this->empresa))->assertInertia(fn (Assert $p) => $p->where('firma.vigente', false));
    }

    #[Test]
    public function la_boleta_por_correo_va_firmada_o_protegida_con_clave(): void
    {
        [$periodo, $linea] = $this->boleta();
        /** @var PlanillaLinea $linea */
        $sinFirma = app(BoletaPago::class)->pdfProtegido($periodo, $linea);
        $this->assertStringContainsString('/Encrypt', $sinFirma, 'Sin certificado: protegida con el DNI');

        $this->registrar()->assertSessionHasNoErrors();
        $firmada = app(BoletaPago::class)->pdfProtegido($periodo, $linea);
        $this->assertTrue($this->firmaValida($firmada), 'Con certificado: firmada');
    }

    #[Test]
    public function el_certificado_de_trabajo_sale_firmado(): void
    {
        $this->registrar()->assertSessionHasNoErrors();
        $t = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2024-01-01', 'fecha_cese' => '2026-10-31']);

        $pdf = (string) $this->actingAs($this->admin)->get(route('trabajadores.certificado', $t))->assertOk()->getContent();

        $this->assertTrue($this->firmaValida($pdf));
    }
}
