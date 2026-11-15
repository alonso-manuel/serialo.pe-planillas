<?php

namespace Tests\Feature\Guia;

use App\Models\User;
use App\Support\GuiaVideos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ayuda → Guía: videos tutoriales y manual. No contiene datos de empresas (es igual para todos), así que no hay
 * aislamiento por empresa que probar; sí que solo se ve con sesión y que no se puede pedir otro archivo del disco.
 */
class GuiaTest extends TestCase
{
    use RefreshDatabase;

    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'guia-test-'.uniqid();
        File::ensureDirectoryExists($this->directorio);
        File::put($this->directorio.DIRECTORY_SEPARATOR.'periodos.mp4', str_repeat('0123456789', 100));
        config(['planillas.guia.directorio' => $this->directorio, 'planillas.guia.x_accel' => false]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);

        parent::tearDown();
    }

    #[Test]
    public function sin_sesion_no_se_ve_nada(): void
    {
        $this->get(route('guia.index'))->assertRedirect(route('login'));
        $this->get(route('guia.video', 'periodos'))->assertRedirect(route('login'));
        $this->get(route('guia.manual'))->assertRedirect(route('login'));
    }

    #[Test]
    public function cualquier_usuario_ve_la_guia_aunque_no_tenga_empresas(): void
    {
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente)->get(route('guia.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('guia/Index')
                ->has('secciones', count(GuiaVideos::SECCIONES))
                ->where('secciones.0.titulo', 'Primeros pasos')
                ->where('secciones.3.titulo', 'Planilla mensual')
                ->where('secciones.3.videos.0.clave', 'periodos')
                ->where('secciones.3.videos.0.disponible', true)
                ->where('secciones.3.videos.0.url', route('guia.video', 'periodos'))
                ->where('secciones.3.videos.1.disponible', false) // asistencia.mp4 no está en el directorio
                ->where('manual', true));
    }

    #[Test]
    public function entrega_el_video_y_permite_adelantarlo(): void
    {
        $this->actingAs(User::factory()->asistente()->create());

        $respuesta = $this->get(route('guia.video', 'periodos'))->assertOk();
        $this->assertSame('video/mp4', $respuesta->headers->get('Content-Type'));

        // Rango (lo que pide el navegador al adelantar el video).
        $this->get(route('guia.video', 'periodos'), ['Range' => 'bytes=10-19'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 10-19/1000');
    }

    #[Test]
    public function con_nginx_solo_valida_la_sesion_y_delega_el_archivo(): void
    {
        config(['planillas.guia.x_accel' => true]);

        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('guia.video', 'periodos'))
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_guia/periodos.mp4')
            ->assertHeader('Content-Type', 'video/mp4');
    }

    #[Test]
    public function solo_entrega_videos_del_catalogo(): void
    {
        File::put($this->directorio.DIRECTORY_SEPARATOR.'otro.mp4', 'x');
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('guia.video', 'otro'))->assertNotFound();          // existe en disco, no en el catálogo
        $this->get(route('guia.video', 'asistencia'))->assertNotFound();    // en el catálogo, sin archivo
        $this->get('/guia/videos/..%2F..%2F.env')->assertNotFound();        // la ruta solo acepta claves
    }

    #[Test]
    public function descarga_el_manual_de_usuario(): void
    {
        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('guia.manual'))
            ->assertOk()
            ->assertDownload('Manual de usuario - Serialo Planillas.pdf');
    }
}
