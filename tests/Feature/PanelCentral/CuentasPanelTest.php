<?php

namespace Tests\Feature\PanelCentral;

use App\Enums\EstadoEstudio;
use App\Enums\TipoEstudio;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Models\Central\Superadmin;
use App\Models\User;
use App\Notifications\InvitacionEstudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Panel central: alta de cuentas con invitación, suspensión, acceso y eliminación. Datos ficticios. */
class CuentasPanelTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->activarModoSaas();

        $superadmin = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $superadmin->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($superadmin, 'superadmin');
    }

    /** @return array<string, string> */
    private function cuenta(array $cambios = []): array
    {
        return [
            'nombre' => 'Estudio Nuevo (prueba)', 'subdominio' => 'nuevo', 'nombre_admin' => 'Ana Prueba',
            'correo_admin' => 'Ana@Nuevo.test', 'tipo' => 'demo', 'vence_en' => now()->addDays(15)->toDateString(),
            ...$cambios,
        ];
    }

    #[Test]
    public function crea_la_cuenta_e_invita_al_administrador_que_crea_su_contrasena(): void
    {
        Notification::fake();
        $this->estudiosDelTest[] = 'nuevo';

        $respuesta = $this->post(self::PANEL.'/cuentas', $this->cuenta())->assertRedirect(self::PANEL.'/cuentas');

        $estudio = Estudio::query()->findOrFail('nuevo');
        $this->assertSame(TipoEstudio::Demo, $estudio->tipo);
        $this->assertSame('ana@nuevo.test', $estudio->correo_admin);
        $this->assertSame(now()->addDays(15)->toDateString(), $estudio->vence_en?->toDateString());

        // Correo con el enlace (y como el correo de pruebas no sale, el enlace también se muestra en el panel).
        Notification::assertSentTimes(InvitacionEstudio::class, 1);
        $enlace = data_get(session(SessionKey::FLASH_DATA), 'invitacion.enlace');
        $this->assertIsString($enlace);
        $this->assertStringStartsWith('http://nuevo.localhost/reset-password/', $enlace);

        // Con el enlace, el administrador crea su contraseña en SU estudio y entra.
        $this->nuevaPeticion();
        $this->get($enlace)->assertOk();
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $consulta);
        $token = basename((string) parse_url($enlace, PHP_URL_PATH));

        $this->post($this->url($estudio, '/reset-password'), [
            'token' => $token, 'email' => $consulta['email'],
            'password' => 'Mi-Clave-Nueva-2026!', 'password_confirmation' => 'Mi-Clave-Nueva-2026!',
        ])->assertSessionHasNoErrors();

        $this->ingresar($estudio, 'ana@nuevo.test', 'Mi-Clave-Nueva-2026!');
        $estudio->run(fn () => $this->assertSame('Ana Prueba', User::query()->sole()->name));

        $this->assertSame(['creado', 'invitado'], AuditoriaCentral::query()->orderBy('id')->pluck('accion')->all());
    }

    #[Test]
    public function valida_los_datos_de_la_cuenta(): void
    {
        $this->crearEstudio('ocupado', 'a@ocupado.test');

        $this->post(self::PANEL.'/cuentas', $this->cuenta(['subdominio' => 'admin']))->assertSessionHasErrors(['subdominio' => 'Ese subdominio está reservado.']);
        $this->post(self::PANEL.'/cuentas', $this->cuenta(['subdominio' => 'ocupado']))->assertSessionHasErrors(['subdominio' => 'Ya existe una cuenta con ese subdominio.']);
        $this->post(self::PANEL.'/cuentas', $this->cuenta(['vence_en' => null]))->assertSessionHasErrors(['vence_en' => 'Una demo necesita fecha de vencimiento.']);
        $this->post(self::PANEL.'/cuentas', $this->cuenta(['correo_admin' => 'no-es-correo']))->assertSessionHasErrors('correo_admin');

        $this->assertSame(['ocupado'], Estudio::query()->pluck('id')->all());
    }

    #[Test]
    public function lista_las_cuentas(): void
    {
        $this->crearEstudio('alfa', 'a@alfa.test');

        $this->get(self::PANEL.'/cuentas')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/cuentas/Index')
                ->has('cuentas', 1)
                ->where('cuentas.0.id', 'alfa')
                ->where('cuentas.0.url', 'http://alfa.localhost')
                ->where('cuentas.0.estado', 'activo'));
    }

    #[Test]
    public function una_cuenta_suspendida_no_deja_entrar_y_conserva_sus_datos(): void
    {
        $alfa = $this->crearEstudio('alfa', 'a@alfa.test');

        $this->patch(self::PANEL.'/cuentas/alfa/estado')->assertRedirect();
        $this->assertSame(EstadoEstudio::Suspendido, $alfa->fresh()?->estado);

        $this->nuevaPeticion();
        $this->get($this->url($alfa, '/login'))->assertForbidden()->assertSee('Cuenta suspendida');

        $this->actingAs(Superadmin::query()->sole(), 'superadmin');
        $this->patch(self::PANEL.'/cuentas/alfa/estado');
        $this->assertSame(EstadoEstudio::Activo, $alfa->fresh()?->estado);
        $this->nuevaPeticion();
        $this->get($this->url($alfa, '/login'))->assertOk();
        $alfa->run(fn () => $this->assertSame(1, User::query()->count()));
    }

    #[Test]
    public function restablece_el_acceso_del_administrador(): void
    {
        Notification::fake();
        $this->crearEstudio('alfa', 'a@alfa.test');

        $this->post(self::PANEL.'/cuentas/alfa/acceso')
            ->assertRedirect()
            ->assertInertiaFlash('invitacion.correo', 'a@alfa.test');

        Notification::assertSentTimes(InvitacionEstudio::class, 1);
    }

    #[Test]
    public function eliminar_pide_confirmar_con_el_subdominio(): void
    {
        $this->crearEstudio('alfa', 'a@alfa.test');
        $base = database_path(config('tenancy.database.prefix').'alfa');
        $this->assertFileExists($base);

        $this->delete(self::PANEL.'/cuentas/alfa', ['confirmacion' => 'beta'])->assertSessionHasErrors('confirmacion');
        $this->assertTrue(Estudio::query()->whereKey('alfa')->exists());

        $this->delete(self::PANEL.'/cuentas/alfa', ['confirmacion' => 'alfa'])->assertRedirect(self::PANEL.'/cuentas');
        $this->assertFalse(Estudio::query()->whereKey('alfa')->exists());
        $this->assertFileDoesNotExist($base);
        $this->assertCount(1, File::glob(storage_path('app/private/respaldos/eliminadas/alfa-*.sql.gz')) ?: [], 'Antes de eliminar queda un último respaldo.');

        $this->get(self::PANEL.'/actividad')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/Actividad')->where('registros.0.accion', 'Eliminada'));
    }
}
