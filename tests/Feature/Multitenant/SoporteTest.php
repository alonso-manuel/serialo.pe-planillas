<?php

namespace Tests\Feature\Multitenant;

use App\Domain\Licencia\Recurso;
use App\Models\Auditoria;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Models\Central\Superadmin;
use App\Models\Empresa;
use App\Models\PermisoSoporte;
use App\Models\User;
use App\Services\Licencia\LicenciaActual;
use App\Services\Soporte\AccesoSoporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Entrar como soporte: solo con permiso del estudio, enlace de un solo uso, auditado y sin contar para el plan. Datos ficticios. */
class SoporteTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private const PANEL = 'http://admin.impulso.cfd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00');
        $this->activarModoSaas();
    }

    private function comoSuperadmin(): void
    {
        $this->nuevaPeticion();
        $s = Superadmin::query()->firstOrCreate(['email' => 'yo@serialo.test'], ['name' => 'Alonso', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($s, 'superadmin');
    }

    private function pedirSoporte(Estudio $estudio): TestResponse
    {
        $this->comoSuperadmin();

        return $this->post(self::PANEL."/cuentas/{$estudio->id}/soporte", [], ['X-Inertia' => 'true']);
    }

    private function autorizar(Estudio $estudio): void
    {
        $this->ingresar($estudio, "admin@{$estudio->id}.test");
        $this->post($this->url($estudio, '/soporte/permiso'))->assertSessionHasNoErrors();
    }

    #[Test]
    public function sin_permiso_del_estudio_el_panel_no_puede_entrar(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');

        $this->pedirSoporte($alfa)->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Estudio Alfa no ha autorizado el acceso de soporte. Pídele al administrador que lo active en Administración → Licencia.');
        $this->assertFalse(AuditoriaCentral::query()->where('accion', 'soporte')->exists());
    }

    #[Test]
    public function con_permiso_entra_como_soporte_una_sola_vez_por_enlace_y_todo_queda_auditado(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->autorizar($alfa);

        $respuesta = $this->pedirSoporte($alfa)->assertStatus(409);
        $enlace = (string) $respuesta->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('http://alfa.localhost/soporte/entrar/', $enlace);
        $this->assertSame('soporte', AuditoriaCentral::query()->latest('id')->value('accion'));

        $this->nuevaPeticion();
        $this->get($enlace)->assertRedirect(route('dashboard'));
        $this->get($this->url($alfa, '/empresas'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('auth.esSoporte', true)->where('auth.esAdmin', true));

        // Lo que hace soporte queda a su nombre.
        $this->post($this->url($alfa, '/empresas'), [
            'ruc' => '20000000001', 'razon_social' => 'Arreglada por Soporte S.A.C.', 'nombre_comercial' => null, 'direccion' => 'Av. Ficticia 1',
            'ubigeo' => '150101', 'regimen_laboral' => 'general', 'seguro_salud' => 'essalud', 'representante_legal' => null,
        ])->assertSessionHasNoErrors();

        $alfa->run(function () {
            $soporte = User::query()->where('es_soporte', true)->firstOrFail();
            $this->assertSame('Soporte Serialo.pe', $soporte->name);
            $this->assertSame($soporte->id, Auditoria::query()->where('auditable_type', 'empresa')->value('user_id'));
            $this->assertSame(['autorizado', 'ingreso'], Auditoria::query()->where('auditable_type', 'soporte')->orderBy('id')->pluck('accion')->all());
            $this->assertSame(1, Empresa::query()->count());
        });

        // El mismo enlace no sirve dos veces.
        $this->nuevaPeticion();
        $this->get($enlace)->assertRedirect(route('login'));
    }

    #[Test]
    public function el_enlace_vence_al_minuto(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->autorizar($alfa);
        $enlace = (string) $this->pedirSoporte($alfa)->headers->get('X-Inertia-Location');

        $this->travel(61)->seconds();
        $this->nuevaPeticion();
        $this->get($enlace)->assertRedirect(route('login'));
        $this->get($this->url($alfa, '/dashboard'))->assertRedirect();
    }

    #[Test]
    public function al_quitar_el_permiso_o_vencer_se_cierra_la_sesion_de_soporte(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->autorizar($alfa);
        $enlace = (string) $this->pedirSoporte($alfa)->headers->get('X-Inertia-Location');
        $this->nuevaPeticion();
        $this->get($enlace);
        $this->get($this->url($alfa, '/dashboard'))->assertOk();

        // Vence (a las 24 horas).
        $alfa->run(fn () => PermisoSoporte::query()->update(['vence_en' => now()->subMinute()]));
        $this->get($this->url($alfa, '/dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->get($this->url($alfa, '/dashboard'))->assertRedirect(route('login'));

        // Lo quita el administrador.
        $this->autorizar($alfa);
        $enlace = (string) $this->pedirSoporte($alfa)->headers->get('X-Inertia-Location');
        $this->nuevaPeticion();
        $this->get($enlace);
        $alfa->run(fn () => app(AccesoSoporte::class)->revocar());
        $this->get($this->url($alfa, '/dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function el_usuario_de_soporte_no_entra_con_clave_no_aparece_en_usuarios_ni_cuenta_para_el_plan(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $this->autorizar($alfa);
        $enlace = (string) $this->pedirSoporte($alfa)->headers->get('X-Inertia-Location');
        $this->nuevaPeticion();
        $this->get($enlace);

        $alfa->run(function () {
            User::query()->where('es_soporte', true)->update(['password' => bcrypt('Clave-Conocida-2026!')]);
            $this->assertSame(1, app(LicenciaActual::class)->uso()[Recurso::Usuarios->value]);
        });

        $this->nuevaPeticion();
        $this->post($this->url($alfa, '/login'), ['email' => 'soporte@serialo.pe', 'password' => 'Clave-Conocida-2026!'])->assertRedirect();
        $this->assertGuest('web');

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->get($this->url($alfa, '/usuarios'))->assertInertia(fn (Assert $p) => $p->has('usuarios.data', 1)->where('usuarios.data.0.email', 'admin@alfa.test'));
    }

    #[Test]
    public function solo_el_admin_del_estudio_autoriza_y_ve_el_estado(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $alfa->run(fn () => User::factory()->asistente()->create(['email' => 'asis@alfa.test', 'password' => 'Clave-Segura-2026!']));

        $this->ingresar($alfa, 'asis@alfa.test');
        $this->post($this->url($alfa, '/soporte/permiso'))->assertForbidden();

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->get($this->url($alfa, '/licencia'))->assertInertia(fn (Assert $p) => $p->where('soporte.vigente', false));
        $this->post($this->url($alfa, '/soporte/permiso'));
        $this->get($this->url($alfa, '/licencia'))->assertInertia(fn (Assert $p) => $p
            ->where('soporte.vigente', true)->where('soporte.vence', '11/11/2026 09:00')->where('soporte.autorizado_por', 'Administrador'));
        $this->delete($this->url($alfa, '/soporte/permiso'));
        $this->get($this->url($alfa, '/licencia'))->assertInertia(fn (Assert $p) => $p->where('soporte.vigente', false));
    }
}
