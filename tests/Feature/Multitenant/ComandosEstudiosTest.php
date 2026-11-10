<?php

namespace Tests\Feature\Multitenant;

use App\Enums\RolUsuario;
use App\Models\Central\Estudio;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Comandos del modo SaaS: alta de estudios y migración de todas las bases. */
class ComandosEstudiosTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    #[Test]
    public function crea_el_estudio_con_su_base_limpia_y_su_administrador(): void
    {
        $this->activarModoSaas();

        $this->artisan('estudio:crear', [
            'subdominio' => 'garcia', 'nombre' => 'Estudio García', 'correo' => 'Admin@Garcia.test',
            '--admin' => 'Ana García', '--clave' => 'Clave-Segura-2026!',
        ])
            ->expectsOutputToContain('Estudio «Estudio García» creado.')
            ->expectsOutputToContain('https://garcia.localhost')
            ->assertSuccessful();

        $estudio = Estudio::query()->findOrFail('garcia');
        $this->assertSame('Estudio García', $estudio->nombre);
        $this->assertSame(['garcia'], $estudio->domains()->pluck('domain')->all());

        $estudio->run(function (): void {
            $admin = User::query()->sole();
            $this->assertSame('admin@garcia.test', $admin->email);
            $this->assertSame('Ana García', $admin->name);
            $this->assertSame(RolUsuario::Admin, $admin->rol);
            $this->assertNotNull($admin->email_verified_at);

            // Catálogos legales cargados; sin empresas ni datos demo.
            $this->assertGreaterThan(0, Parametro::query()->count());
            $this->assertSame(0, Empresa::query()->count());
        });

        // La base central no recibe datos del estudio.
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function valida_el_subdominio(): void
    {
        $this->activarModoSaas();
        $this->crearEstudio('garcia', 'a@garcia.test');

        foreach (['admin', 'ab', 'Con Espacios', '9inicia', 'termina-', 'garcia'] as $subdominio) {
            $this->artisan('estudio:crear', ['subdominio' => $subdominio, 'nombre' => 'X', 'correo' => 'x@x.test'])
                ->assertFailed();
        }

        $this->assertSame(['garcia'], Estudio::query()->pluck('id')->all());
    }

    #[Test]
    public function migra_la_base_central_y_las_de_cada_estudio(): void
    {
        $this->activarModoSaas();
        $this->crearEstudio('garcia', 'a@garcia.test');

        $this->artisan('estudios:migrar')
            ->expectsOutputToContain('Bases de los estudios')
            ->assertSuccessful();
    }

    #[Test]
    public function en_una_instalacion_unica_los_comandos_no_estan_disponibles(): void
    {
        $this->artisan('estudio:crear', ['subdominio' => 'garcia', 'nombre' => 'X', 'correo' => 'x@x.test'])
            ->expectsOutputToContain('PLANILLAS_MODO=saas')
            ->assertFailed();
        $this->artisan('estudios:migrar')->assertFailed();
    }
}
