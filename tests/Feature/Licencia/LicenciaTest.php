<?php

namespace Tests\Feature\Licencia;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConLicencia;
use Tests\TestCase;

/** Licencia firmada: modo solo lectura, límites del plan y pantalla de registro. Datos ficticios. */
class LicenciaTest extends TestCase
{
    use ConLicencia;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activarLicencia(instaladoEn: '2026-11-01');
        $this->travelTo('2026-11-09 10:00');
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function empresa(string $ruc = '20000000001'): array
    {
        return [
            'ruc' => $ruc, 'razon_social' => "Empresa Ficticia {$ruc} S.A.C.", 'nombre_comercial' => null,
            'direccion' => 'Av. Ficticia 123', 'ubigeo' => '150101', 'regimen_laboral' => 'general',
            'seguro_salud' => 'essalud', 'representante_legal' => null,
        ];
    }

    #[Test]
    public function en_prueba_se_trabaja_y_se_avisa_cuantos_dias_quedan(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('avisoLicencia.tipo', 'prueba')
                ->where('avisoLicencia.solo_lectura', false)
                ->where('avisoLicencia.dias_prueba', 7));

        $this->post(route('empresas.store'), $this->empresa())->assertSessionHasNoErrors();
        $this->assertSame(1, Empresa::query()->count());
    }

    #[Test]
    public function vencida_la_prueba_queda_en_solo_lectura_sin_borrar_nada(): void
    {
        Empresa::factory()->create(['razon_social' => 'Existente S.A.C.']);
        $this->travelTo('2026-11-20 10:00');

        $this->actingAs($this->admin)->get(route('empresas.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('avisoLicencia.solo_lectura', true));

        $this->from(route('empresas.create'))
            ->post(route('empresas.store'), $this->empresa())
            ->assertRedirect(route('empresas.create'))
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertSame(1, Empresa::query()->count());

        // JSON (p. ej. cálculo masivo): 423 con el motivo.
        $this->postJson(route('calculos.store'), ['anio' => 2026, 'mes' => 11])
            ->assertStatus(423)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'prueba'));

        // Salir del sistema y registrar la licencia siguen funcionando.
        $this->put(route('licencia.update'), ['clave' => $this->claveLicencia()])->assertSessionHasNoErrors();
        $this->post(route('empresas.store'), $this->empresa())->assertSessionHasNoErrors();
        $this->assertSame(2, Empresa::query()->count());

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    #[Test]
    public function una_clave_de_otro_dominio_deja_solo_lectura(): void
    {
        $this->guardarClave(['dominio' => 'planillas.otro-estudio.pe']);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('avisoLicencia.tipo', 'otro_dominio')
                ->where('avisoLicencia.mensaje', fn (string $m) => str_contains($m, 'planillas.otro-estudio.pe')));

        $this->post(route('empresas.store'), $this->empresa())->assertInertiaFlash('toast.type', 'error');
        $this->assertSame(0, Empresa::query()->count());
    }

    #[Test]
    public function con_licencia_valida_no_hay_aviso(): void
    {
        $this->guardarClave();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('avisoLicencia', null));
    }

    #[Test]
    public function limite_de_empresas_al_crear_y_al_reactivar(): void
    {
        $this->guardarClave(['max_empresas' => 2]);
        Empresa::factory()->create();
        $inactiva = Empresa::factory()->create(['activo' => false]);

        $this->actingAs($this->admin)->post(route('empresas.store'), $this->empresa('20000000028'))->assertSessionHasNoErrors();

        $this->from(route('empresas.index'))
            ->post(route('empresas.store'), $this->empresa('20000000010'))
            ->assertRedirect(route('empresas.index'))
            ->assertInertiaFlash('toast.message', 'Tu licencia Estudio permite hasta 2 empresas activas y ya tienes 2. Para continuar, desactiva alguno o amplía tu plan con Serialo.pe.');
        $this->assertSame(3, Empresa::query()->count());

        $this->patch(route('empresas.estado', $inactiva))->assertInertiaFlash('toast.type', 'error');
        $this->assertFalse($inactiva->refresh()->activo);
    }

    #[Test]
    public function limite_de_usuarios_al_crear_y_al_reactivar(): void
    {
        $this->guardarClave(['max_usuarios' => 2]);
        $inactivo = User::factory()->asistente()->create(['activo' => false]);
        User::factory()->asistente()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Asistente Ficticio', 'email' => 'ficticio@ejemplo.test', 'rol' => 'asistente',
            'password' => 'Clave-Segura-2026!', 'password_confirmation' => 'Clave-Segura-2026!', 'empresas' => [],
        ])->assertInertiaFlash('toast.message', 'Tu licencia Estudio permite hasta 2 usuarios activos y ya tienes 2. Para continuar, desactiva alguno o amplía tu plan con Serialo.pe.');
        $this->assertSame(0, User::query()->where('email', 'ficticio@ejemplo.test')->count());

        $this->patch(route('usuarios.estado', $inactivo))->assertInertiaFlash('toast.type', 'error');
        $this->assertFalse($inactivo->refresh()->activo);
    }

    #[Test]
    public function limite_de_trabajadores_cuenta_solo_los_activos(): void
    {
        $this->guardarClave(['max_trabajadores' => 2]);
        $empresa = Empresa::factory()->create();
        $establecimiento = $empresa->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
        Trabajador::factory()->for($empresa)->create(['fecha_cese' => '2026-10-31']); // cesado: no cuenta
        $cesado = Trabajador::factory()->for($empresa)->create(['fecha_cese' => '2026-10-31']);
        Trabajador::factory()->for($empresa)->create(['fecha_cese' => '2026-12-31']); // cese futuro: cuenta
        $activo = Trabajador::factory()->for($empresa)->create();

        $datos = [
            'tipo_documento' => '01', 'numero_documento' => '70009999', 'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Ficticia', 'nombres' => 'Ana', 'fecha_nacimiento' => '1995-03-10', 'sexo' => 'F',
            'email' => null, 'telefono' => null, 'establecimiento_id' => $establecimiento->id, 'cargo' => 'Asistente',
            'fecha_ingreso' => '2026-11-02', 'tipo_contrato' => 'indeterminado', 'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null, 'cuspp' => null,
            'tipo_comision_afp' => null, 'seguro_salud' => 'essalud', 'cuenta_sueldo' => null, 'cuenta_cts' => null,
        ];

        $this->actingAs($this->admin)->post(route('trabajadores.store'), $datos)
            ->assertInertiaFlash('toast.message', 'Tu licencia Estudio permite hasta 2 trabajadores activos y ya tienes 2. Para continuar, registra el cese de alguno o amplía tu plan con Serialo.pe.');
        $this->assertSame(4, Trabajador::query()->count());

        // Revertir un cese también suma un activo.
        $this->delete(route('trabajadores.cese.destroy', $cesado))->assertInertiaFlash('toast.type', 'error');
        $this->assertNotNull($cesado->refresh()->fecha_cese);

        // Con el cese de uno se libera cupo.
        $activo->update(['fecha_cese' => '2026-11-08']);
        $this->post(route('trabajadores.store'), $datos)->assertSessionHasNoErrors();
        $this->assertSame(5, Trabajador::query()->count());
    }

    #[Test]
    public function la_pantalla_de_licencia_muestra_plan_y_uso(): void
    {
        $this->guardarClave(['max_empresas' => 40]);
        Empresa::factory()->count(3)->create();

        $this->actingAs($this->admin)->get(route('licencia.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('licencia/Index')
                ->where('estado.tipo', 'valida')
                ->where('licencia.plan', 'Estudio')
                ->where('licencia.dominio', 'localhost')
                ->where('licencia.con_actualizaciones', true)
                ->where('uso.0.recurso', 'empresas')
                ->where('uso.0.usados', 3)
                ->where('uso.0.limite', 40)
                ->where('uso.2.limite', null));
    }

    #[Test]
    public function registra_una_clave_valida_y_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->put(route('licencia.update'), ['clave' => "  {$this->claveLicencia()}\n"])
            ->assertRedirect(route('licencia.show'))
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith('SERIALO-', (string) file_get_contents((string) config('licencia.archivo')));
        $auditoria = Auditoria::query()->where('auditable_type', 'licencia')->sole();
        $this->assertSame('creado', $auditoria->accion);
        $this->assertSame($this->admin->id, $auditoria->user_id);
        $this->assertStringContainsString('Estudio', (string) $auditoria->descripcion);
    }

    #[Test]
    public function rechaza_claves_invalidas_o_de_otro_dominio(): void
    {
        $this->actingAs($this->admin);

        $this->put(route('licencia.update'), ['clave' => 'SERIALO-no.vale'])->assertSessionHasErrors('clave');
        $this->put(route('licencia.update'), ['clave' => $this->claveLicencia(['dominio' => 'otro.pe'])])
            ->assertSessionHasErrors(['clave' => 'Esta clave es para otro.pe y el sistema está en localhost. Pide a Serialo.pe una clave para este dominio.']);

        $this->assertFileDoesNotExist((string) config('licencia.archivo'));
    }

    #[Test]
    public function solo_el_admin_ve_y_cambia_la_licencia(): void
    {
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente)->get(route('licencia.show'))->assertForbidden();
        $this->put(route('licencia.update'), ['clave' => $this->claveLicencia()])->assertForbidden();
        $this->assertFileDoesNotExist((string) config('licencia.archivo'));
    }
}
