<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Afp;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trabajadores de la empresa activa. Todos los datos personales son ficticios (Ley 29733). */
class TrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Empresa $otra;

    private User $asistente;

    private Afp $afp;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->empresa = Empresa::factory()->create(['razon_social' => 'Propia S.A.C.']);
        $this->empresa->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
        $this->otra = Empresa::factory()->create(['razon_social' => 'Ajena S.A.C.']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->afp = Afp::create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'activo' => true]);
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo_documento' => '01',
            'numero_documento' => '70000001',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Mamani',
            'nombres' => 'Rosa Elena',
            'fecha_nacimiento' => '1995-03-10',
            'sexo' => 'F',
            'email' => null,
            'telefono' => null,
            'establecimiento_id' => $this->empresa->establecimientos()->first()->id,
            'cargo' => 'Asistente contable',
            'fecha_ingreso' => '2026-01-02',
            'tipo_contrato' => 'indeterminado',
            'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => true,
            'sistema_pension' => 'onp',
            'afp_id' => null,
            'cuspp' => null,
            'tipo_comision_afp' => null,
            'seguro_salud' => 'essalud',
            'cuenta_sueldo' => '191-12345678-0-11',
            'cuenta_cts' => null,
            ...$cambios,
        ];
    }

    #[Test]
    public function lista_solo_los_trabajadores_de_la_empresa_activa(): void
    {
        Trabajador::factory()->for($this->empresa)->create(['apellido_paterno' => 'Torres']);
        Trabajador::factory()->for($this->empresa)->create(['apellido_paterno' => 'Alva']);
        Trabajador::factory()->for($this->otra)->create(['apellido_paterno' => 'Ajeno']);

        $this->actingAs($this->asistente)
            ->get(route('trabajadores.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('trabajadores/Index')
                ->has('trabajadores.data', 2)
                ->where('trabajadores.data.0.apellido_paterno', 'Alva')
                ->where('trabajadores.data.0.documento', fn ($d) => str_starts_with($d, 'DNI '))
                ->where('trabajadores.data.0.cesado', false)
            );
    }

    #[Test]
    public function filtra_por_busqueda_estado_pension_y_establecimiento(): void
    {
        $local = $this->empresa->establecimientos()->create(['codigo' => '0001', 'nombre' => 'Local Norte']);
        Trabajador::factory()->for($this->empresa)->create(['apellido_paterno' => 'Quispe', 'numero_documento' => '70000011']);
        Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => 'Rojas', 'sistema_pension' => SistemaPension::Afp, 'establecimiento_id' => $local->id,
        ]);
        Trabajador::factory()->for($this->empresa)->create(['apellido_paterno' => 'Cesado', 'fecha_cese' => '2026-10-31']);

        $this->actingAs($this->asistente);

        $this->get(route('trabajadores.index', ['buscar' => '70000011']))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 1)->where('trabajadores.data.0.apellido_paterno', 'Quispe'));
        $this->get(route('trabajadores.index', ['buscar' => 'roj']))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 1));
        $this->get(route('trabajadores.index'))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 2), 'Por defecto, solo activos.');
        $this->get(route('trabajadores.index', ['estado' => 'cesados']))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 1)->where('trabajadores.data.0.cesado', true));
        $this->get(route('trabajadores.index', ['estado' => 'todos']))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 3));
        $this->get(route('trabajadores.index', ['pension' => 'afp']))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 1)->where('trabajadores.data.0.apellido_paterno', 'Rojas'));
        $this->get(route('trabajadores.index', ['establecimiento' => $local->id]))
            ->assertInertia(fn (Assert $p) => $p->has('trabajadores.data', 1));
    }

    #[Test]
    public function la_ficha_ofrece_los_catalogos_de_la_empresa(): void
    {
        $this->actingAs($this->asistente)
            ->get(route('trabajadores.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('trabajadores/Form')
                ->where('trabajador', null)
                ->has('catalogos.establecimientos', 1)
                ->has('catalogos.afps', 1)
                ->has('catalogos.tipos_documento', 3)
                ->has('catalogos.tipos_contrato', 3)
                ->where('catalogos.seguro_por_defecto', 'essalud')
                ->where('catalogos.permite_sis', false)
            );
    }

    #[Test]
    public function crea_un_trabajador_onp_en_la_empresa_activa(): void
    {
        $this->actingAs($this->asistente)
            ->post(route('trabajadores.store'), $this->datos(['empresa_id' => $this->otra->id]))
            ->assertRedirect(route('trabajadores.index'))
            ->assertSessionHasNoErrors();

        $t = Trabajador::sole();
        $this->assertSame($this->empresa->id, $t->empresa_id, 'Se ignora empresa_id del formulario.');
        $this->assertSame(TipoDocumento::Dni, $t->tipo_documento);
        $this->assertSame(TipoContrato::Indeterminado, $t->tipo_contrato);
        $this->assertSame('1500.00', $t->remuneracion_basica);
        $this->assertSame(SistemaPension::Onp, $t->sistema_pension);
        $this->assertTrue($t->asignacion_familiar);
        $this->assertTrue(Auditoria::where('auditable_type', 'trabajador')->where('accion', 'creado')->exists());
    }

    #[Test]
    public function un_trabajador_afp_exige_afp_cuspp_y_comision(): void
    {
        $this->actingAs($this->asistente);

        $this->post(route('trabajadores.store'), $this->datos(['sistema_pension' => 'afp']))
            ->assertSessionHasErrors(['afp_id', 'cuspp', 'tipo_comision_afp']);

        $this->post(route('trabajadores.store'), $this->datos([
            'sistema_pension' => 'afp', 'afp_id' => $this->afp->id, 'cuspp' => 'abc123def456', 'tipo_comision_afp' => 'mixta',
        ]))->assertSessionHasNoErrors();

        $t = Trabajador::sole();
        $this->assertSame('ABC123DEF456', $t->cuspp, 'CUSPP en mayúsculas.');
        $this->assertSame(TipoComisionAfp::Mixta, $t->tipo_comision_afp);
    }

    #[Test]
    public function al_pasar_a_onp_se_limpian_los_datos_de_afp(): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'sistema_pension' => SistemaPension::Afp, 'afp_id' => $this->afp->id,
            'cuspp' => 'ABC123DEF456', 'tipo_comision_afp' => TipoComisionAfp::Flujo,
        ]);

        $this->actingAs($this->asistente)
            ->put(route('trabajadores.update', $t), $this->datos([
                'numero_documento' => $t->numero_documento,
                'sistema_pension' => 'onp', 'afp_id' => $this->afp->id, 'cuspp' => 'ABC123DEF456', 'tipo_comision_afp' => 'flujo',
            ]))
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame(SistemaPension::Onp, $t->sistema_pension);
        $this->assertNull($t->afp_id);
        $this->assertNull($t->cuspp);
        $this->assertNull($t->tipo_comision_afp);
    }

    #[Test]
    public function validaciones_de_la_ficha(): void
    {
        Trabajador::factory()->for($this->empresa)->create(['numero_documento' => '70000099']);
        $establecimientoAjeno = $this->otra->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Ajeno']);
        $this->actingAs($this->asistente);

        $this->post(route('trabajadores.store'), $this->datos(['numero_documento' => '1234']))
            ->assertSessionHasErrors(['numero_documento' => 'El DNI debe tener 8 dígitos.']);

        $this->post(route('trabajadores.store'), $this->datos(['numero_documento' => '70000099']))
            ->assertSessionHasErrors(['numero_documento' => 'Ya existe un trabajador con ese documento en esta empresa.']);

        $this->post(route('trabajadores.store'), $this->datos([
            'apellido_paterno' => '',
            'nombres' => '',
            'fecha_ingreso' => '',
            'remuneracion_basica' => '1500.555',
            'establecimiento_id' => $establecimientoAjeno->id,
            'tipo_contrato' => 'locacion',
            'sexo' => 'X',
            'fecha_nacimiento' => '2030-01-01',
        ]))->assertSessionHasErrors([
            'apellido_paterno', 'nombres', 'fecha_ingreso', 'remuneracion_basica',
            'establecimiento_id', 'tipo_contrato', 'sexo', 'fecha_nacimiento',
        ]);

        // El mismo DNI en otra empresa sí se permite.
        Trabajador::factory()->for($this->otra)->create(['numero_documento' => '70000123']);
        $this->post(route('trabajadores.store'), $this->datos(['numero_documento' => '70000123']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function carne_de_extranjeria_y_pasaporte_aceptan_letras(): void
    {
        $this->actingAs($this->asistente)
            ->post(route('trabajadores.store'), $this->datos(['tipo_documento' => '04', 'numero_documento' => 'ce0012345']))
            ->assertSessionHasNoErrors();

        $this->assertSame('CE0012345', Trabajador::sole()->numero_documento);
    }

    #[Test]
    public function el_sis_solo_se_permite_si_la_empresa_es_microempresa(): void
    {
        $this->actingAs($this->asistente)
            ->post(route('trabajadores.store'), $this->datos(['seguro_salud' => 'sis']))
            ->assertSessionHasErrors(['seguro_salud' => 'El SIS solo aplica a trabajadores de microempresas.']);

        $this->empresa->update(['regimen_laboral' => 'micro', 'seguro_salud' => 'sis']);
        $this->post(route('trabajadores.store'), $this->datos(['seguro_salud' => 'sis']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function edita_un_trabajador(): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create(['numero_documento' => '70000001']);

        $this->actingAs($this->asistente)
            ->get(route('trabajadores.edit', $t))
            ->assertInertia(fn (Assert $page) => $page
                ->component('trabajadores/Form')
                ->where('trabajador.id', $t->id)
                ->where('trabajador.numero_documento', '70000001')
                ->where('trabajador.en_planilla', false)
            );

        $this->put(route('trabajadores.update', $t), $this->datos(['cargo' => 'Contador', 'remuneracion_basica' => '2000']))
            ->assertRedirect(route('trabajadores.index'))
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('Contador', $t->cargo);
        $this->assertSame('2000.00', $t->remuneracion_basica);
    }

    #[Test]
    public function elimina_solo_si_nunca_estuvo_en_planilla(): void
    {
        $nuevo = Trabajador::factory()->for($this->empresa)->create();
        $conPlanilla = Trabajador::factory()->for($this->empresa)->create();
        Periodo::factory()->for($this->empresa)->create()->lineas()->create(['trabajador_id' => $conPlanilla->id]);

        $this->actingAs($this->asistente);

        $this->delete(route('trabajadores.destroy', $nuevo))->assertRedirect();
        $this->assertNull(Trabajador::withTrashed()->find($nuevo->id), 'Se elimina del todo para poder volver a registrarlo.');

        $this->delete(route('trabajadores.destroy', $conPlanilla))->assertRedirect();
        $this->assertModelExists($conPlanilla);
    }

    #[Test]
    public function asistente_no_ve_ni_edita_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for($this->otra)->create();
        $this->actingAs($this->asistente);

        $this->get(route('trabajadores.edit', $ajeno))->assertForbidden();
        $this->put(route('trabajadores.update', $ajeno), $this->datos())->assertForbidden();
        $this->delete(route('trabajadores.destroy', $ajeno))->assertForbidden();

        $this->assertModelExists($ajeno);
    }

    #[Test]
    public function sin_empresa_activa_vuelve_al_panel(): void
    {
        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('trabajadores.index'))
            ->assertRedirect(route('dashboard'));
    }
}
