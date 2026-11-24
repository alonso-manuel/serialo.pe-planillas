<?php

namespace Tests\Feature\PanelCentral;

use App\Models\Central\Solicitud;
use App\Models\Central\Superadmin;
use App\Notifications\NuevaSolicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/**
 * Formulario «Pide tu cuenta de prueba» de la web pública: guarda la solicitud en la base central, avisa por correo
 * al superadmin y se atiende en el panel. Datos ficticios.
 */
class SolicitudesTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const SITIO = 'http://impulso.cfd';

    private const PANEL = 'http://admin.impulso.cfd';

    /** @return array<string, string> */
    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Ana Torres',
            'tipo' => 'estudio',
            'organizacion' => 'Estudio Contable Ficticio',
            'celular' => '987 654 321',
            'correo' => 'ana@ficticio.test',
            'empresas' => '25 empresas',
            'mensaje' => 'Llevo planillas del régimen general y MYPE.',
            'acepta' => '1',
            ...$cambios,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->activarModoSaas();
        Notification::fake();
    }

    private function superadmin(): Superadmin
    {
        $s = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();

        return $s;
    }

    #[Test]
    public function la_solicitud_se_guarda_y_avisa_al_superadmin(): void
    {
        $superadmin = $this->superadmin();

        $this->postJson(self::SITIO.'/solicitud', $this->datos())->assertCreated();

        $solicitud = Solicitud::query()->sole();
        $this->assertSame('Estudio Contable Ficticio', $solicitud->organizacion);
        $this->assertSame('51987654321', $solicitud->whatsapp());
        Notification::assertSentTo($superadmin, NuevaSolicitud::class);

        // Sin JavaScript: formulario normal que termina en /gracias.
        $this->post(self::SITIO.'/solicitud', $this->datos(['correo' => 'otra@ficticio.test']))->assertRedirect('/gracias');
        $this->assertSame(2, Solicitud::query()->count());
    }

    #[Test]
    public function valida_los_datos_y_descarta_a_los_robots(): void
    {
        $this->postJson(self::SITIO.'/solicitud', $this->datos(['correo' => 'no-es-correo', 'acepta' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo', 'acepta']);

        // El campo trampa lleno: responde como si se hubiera guardado, pero no guarda ni avisa.
        $this->postJson(self::SITIO.'/solicitud', $this->datos(['sitio_web' => 'http://spam.test']))->assertCreated();

        $this->assertSame(0, Solicitud::query()->count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function el_panel_lista_las_solicitudes_y_las_marca_atendidas(): void
    {
        $this->postJson(self::SITIO.'/solicitud', $this->datos())->assertCreated();
        $solicitud = Solicitud::query()->sole();

        $this->actingAs($this->superadmin(), 'superadmin');
        $this->get(self::PANEL.'/solicitudes')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/Solicitudes')
                ->where('solicitudesSinAtender', 1)
                ->where('solicitudes', function (Collection $lista): bool {
                    $this->assertSame('Estudio contable', $lista[0]['tipo']);
                    $this->assertSame('51987654321', $lista[0]['whatsapp']);

                    return true;
                }));

        $this->patch(self::PANEL."/solicitudes/{$solicitud->id}/atendida")->assertRedirect();
        $this->assertNotNull($solicitud->refresh()->atendida_en);
    }

    #[Test]
    public function solo_existe_en_el_dominio_de_la_web_y_el_panel_pide_ingresar(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');

        $this->postJson($this->url($alfa, '/solicitud'), $this->datos())->assertNotFound();
        $this->nuevaPeticion();
        $this->get(self::PANEL.'/solicitudes')->assertRedirect(self::PANEL.'/ingresar');
        $this->assertSame(0, Solicitud::query()->count());
    }
}
