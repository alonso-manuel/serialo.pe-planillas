<?php

namespace Tests\Feature\PanelCentral;

use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Superadmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Panel central (admin.impulso.cfd): superadmin con doble factor obligatorio. */
class AccesoPanelTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->activarModoSaas();
    }

    private function superadmin(): Superadmin
    {
        $this->artisan('superadmin:crear', ['correo' => 'yo@serialo.test', '--nombre' => 'Alonso', '--clave' => 'Clave-Panel-2026!'])
            ->assertSuccessful();

        return Superadmin::query()->sole();
    }

    private function otp(Superadmin $superadmin): string
    {
        return (new Google2FA)->getCurrentOtp(Fortify::currentEncrypter()->decrypt((string) $superadmin->fresh()?->two_factor_secret));
    }

    #[Test]
    public function el_comando_crea_el_superadmin_y_exige_una_clave_fuerte(): void
    {
        $this->artisan('superadmin:crear', ['correo' => 'yo@serialo.test', '--clave' => '123'])->assertFailed();
        $this->assertSame(0, Superadmin::query()->count());

        $superadmin = $this->superadmin();
        $this->assertSame('Alonso', $superadmin->name);
        $this->assertFalse($superadmin->tieneDosFactores());
    }

    #[Test]
    public function primer_ingreso_obliga_a_configurar_el_doble_factor(): void
    {
        $superadmin = $this->superadmin();

        $this->get(self::PANEL.'/cuentas')->assertRedirect(self::PANEL.'/ingresar');
        $this->get(self::PANEL.'/ingresar')->assertOk()->assertInertia(fn (Assert $p) => $p->component('panel/acceso/Ingresar'));

        $this->post(self::PANEL.'/ingresar', ['email' => 'yo@serialo.test', 'password' => 'otra'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

        $this->post(self::PANEL.'/ingresar', ['email' => 'YO@serialo.test', 'password' => 'Clave-Panel-2026!'])
            ->assertRedirect(self::PANEL.'/seguridad/doble-factor');

        // Sin doble factor no se administra nada.
        $this->get(self::PANEL.'/cuentas')->assertRedirect(self::PANEL.'/seguridad/doble-factor');

        $this->get(self::PANEL.'/seguridad/doble-factor')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/acceso/DobleFactor')->has('qr')->has('clave'));

        $this->post(self::PANEL.'/seguridad/doble-factor', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->post(self::PANEL.'/seguridad/doble-factor', ['code' => $this->otp($superadmin)])
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/acceso/CodigosRecuperacion')->has('codigos', 8));

        $this->assertTrue($superadmin->fresh()?->tieneDosFactores());
        $this->get(self::PANEL.'/cuentas')->assertOk();
        $this->assertSame(['ingreso', 'doble_factor'], AuditoriaCentral::query()->orderBy('id')->pluck('accion')->all());
    }

    #[Test]
    public function con_doble_factor_pide_el_codigo_o_uno_de_recuperacion(): void
    {
        $superadmin = $this->superadmin();
        $superadmin->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode(['recupera-1', 'recupera-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(self::PANEL.'/ingresar', ['email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!'])
            ->assertRedirect(self::PANEL.'/verificacion');
        $this->assertGuest('superadmin');

        $this->post(self::PANEL.'/verificacion', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post(self::PANEL.'/verificacion', ['code' => $this->otp($superadmin)])->assertRedirect(self::PANEL.'/cuentas');
        $this->assertAuthenticated('superadmin');

        // Código de recuperación: sirve una sola vez.
        $this->post(self::PANEL.'/salir')->assertRedirect(self::PANEL.'/ingresar');
        $this->post(self::PANEL.'/ingresar', ['email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $this->post(self::PANEL.'/verificacion', ['recovery_code' => 'recupera-1'])->assertRedirect(self::PANEL.'/cuentas');
        $this->assertNotContains('recupera-1', $superadmin->fresh()?->recoveryCodes() ?? []);
    }

    #[Test]
    public function el_panel_y_los_estudios_no_comparten_rutas(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');

        // Rutas de un estudio en el dominio del panel, y del panel en un estudio: no existen.
        $this->get(self::PANEL.'/login')->assertNotFound();
        $this->get(self::PANEL.'/dashboard')->assertNotFound();
        $this->get($this->url($alfa, '/cuentas'))->assertNotFound();
        $this->get($this->url($alfa, '/ingresar'))->assertNotFound();

        // El administrador de un estudio no entra al panel.
        $this->ingresar($alfa, 'admin@alfa.test');
        $this->nuevaPeticion();
        $this->get(self::PANEL.'/cuentas')->assertRedirect(self::PANEL.'/ingresar');
    }

    #[Test]
    public function en_una_instalacion_unica_el_panel_no_existe(): void
    {
        config(['planillas.modo' => 'unica']);

        $this->get(self::PANEL.'/ingresar')->assertNotFound();
    }
}
