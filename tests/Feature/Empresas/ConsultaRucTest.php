<?php

namespace Tests\Feature\Empresas;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Consulta de RUC con proveedores externos simulados (Http::fake): nunca sale a internet.
 * Datos de respuesta ficticios.
 */
class ConsultaRucTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function usarDriver(string $driver, ?string $token = null): void
    {
        config(['services.ruc.driver' => $driver, 'services.ruc.decolecta_token' => $token]);
    }

    #[Test]
    public function openruc_completa_los_datos_de_una_persona_juridica(): void
    {
        $this->usarDriver('openruc');
        Http::fake(['openruc.com/api/ruc/20000000001' => Http::response([
            'ruc' => '20000000001',
            'razon_social' => 'COMERCIAL FICTICIA S.A.C.',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
            'direccion' => 'AV. FICTICIA NRO 123',
            'ubigeo' => '150101',
            'source' => 'SUNAT',
            'as_of' => '2026-11-01',
        ])]);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertOk()
            ->assertExactJson([
                'ruc' => '20000000001',
                'razon_social' => 'COMERCIAL FICTICIA S.A.C.',
                'direccion' => 'AV. FICTICIA NRO 123',
                'ubigeo' => '150101',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'alerta' => null,
            ]);
    }

    #[Test]
    public function decolecta_usa_el_token_y_avisa_si_no_esta_activo_o_habido(): void
    {
        $this->usarDriver('decolecta', 'token-de-prueba');
        Http::fake(['api.decolecta.com/v1/sunat/ruc*' => Http::response([
            'razon_social' => 'PERSONA FICTICIA',
            'numero_documento' => '10000000006',
            'estado' => 'BAJA DE OFICIO',
            'condicion' => 'NO HALLADO',
            'direccion' => '',
            'ubigeo' => '-',
            'distrito' => '',
            'provincia' => '',
            'departamento' => '',
        ])]);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '10000000006'))
            ->assertOk()
            ->assertJson([
                'razon_social' => 'PERSONA FICTICIA',
                'direccion' => null,
                'ubigeo' => null,
                'alerta' => 'SUNAT reporta este RUC como BAJA DE OFICIO / NO HALLADO. Verifica antes de registrar la planilla.',
            ]);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token-de-prueba')
            && $r['numero'] === '10000000006');
    }

    #[Test]
    public function openruc_no_cubre_personas_naturales(): void
    {
        $this->usarDriver('openruc');

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '10000000006'))
            ->assertStatus(503)
            ->assertJson(['message' => 'La consulta gratuita solo cubre personas jurídicas (RUC 20). Completa los datos a mano.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function ruc_no_encontrado(): void
    {
        $this->usarDriver('openruc');
        Http::fake(['openruc.com/*' => Http::response(null, 404)]);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertNotFound()
            ->assertJson(['message' => 'SUNAT no tiene registrado ese RUC.']);
    }

    #[Test]
    public function proveedor_caido_permite_seguir_a_mano(): void
    {
        $this->usarDriver('openruc');
        Http::fake(['openruc.com/*' => Http::response(null, 500)]);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertStatus(503)
            ->assertJson(['message' => 'La consulta a SUNAT no está disponible ahora. Completa los datos a mano.']);
    }

    #[Test]
    public function sin_proveedor_configurado(): void
    {
        $this->usarDriver('ninguno');

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    #[Test]
    public function decolecta_sin_token_no_consulta(): void
    {
        $this->usarDriver('decolecta', null);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    #[Test]
    public function ruc_invalido_no_llega_al_proveedor(): void
    {
        $this->usarDriver('openruc');

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000002'))
            ->assertStatus(422)
            ->assertJson(['message' => 'El RUC no es válido. Revisa los 11 dígitos.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function ruc_ya_registrado_avisa_sin_consultar(): void
    {
        $this->usarDriver('openruc');
        Empresa::factory()->create(['ruc' => '20000000001']);

        $this->actingAs($this->admin)
            ->getJson(route('empresas.consulta-ruc', '20000000001'))
            ->assertStatus(409)
            ->assertJson(['message' => 'Esta empresa ya está registrada en el sistema.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function el_resultado_se_guarda_en_cache(): void
    {
        $this->usarDriver('openruc');
        Http::fake(['openruc.com/*' => Http::response([
            'ruc' => '20000000001', 'razon_social' => 'FICTICIA S.A.C.', 'estado' => 'ACTIVO',
            'condicion' => 'HABIDO', 'direccion' => 'AV. X', 'ubigeo' => '150101',
        ])]);

        $this->actingAs($this->admin);
        $this->getJson(route('empresas.consulta-ruc', '20000000001'))->assertOk();
        $this->getJson(route('empresas.consulta-ruc', '20000000001'))->assertOk();

        Http::assertSentCount(1);
    }
}
