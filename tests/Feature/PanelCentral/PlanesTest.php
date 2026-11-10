<?php

namespace Tests\Feature\PanelCentral;

use App\Enums\EstadoEstudio;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Models\Central\Superadmin;
use App\Models\Empresa;
use App\Notifications\AvisoVencimientoCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Planes del modo SaaS: límites por cuenta, vencimiento (solo lectura) y avisos por correo. Datos ficticios. */
class PlanesTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00');
        $this->activarModoSaas();
    }

    private function superadmin(): void
    {
        $s = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($s, 'superadmin');
    }

    /** @return array<string, string> */
    private function empresa(string $ruc): array
    {
        return [
            'ruc' => $ruc, 'razon_social' => "Empresa {$ruc} S.A.C.", 'nombre_comercial' => null, 'direccion' => 'Av. Ficticia 1',
            'ubigeo' => '150101', 'regimen_laboral' => 'general', 'seguro_salud' => 'essalud', 'representante_legal' => null,
        ];
    }

    #[Test]
    public function el_estudio_trabaja_con_los_limites_de_su_plan(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $alfa->update(['plan' => 'Empresa', 'max_empresas' => 1]);

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->post($this->url($alfa, '/empresas'), $this->empresa('20000000001'))->assertSessionHasNoErrors();
        $this->post($this->url($alfa, '/empresas'), $this->empresa('20000000028'))
            ->assertInertiaFlash('toast.message', 'Tu plan Empresa permite hasta 1 empresa activa y ya tienes 1. Para continuar, desactiva alguno o amplía tu plan con Serialo.pe.');

        $alfa->run(fn () => $this->assertSame(1, Empresa::query()->count()));
    }

    #[Test]
    public function una_demo_vigente_avisa_los_dias_y_vencida_queda_en_solo_lectura(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $alfa->update(['tipo' => 'demo', 'vence_en' => '2026-11-20']);

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->get($this->url($alfa, '/dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('avisoLicencia.dias_prueba', 11)
            ->where('avisoLicencia.solo_lectura', false));

        // Vence: se puede entrar y ver, pero no guardar.
        $this->travelTo('2026-11-21 09:00');
        $this->get($this->url($alfa, '/empresas'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('avisoLicencia.solo_lectura', true));
        $this->post($this->url($alfa, '/empresas'), $this->empresa('20000000001'))
            ->assertInertiaFlash('toast.message', 'La demo venció el 20/11/2026. El sistema queda en solo lectura: tus datos siguen intactos. Comunícate con Serialo.pe para continuar.');
        $alfa->run(fn () => $this->assertSame(0, Empresa::query()->count()));
    }

    #[Test]
    public function la_pantalla_de_licencia_del_estudio_muestra_el_plan_sin_clave(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->get($this->url($alfa, '/licencia'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('saas', true)->where('licencia', null)->where('estado.tipo', 'valida'));
        $this->put($this->url($alfa, '/licencia'), ['clave' => 'SERIALO-x.y'])->assertNotFound();
    }

    #[Test]
    public function el_panel_crea_y_edita_el_plan_de_una_cuenta(): void
    {
        $this->superadmin();
        Notification::fake();
        $this->estudiosDelTest[] = 'nuevo';

        $this->post(self::PANEL.'/cuentas', [
            'nombre' => 'Estudio Nuevo', 'subdominio' => 'nuevo', 'nombre_admin' => 'Ana', 'correo_admin' => 'ana@nuevo.test',
            'tipo' => 'cliente', 'plan' => 'Estudio Pro', 'max_empresas' => null, 'max_usuarios' => 10, 'max_trabajadores' => null,
        ])->assertRedirect(self::PANEL.'/cuentas');

        $nuevo = Estudio::query()->findOrFail('nuevo');
        $this->assertSame(['Estudio Pro', null, 10, null], [$nuevo->plan, $nuevo->max_empresas, $nuevo->max_usuarios, $nuevo->max_trabajadores]);

        $this->get(self::PANEL.'/cuentas/nuevo/editar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('panel/cuentas/Edit')->where('cuenta.plan', 'Estudio Pro'));

        $this->put(self::PANEL.'/cuentas/nuevo', [
            'nombre' => 'Estudio Nuevo', 'tipo' => 'cliente', 'plan' => 'Estudio', 'max_empresas' => 40, 'max_usuarios' => 3,
            'max_trabajadores' => null, 'vence_en' => '2027-11-10',
        ])->assertRedirect(self::PANEL.'/cuentas');

        $nuevo->refresh();
        $this->assertSame([40, 3, '2027-11-10'], [$nuevo->max_empresas, $nuevo->max_usuarios, $nuevo->vence_en?->toDateString()]);
        $cambios = AuditoriaCentral::query()->where('accion', 'actualizado')->sole()->cambios;
        $this->assertSame([null, 40], $cambios['max_empresas'] ?? null);
        $this->assertSame([null, '2027-11-10'], $cambios['vence_en'] ?? null);
    }

    #[Test]
    public function avisa_por_correo_una_vez_antes_de_vencer_y_otra_al_vencer(): void
    {
        Notification::fake();
        $porVencer = $this->crearEstudio('alfa', 'admin@alfa.test');
        $porVencer->update(['tipo' => 'demo', 'vence_en' => '2026-11-12']);
        $lejana = $this->crearEstudio('beta', 'admin@beta.test');
        $lejana->update(['tipo' => 'demo', 'vence_en' => '2026-12-31']);
        $suspendida = $this->crearEstudio('gama', 'admin@gama.test');
        $suspendida->update(['vence_en' => '2026-11-01', 'estado' => EstadoEstudio::Suspendido]);

        $this->artisan('estudios:avisar-vencimientos')->assertSuccessful();
        $this->artisan('estudios:avisar-vencimientos')->assertSuccessful(); // no repite

        Notification::assertSentOnDemandTimes(AvisoVencimientoCuenta::class, 1);
        Notification::assertSentOnDemand(AvisoVencimientoCuenta::class, fn ($n, $canales, $notifiable) => $notifiable->routes['mail'] === 'admin@alfa.test' && ! $n->vencida);
        $this->assertSame('por_vencer', $porVencer->fresh()?->aviso_vencimiento);

        // Al vencer, un segundo aviso.
        $this->travelTo('2026-11-13 08:00');
        $this->artisan('estudios:avisar-vencimientos')->assertSuccessful();
        Notification::assertSentOnDemandTimes(AvisoVencimientoCuenta::class, 2);
        $this->assertSame('vencida', $porVencer->fresh()?->aviso_vencimiento);
    }
}
