<?php

namespace Tests\Feature\Multitenant;

use App\Http\Middleware\VerificarSesionEstudio;
use App\Models\Central\Estudio;
use App\Models\Empresa;
use App\Models\Trabajador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/**
 * Modo SaaS: cada estudio solo ve lo suyo (base, sesión, caché, archivos y cola). Datos ficticios.
 */
class AislamientoEstudiosTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    /** La transacción del test es la de la base central, aunque al final quede activa la de un estudio. */
    protected array $connectionsToTransact = ['sqlite'];

    private Estudio $alfa;

    private Estudio $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activarModoSaas();
        $this->alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->beta = $this->crearEstudio('beta', 'admin@beta.test');

        $this->alfa->run(function (): void {
            $empresa = Empresa::factory()->create(['razon_social' => 'Alfa Ficticia S.A.C.']);
            Trabajador::factory()->for($empresa)->create(['apellido_paterno' => 'Alfaro']);
        });
        $this->beta->run(fn () => Empresa::factory()->create(['razon_social' => 'Beta Ficticia S.A.C.']));
    }

    #[Test]
    public function cada_estudio_ve_solo_sus_empresas_aunque_los_ids_coincidan(): void
    {
        $this->ingresar($this->alfa, 'admin@alfa.test');
        $this->get($this->url($this->alfa, '/empresas'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('empresas.data', 1)
                ->where('empresas.data.0.razon_social', 'Alfa Ficticia S.A.C.'));

        // Mismo id (1) en las dos bases: cada subdominio resuelve el suyo.
        $this->get($this->url($this->alfa, '/empresas/1/edit'))
            ->assertInertia(fn (Assert $p) => $p->where('empresa.razon_social', 'Alfa Ficticia S.A.C.'));

        $this->ingresar($this->beta, 'admin@beta.test');
        $this->get($this->url($this->beta, '/empresas'))
            ->assertInertia(fn (Assert $p) => $p
                ->has('empresas.data', 1)
                ->where('empresas.data.0.razon_social', 'Beta Ficticia S.A.C.'));
        $this->get($this->url($this->beta, '/empresas/1/edit'))
            ->assertInertia(fn (Assert $p) => $p->where('empresa.razon_social', 'Beta Ficticia S.A.C.'));
    }

    #[Test]
    public function con_sesiones_en_base_de_datos_la_sesion_se_guarda_en_la_del_estudio(): void
    {
        // Regresión: el login de Fortify abre la sesión al crear su controlador, antes de los middlewares de
        // la ruta. Si el estudio no se identificara antes (middleware global), la sesión iría a la base central.
        config(['session.driver' => 'database']);

        $this->ingresar($this->alfa, 'admin@alfa.test');

        $this->assertSame(0, DB::connection('sqlite')->table('sessions')->count());
        $this->alfa->run(fn () => $this->assertSame(1, DB::table('sessions')->whereNotNull('user_id')->count()));
        $this->beta->run(fn () => $this->assertSame(0, DB::table('sessions')->count()));
    }

    #[Test]
    public function el_usuario_de_un_estudio_no_existe_en_otro(): void
    {
        $this->nuevaPeticion();

        $this->post($this->url($this->beta, '/login'), ['email' => 'admin@alfa.test', 'password' => 'Clave-Segura-2026!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function una_sesion_de_otro_estudio_se_cierra_en_vez_de_usarse(): void
    {
        // Sesión marcada como de «alfa» con el usuario id 1, que en «beta» es otra persona.
        $this->nuevaPeticion();
        $this->withSession([
            VerificarSesionEstudio::CLAVE => 'alfa',
            $this->app['auth']->guard('web')->getName() => 1,
        ]);

        $this->get($this->url($this->beta, '/dashboard'))->assertRedirect($this->url($this->beta, '/login'));
        $this->assertGuest();
    }

    #[Test]
    public function subdominios_desconocidos_y_el_dominio_central_responden_404(): void
    {
        $this->get('http://nadie.localhost/login')->assertNotFound();
        $this->get('http://localhost/login')->assertNotFound();
        $this->get($this->url($this->alfa, '/login'))->assertOk();
    }

    #[Test]
    public function cache_y_archivos_son_de_cada_estudio(): void
    {
        $this->alfa->run(function (): void {
            Cache::put('consulta', 'dato de alfa');
            Storage::disk('local')->put('logos/logo.png', 'alfa');

            // La clave queda dentro de la etiqueta del estudio, no en el espacio común.
            $this->assertSame('dato de alfa', Cache::get('consulta'));
            $this->assertNull(Cache::store()->get('consulta'));
            $this->assertStringContainsString('estudio-alfa', Storage::disk('local')->path('logos/logo.png'));
        });

        $this->beta->run(function (): void {
            $this->assertNull(Cache::get('consulta'));
            $this->assertFalse(Storage::disk('local')->exists('logos/logo.png'));
        });

        $this->alfa->run(fn () => $this->assertTrue(Storage::disk('local')->exists('logos/logo.png')));
    }

    #[Test]
    public function un_trabajo_en_cola_se_procesa_en_la_base_de_su_estudio(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.connection' => config('database.default')]);
        TrabajoDePrueba::$vistos = [];

        // Sentencia, no flecha: el despacho ocurre al destruir PendingDispatch, dentro del estudio.
        $this->alfa->run(function (): void {
            TrabajoDePrueba::dispatch();
        });
        $this->beta->run(function (): void {
            TrabajoDePrueba::dispatch();
        });

        // Los trabajos quedan en la base central, marcados con su estudio; un solo worker los atiende a todos.
        $cola = DB::connection('sqlite')->table('jobs')->pluck('payload')
            ->map(fn (string $p) => json_decode($p, true)['tenant_id'] ?? null)->all();
        $this->assertSame(['alfa', 'beta'], $cola);

        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame([['alfa', 'Alfaro'], ['beta', null]], TrabajoDePrueba::$vistos);
    }
}

/** Anota en qué estudio se ejecutó y qué trabajador ve. */
class TrabajoDePrueba implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /** @var list<array{0: string|null, 1: string|null}> */
    public static array $vistos = [];

    public function handle(): void
    {
        self::$vistos[] = [tenant('id'), Trabajador::query()->value('apellido_paterno')];
    }
}
