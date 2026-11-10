<?php

namespace Tests\Feature\Instalador;

use App\Enums\RolUsuario;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\User;
use App\Services\Instalador\ConexionBaseDatos;
use App\Services\Instalador\Instalacion;
use App\Services\Instalador\Requisitos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Instalador web. Trabaja en una carpeta temporal (marca, código y .env) y una BD SQLite aparte. */
class InstaladorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/instalador-'.Str::random(8));
        File::ensureDirectoryExists($this->dir);
        config(['instalador.omitir' => false, 'instalador.directorio' => $this->dir]);
        $this->app->useEnvironmentPath($this->dir);

        // El servidor de CI puede no tener todas las extensiones del hosting.
        $this->app->instance(Requisitos::class, new class extends Requisitos
        {
            public function revisar(): array
            {
                return [['nombre' => 'PHP', 'ok' => true, 'ayuda' => '']];
            }
        });
    }

    protected function tearDown(): void
    {
        DB::purge('instalado');
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function codigo(): string
    {
        return trim((string) file_get_contents($this->dir.'/codigo-instalacion.txt'));
    }

    /** Conexión de prueba: en vez de MySQL, una SQLite en la carpeta temporal. */
    private function usarSqlite(): string
    {
        $archivo = $this->dir.'/bd.sqlite';
        touch($archivo);

        $this->app->instance(ConexionBaseDatos::class, new class($archivo) extends ConexionBaseDatos
        {
            public function __construct(private readonly string $archivo) {}

            public function probar(array $datos): ?string
            {
                return null;
            }

            public function configurar(array $datos): void
            {
                config([
                    'database.connections.instalado' => array_merge((array) config('database.connections.sqlite'), ['database' => $this->archivo]),
                    'database.default' => 'instalado',
                ]);
                DB::purge('instalado');
            }
        });

        return $archivo;
    }

    private function hastaSistema(): void
    {
        $this->get(route('instalar.inicio'))->assertOk();
        $this->post(route('instalar.codigo'), ['codigo' => $this->codigo()])->assertRedirect(route('instalar.base-datos'));
        $this->post(route('instalar.base-datos.store'), [
            'host' => 'localhost', 'puerto' => 3306, 'nombre' => 'planillas', 'usuario' => 'planillas', 'clave' => 'Cl@ve$ecreta',
        ])->assertRedirect(route('instalar.sistema'));
    }

    #[Test]
    public function mientras_no_esta_instalado_todo_lleva_al_instalador(): void
    {
        $this->get('/login')->assertRedirect(route('instalar.inicio'));
        $this->get('/dashboard')->assertRedirect(route('instalar.inicio'));

        $this->get(route('instalar.inicio'))
            ->assertOk()
            ->assertSee('Requisitos del servidor')
            ->assertSee('codigo-instalacion.txt');

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $this->codigo());
    }

    #[Test]
    public function sin_el_codigo_correcto_no_avanza(): void
    {
        $this->get(route('instalar.inicio'));
        $this->post(route('instalar.codigo'), ['codigo' => 'AAAA-BBBB-CCCC'])
            ->assertSessionHasErrors(['codigo' => 'El código de instalación no es correcto.']);

        $this->get(route('instalar.base-datos'))->assertRedirect(route('instalar.inicio'));
        $this->get(route('instalar.sistema'))->assertRedirect(route('instalar.inicio'));
    }

    #[Test]
    public function una_conexion_fallida_muestra_el_error_y_no_guarda_nada(): void
    {
        $this->get(route('instalar.inicio'));
        $this->post(route('instalar.codigo'), ['codigo' => strtolower($this->codigo())]); // sin importar mayúsculas

        $this->post(route('instalar.base-datos.store'), [
            'host' => '127.0.0.1', 'puerto' => 1, 'nombre' => 'no_existe', 'usuario' => 'nadie', 'clave' => 'x',
        ])->assertSessionHasErrors('conexion');

        $this->assertFileDoesNotExist($this->dir.'/.env');
        $this->assertFileDoesNotExist($this->dir.'/instalado.json');
        $this->get(route('instalar.sistema'))->assertRedirect(route('instalar.inicio'));
    }

    #[Test]
    public function instala_el_sistema_completo(): void
    {
        $this->usarSqlite();
        $this->hastaSistema();

        $this->post(route('instalar.sistema.store'), [
            'sistema' => 'Planillas Estudio Demo',
            'url' => 'https://planillas.ejemplo.test/',
            'admin_nombre' => 'Administradora Demo',
            'admin_correo' => 'admin@ejemplo.test',
            'admin_clave' => 'Clave-Segura-2026!',
            'admin_clave_confirmation' => 'Clave-Segura-2026!',
        ])->assertOk()->assertSee('Instalación terminada')->assertSee('schedule:run');

        // Marca de instalado y el código ya no existe.
        $this->assertFileExists($this->dir.'/instalado.json');
        $this->assertFileDoesNotExist($this->dir.'/codigo-instalacion.txt');

        // .env definitivo (la clave con $ queda literal entre comillas simples).
        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString("APP_NAME='Planillas Estudio Demo'", $env);
        $this->assertStringContainsString('APP_URL=https://planillas.ejemplo.test', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('DB_DATABASE=planillas', $env);
        $this->assertStringContainsString("DB_PASSWORD='Cl@ve\$ecreta'", $env);
        $this->assertStringContainsString('SESSION_DRIVER=database', $env);
        $this->assertStringContainsString('MAIL_MAILER=log', $env);

        // Tablas, catálogos y primer administrador; sin datos demo.
        $admin = User::query()->where('email', 'admin@ejemplo.test')->sole();
        $this->assertSame(RolUsuario::Admin, $admin->rol);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertGreaterThan(0, Parametro::query()->count());
        $this->assertSame(0, Empresa::query()->count());

        // Desde ahora el instalador ya no existe.
        $this->get(route('instalar.inicio'))->assertNotFound();
    }

    #[Test]
    public function rechaza_una_contrasena_debil_del_administrador(): void
    {
        $this->usarSqlite();
        $this->hastaSistema();

        $this->post(route('instalar.sistema.store'), [
            'sistema' => 'Planillas', 'url' => 'https://planillas.ejemplo.test',
            'admin_nombre' => 'Admin', 'admin_correo' => 'admin@ejemplo.test',
            'admin_clave' => '123', 'admin_clave_confirmation' => '123',
        ])->assertSessionHasErrors('admin_clave');

        $this->assertFileDoesNotExist($this->dir.'/instalado.json');
    }

    #[Test]
    public function sin_app_key_crea_el_env_de_produccion_con_su_clave(): void
    {
        config(['app.key' => '']);

        $this->get(route('instalar.inicio'))->assertOk();

        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:\S{40,}$/m', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);
    }

    #[Test]
    public function ya_instalado_el_instalador_responde_404(): void
    {
        app(Instalacion::class)->marcarInstalado('1.0.0');

        $this->get(route('instalar.inicio'))->assertNotFound();
        $this->get('/login')->assertOk();
    }
}
