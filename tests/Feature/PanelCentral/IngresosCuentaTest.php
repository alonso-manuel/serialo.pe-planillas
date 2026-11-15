<?php

namespace Tests\Feature\PanelCentral;

use App\Models\Central\Estudio;
use App\Models\Central\Superadmin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Panel central: cuántas veces y cuándo entraron los usuarios de cada cuenta (¿el prospecto probó la demo?). */
class IngresosCuentaTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 09:00');
        $this->activarModoSaas();
    }

    #[Test]
    public function cada_ingreso_suma_en_la_cuenta_con_fecha_y_usuario(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->crearEstudio('beta', 'admin@beta.test');

        $this->ingresar($alfa, 'admin@alfa.test');
        Carbon::setTestNow('2026-11-15 18:30');
        $this->ingresar($alfa, 'admin@alfa.test');

        $alfa->refresh();
        $this->assertSame(2, $alfa->ingresos);
        $this->assertSame('2026-11-15 18:30', $alfa->ultimo_ingreso_en?->format('Y-m-d H:i'));
        $this->assertSame('Administrador', $alfa->ultimo_ingreso_usuario);
        $this->assertSame(0, Estudio::query()->findOrFail('beta')->ingresos); // las demás cuentas no cambian

        $s = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->nuevaPeticion();
        $this->actingAs($s, 'superadmin')->get(self::PANEL.'/cuentas')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/cuentas/Index')
                ->where('cuentas', function (Collection $cuentas): bool {
                    $porId = $cuentas->keyBy('id');
                    $this->assertSame(2, $porId['alfa']['ingresos']);
                    $this->assertSame('15/11/2026 18:30', $porId['alfa']['ultimo_ingreso']);
                    $this->assertSame('Administrador', $porId['alfa']['ultimo_ingreso_usuario']);
                    $this->assertNull($porId['beta']['ultimo_ingreso']); // «Nunca ingresó»

                    return true;
                }));
    }

    #[Test]
    public function el_acceso_de_soporte_no_cuenta_como_ingreso_del_cliente(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');

        $alfa->run(function (): void {
            $soporte = User::factory()->admin()->create(['name' => 'Soporte Serialo.pe', 'es_soporte' => true]);
            event(new Login('web', $soporte, false));
        });

        $this->assertSame(0, $alfa->refresh()->ingresos);
        $this->assertNull($alfa->ultimo_ingreso_en);
    }
}
