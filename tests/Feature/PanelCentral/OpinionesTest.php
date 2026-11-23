<?php

namespace Tests\Feature\PanelCentral;

use App\Models\Central\Opinion;
use App\Models\Central\Superadmin;
use App\Models\PermisoSoporte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/**
 * Comentarios de los usuarios sobre el sistema: la ventana se abre sola UNA vez (tras unos días de uso), el
 * comentario llega a la base central con la cuenta y el usuario, y se lee en el panel. Datos ficticios.
 */
class OpinionesTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 09:00');
        $this->activarModoSaas();
    }

    /** @return array{habilitada: bool, pedir: bool} */
    private function opinion(string $url): array
    {
        $datos = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $p) use (&$datos) {
            $datos = $p->toArray()['props']['opinion'];
        });

        return $datos;
    }

    #[Test]
    public function la_ventana_se_abre_una_sola_vez_y_despues_de_unos_dias_de_uso(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->ingresar($alfa, 'admin@alfa.test');

        // Usuario recién creado: todavía no se le pide (puede escribir desde el menú).
        $this->assertSame(['habilitada' => true, 'pedir' => false], $this->opinion($this->url($alfa, '/dashboard')));

        Carbon::setTestNow('2026-11-27 09:00');
        $this->assertTrue($this->opinion($this->url($alfa, '/dashboard'))['pedir']);

        // Al abrirse se marca: no vuelve a aparecer aunque no responda.
        $this->post($this->url($alfa, '/opinion/mostrada'))->assertRedirect();
        $this->assertFalse($this->opinion($this->url($alfa, '/dashboard'))['pedir']);
        $alfa->run(fn () => $this->assertNotNull(User::query()->sole()->opinion_pedida_en));
    }

    #[Test]
    public function el_comentario_llega_al_panel_con_la_cuenta_y_el_usuario(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->ingresar($alfa, 'admin@alfa.test');

        $this->post($this->url($alfa, '/opinion'), ['comentario' => 'hey'])->assertSessionHasErrors('comentario');
        $this->post($this->url($alfa, '/opinion'), ['comentario' => 'Útil, pero falta construcción civil.', 'calificacion' => 6])
            ->assertSessionHasErrors('calificacion');
        $this->post($this->url($alfa, '/opinion'), ['comentario' => '  Me ahorra tiempo; falta construcción civil.  ', 'calificacion' => 4])
            ->assertSessionHasNoErrors()->assertRedirect();

        $opinion = Opinion::query()->sole();
        $this->assertSame('alfa', $opinion->estudio_id);
        $this->assertSame('Estudio Alfa', $opinion->estudio_nombre);
        $this->assertSame('admin@alfa.test', $opinion->usuario_correo);
        $this->assertSame('admin', $opinion->usuario_rol);
        $this->assertSame(4, $opinion->calificacion);
        $this->assertSame('Me ahorra tiempo; falta construcción civil.', $opinion->comentario);
        // Enviar también cuenta como «ya se le pidió».
        $alfa->run(fn () => $this->assertNotNull(User::query()->sole()->opinion_pedida_en));

        // Panel: lo lista con su cuenta y lo marca como leído.
        $s = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->nuevaPeticion();
        $this->actingAs($s, 'superadmin');
        $this->get(self::PANEL.'/comentarios')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/Opiniones')
                ->where('opinionesSinLeer', 1)
                ->where('promedio', 4)
                ->where('opiniones', function (Collection $lista): bool {
                    $this->assertSame('Administrador', $lista[0]['rol']);
                    $this->assertSame('Estudio Alfa', $lista[0]['cuenta']);
                    $this->assertFalse($lista[0]['leida']);

                    return true;
                }));

        $this->patch(self::PANEL."/comentarios/{$opinion->id}/leida")->assertRedirect();
        $this->assertNotNull($opinion->refresh()->leida_en);
    }

    #[Test]
    public function el_soporte_de_serialo_no_ve_la_ventana_ni_envia_comentarios(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $soporte = $alfa->run(function () {
            $admin = User::query()->sole();
            PermisoSoporte::query()->create(['autorizado_por' => $admin->id, 'vence_en' => now()->addDay()]);

            return User::factory()->admin()->create([
                'name' => 'Soporte Serialo.pe', 'es_soporte' => true, 'created_at' => now()->subMonth(),
            ]);
        });

        $this->actingAs($soporte);
        $this->assertSame(['habilitada' => false, 'pedir' => false], $this->opinion($this->url($alfa, '/dashboard')));
        $this->post($this->url($alfa, '/opinion'), ['comentario' => 'Comentario de prueba'])->assertForbidden();
        $this->post($this->url($alfa, '/opinion/mostrada'))->assertNotFound();
        $this->assertSame(0, Opinion::query()->count());
    }

    #[Test]
    public function un_usuario_de_un_estudio_no_puede_leer_los_comentarios_del_panel(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->ingresar($alfa, 'admin@alfa.test');

        // La ruta del panel no existe en el dominio de un estudio, y sin superadmin el panel pide ingresar.
        $this->get($this->url($alfa, '/comentarios'))->assertNotFound();
        $this->nuevaPeticion();
        $this->get(self::PANEL.'/comentarios')->assertRedirect(self::PANEL.'/ingresar');
    }
}
