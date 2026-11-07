<?php

namespace Tests\Feature\Trabajadores;

use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Autocompletar nombres por DNI (RENIEC vía Decolecta), simulado con Http::fake.
 * DNI y nombres ficticios (Ley 29733). Nunca sale a internet.
 */
class ConsultaDniTest extends TestCase
{
    use RefreshDatabase;

    private User $asistente;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();
        config(['services.decolecta.token' => 'token-de-prueba']);

        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function respuestaReniec(): array
    {
        return [
            'first_name' => 'ROSA ELENA',
            'first_last_name' => 'QUISPE',
            'second_last_name' => 'MAMANI',
            'full_name' => 'QUISPE MAMANI ROSA ELENA',
            'document_number' => '70000001',
        ];
    }

    #[Test]
    public function completa_apellidos_y_nombres_desde_reniec(): void
    {
        Http::fake(['api.decolecta.com/v1/reniec/dni*' => Http::response($this->respuestaReniec())]);

        $this->actingAs($this->asistente)
            ->getJson(route('trabajadores.consulta-dni', '70000001'))
            ->assertOk()
            ->assertExactJson([
                'dni' => '70000001',
                'apellido_paterno' => 'QUISPE',
                'apellido_materno' => 'MAMANI',
                'nombres' => 'ROSA ELENA',
            ]);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token-de-prueba')
            && $r['numero'] === '70000001');
    }

    #[Test]
    public function se_recuerda_un_rato_para_no_gastar_consultas(): void
    {
        Http::fake(['api.decolecta.com/*' => Http::response($this->respuestaReniec())]);
        $this->actingAs($this->asistente);

        $this->getJson(route('trabajadores.consulta-dni', '70000001'))->assertOk();
        $this->getJson(route('trabajadores.consulta-dni', '70000001'))->assertOk();

        Http::assertSentCount(1);
    }

    #[Test]
    public function dni_invalido_no_consume_consultas(): void
    {
        $this->actingAs($this->asistente)
            ->getJson(route('trabajadores.consulta-dni', '1234'))
            ->assertStatus(422)
            ->assertJson(['message' => 'El DNI debe tener 8 dígitos.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function dni_ya_registrado_en_la_empresa_no_consume_consultas(): void
    {
        Trabajador::factory()->for($this->empresa)->create(['numero_documento' => '70000001']);

        $this->actingAs($this->asistente)
            ->getJson(route('trabajadores.consulta-dni', '70000001'))
            ->assertStatus(409)
            ->assertJson(['message' => 'Ya existe un trabajador con ese DNI en esta empresa.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function dni_no_encontrado(): void
    {
        Http::fake(['api.decolecta.com/*' => Http::response(['error' => 'Invalid request'], 400)]);

        $this->actingAs($this->asistente)
            ->getJson(route('trabajadores.consulta-dni', '70000009'))
            ->assertNotFound()
            ->assertJson(['message' => 'RENIEC no devolvió datos para ese DNI. Revisa el número o completa a mano.']);
    }

    #[Test]
    public function proveedor_caido_o_sin_cupo_permite_seguir_a_mano(): void
    {
        Http::fake(['api.decolecta.com/*' => Http::sequence()->push(null, 500)->push(['message' => 'limit'], 429)]);
        $this->actingAs($this->asistente);

        $this->getJson(route('trabajadores.consulta-dni', '70000001'))
            ->assertStatus(503)
            ->assertJson(['message' => 'La consulta a RENIEC no está disponible ahora. Completa los datos a mano.']);

        $this->getJson(route('trabajadores.consulta-dni', '70000002'))
            ->assertStatus(503)
            ->assertJson(['message' => 'Se agotaron las consultas de DNI del mes. Completa los datos a mano.']);
    }

    #[Test]
    public function sin_token_no_consulta(): void
    {
        config(['services.decolecta.token' => null]);

        $this->actingAs($this->asistente)
            ->getJson(route('trabajadores.consulta-dni', '70000001'))
            ->assertStatus(503)
            ->assertJson(['message' => 'La consulta de DNI no está configurada. Completa los datos a mano.']);

        Http::assertNothingSent();
    }

    #[Test]
    public function la_ficha_indica_si_la_consulta_esta_disponible(): void
    {
        $this->actingAs($this->asistente)
            ->get(route('trabajadores.create'))
            ->assertInertia(fn (Assert $page) => $page->where('catalogos.consulta_dni', true));

        config(['services.decolecta.token' => null]);

        $this->get(route('trabajadores.create'))
            ->assertInertia(fn (Assert $page) => $page->where('catalogos.consulta_dni', false));
    }

    #[Test]
    public function requiere_empresa_activa_y_sesion(): void
    {
        $this->getJson(route('trabajadores.consulta-dni', '70000001'))->assertUnauthorized();

        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('trabajadores.consulta-dni', '70000001'))
            ->assertRedirect(route('dashboard'));

        Http::assertNothingSent();
    }
}
