<?php

namespace Tests\Feature\Auditoria;

use App\Enums\TipoConcepto;
use App\Models\Afp;
use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regla 7: quién cambió qué, cuándo y desde dónde. Datos ficticios. */
class RegistroAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->admin = Auditoria::sinRegistrar(fn () => User::factory()->admin()->create(['name' => 'Ana Admin']));
    }

    /** @return array<string, mixed> */
    private function datosEmpresa(array $cambios = []): array
    {
        return [
            'ruc' => '20000000001', 'razon_social' => 'Andes S.A.C.', 'nombre_comercial' => null,
            'direccion' => 'Av. Ficticia 1', 'ubigeo' => null, 'regimen_laboral' => 'general',
            'seguro_salud' => 'essalud', 'representante_legal' => null, ...$cambios,
        ];
    }

    #[Test]
    public function crear_editar_y_desactivar_una_empresa_queda_registrado(): void
    {
        $this->actingAs($this->admin)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->post(route('empresas.store'), $this->datosEmpresa());
        $empresa = Empresa::sole();

        $creado = Auditoria::where('auditable_type', 'empresa')->where('accion', 'creado')->sole();
        $this->assertSame($this->admin->id, $creado->user_id);
        $this->assertSame($empresa->id, $creado->empresa_id);
        $this->assertSame('10.0.0.7', $creado->ip);
        $this->assertSame('Andes S.A.C. (RUC 20000000001)', $creado->descripcion);
        $this->assertSame([null, '20000000001'], $creado->cambios['ruc']);
        $this->assertArrayNotHasKey('created_at', $creado->cambios);

        // El establecimiento 0000 creado junto con la empresa también queda registrado.
        $this->assertTrue(Auditoria::where('auditable_type', 'establecimiento')->where('accion', 'creado')->exists());

        $this->put(route('empresas.update', $empresa), $this->datosEmpresa(['regimen_laboral' => 'pequena']));
        $editado = Auditoria::where('auditable_type', 'empresa')->where('accion', 'actualizado')->sole();
        $this->assertSame(['regimen_laboral' => ['general', 'pequena']], $editado->cambios);

        $this->patch(route('empresas.estado', $empresa));
        $this->assertSame(['activo' => [true, false]], Auditoria::latest('id')->first()->cambios);
    }

    #[Test]
    public function una_edicion_sin_cambios_no_registra_nada(): void
    {
        $empresa = Auditoria::sinRegistrar(fn () => Empresa::factory()->create(['razon_social' => 'Igual S.A.C.']));

        $empresa->update(['razon_social' => 'Igual S.A.C.']);
        $empresa->touch();

        $this->assertSame(0, Auditoria::count());
    }

    #[Test]
    public function usuario_registra_rol_y_empresas_pero_nunca_la_contrasena(): void
    {
        [$alfa, $beta] = Auditoria::sinRegistrar(fn () => [
            Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.', 'ruc' => '20000000010']),
            Empresa::factory()->create(['razon_social' => 'Beta S.A.C.', 'ruc' => '20000000028']),
        ]);

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Beto', 'email' => 'beto@ejemplo.pe', 'rol' => 'asistente',
            'password' => 'Clave-Segura-2026', 'empresas' => [$alfa->id],
        ]);
        $beto = User::where('email', 'beto@ejemplo.pe')->sole();

        $creado = Auditoria::where('auditable_type', 'usuario')->where('accion', 'creado')->sole();
        $this->assertSame([null, '(cambiada)'], $creado->cambios['password']);
        $this->assertStringNotContainsString($beto->password, json_encode($creado->cambios));
        $this->assertArrayNotHasKey('remember_token', $creado->cambios);
        $this->assertSame([[], ['Alfa S.A.C.']], $creado->cambios['empresas']);

        $this->put(route('usuarios.update', $beto), [
            'name' => 'Beto', 'email' => 'beto@ejemplo.pe', 'rol' => 'asistente',
            'password' => 'Otra-Clave-2026', 'empresas' => [$beta->id],
        ]);

        $editado = Auditoria::where('auditable_type', 'usuario')->where('accion', 'actualizado')->sole();
        $this->assertSame(['(cambiada)', '(cambiada)'], $editado->cambios['password']);
        $this->assertSame([['Alfa S.A.C.'], ['Beta S.A.C.']], $editado->cambios['empresas']);
        $this->assertSame('Beto (beto@ejemplo.pe)', $editado->descripcion);
    }

    #[Test]
    public function parametro_tasa_afp_y_concepto_quedan_registrados(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1200', 'vigente_desde' => '2027-01', 'base_legal' => 'Norma ficticia',
        ]);
        $parametro = Parametro::sole();
        $this->delete(route('parametros.destroy', $parametro));

        $this->assertSame(
            ['creado', 'eliminado'],
            Auditoria::where('auditable_type', 'parametro')->orderBy('id')->pluck('accion')->all(),
        );
        $eliminado = Auditoria::where('accion', 'eliminado')->sole();
        $this->assertSame('Remuneración Mínima Vital (RMV) desde 01/2027', $eliminado->descripcion);
        $this->assertSame(['1200.0000', null], $eliminado->cambios['valor']);

        $afp = Auditoria::sinRegistrar(fn () => Afp::create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'activo' => true]));
        $afp->tasas()->create([
            'anio' => 2026, 'mes' => 12, 'aporte_obligatorio' => '0.1000', 'prima_seguro' => '0.0137',
            'comision_flujo' => '0.0147', 'comision_mixta' => '0', 'remuneracion_maxima_asegurable' => '12000.00',
        ]);
        $this->assertSame('AFP Habitat 12/2026', Auditoria::where('auditable_type', 'tasa_afp')->sole()->descripcion);

        $concepto = Auditoria::sinRegistrar(fn () => Concepto::create([
            'codigo_plame' => '0304', 'nombre' => 'Bono', 'tipo' => TipoConcepto::Ingreso,
            'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
            'sistema' => false, 'activo' => true,
        ]));
        $this->put(route('conceptos.update', $concepto), [
            'nombre' => 'Bono', 'afecto_essalud' => true, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
        ]);
        $this->assertSame(['afecto_essalud' => [false, true]], Auditoria::where('auditable_type', 'concepto')->sole()->cambios);
    }

    #[Test]
    public function trabajador_y_novedad_registran_su_empresa(): void
    {
        $empresa = Auditoria::sinRegistrar(fn () => Empresa::factory()->create());
        $this->actingAs($this->admin);

        $trabajador = Trabajador::factory()->for($empresa)->create(['apellido_paterno' => 'Quispe', 'nombres' => 'Rosa']);
        $periodo = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 11]);
        $concepto = Auditoria::sinRegistrar(fn () => Concepto::create([
            'codigo_plame' => '0701', 'nombre' => 'Adelanto', 'tipo' => TipoConcepto::DescuentoTrabajador,
            'afecto_essalud' => false, 'afecto_onp' => false, 'afecto_afp' => false, 'afecto_quinta' => false,
            'sistema' => false, 'activo' => true,
        ]));
        $periodo->lineas()->create(['trabajador_id' => $trabajador->id])
            ->novedades()->create(['concepto_id' => $concepto->id, 'monto' => '200.00']);

        $this->assertSame($empresa->id, Auditoria::where('auditable_type', 'trabajador')->sole()->empresa_id);
        $novedad = Auditoria::where('auditable_type', 'novedad')->sole();
        $this->assertSame($empresa->id, $novedad->empresa_id);
        $this->assertSame([null, '200.00'], $novedad->cambios['monto']);
        $this->assertStringContainsString('0701 Adelanto', (string) $novedad->descripcion);
    }

    #[Test]
    public function acciones_especiales_como_cerrar_un_periodo(): void
    {
        $periodo = Auditoria::sinRegistrar(fn () => Periodo::factory()->create(['anio' => 2026, 'mes' => 10]));
        $this->actingAs($this->admin);

        $periodo->registrarAuditoria('cerrado', ['estado' => ['calculado', 'cerrado']]);

        $fila = Auditoria::sole();
        $this->assertSame('cerrado', $fila->accion);
        $this->assertSame($periodo->empresa_id, $fila->empresa_id);
        $this->assertSame('Periodo 10/2026', $fila->descripcion);
    }

    #[Test]
    public function los_seeders_no_generan_auditoria(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Auditoria::count());
    }

    #[Test]
    public function la_auditoria_no_se_edita_ni_se_borra(): void
    {
        $this->actingAs($this->admin);
        $empresa = Empresa::factory()->create();
        $fila = Auditoria::sole();

        $this->assertFalse($fila->update(['accion' => 'otra']));
        $this->assertFalse($fila->delete());
        $this->assertSame('creado', $fila->refresh()->accion);

        // Si se elimina la empresa, la fila queda (sin el vínculo).
        Auditoria::sinRegistrar(fn () => $empresa->forceDelete());
        $this->assertSame(1, Auditoria::count());
    }
}
