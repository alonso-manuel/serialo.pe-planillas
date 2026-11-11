<?php

namespace Tests\Feature\Empresas;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GestionEmpresasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return [
            'ruc' => '20000000001',
            'razon_social' => 'Comercial Ficticia S.A.C.',
            'nombre_comercial' => 'Ficticia',
            'direccion' => 'Av. Ficticia 123',
            'ubigeo' => '150101',
            'regimen_laboral' => 'general',
            'seguro_salud' => 'essalud',
            'representante_legal' => 'Representante Ficticio',
            ...$cambios,
        ];
    }

    #[Test]
    public function admin_ve_el_listado_de_empresas(): void
    {
        Empresa::factory()->create(['razon_social' => 'Beta S.A.C.']);
        Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.']);

        $this->actingAs($this->admin)
            ->get(route('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('empresas/Index')
                ->has('empresas.data', 2)
                ->where('empresas.data.0.razon_social', 'Alfa S.A.C.')
                ->where('empresas.data.0.trabajadores_count', 0)
                ->has('regimenes', 4)
            );
    }

    #[Test]
    public function listado_filtra_por_busqueda_regimen_y_estado(): void
    {
        Empresa::factory()->create(['razon_social' => 'Andes S.A.C.', 'ruc' => '20000000010']);
        Empresa::factory()->microempresa()->create(['razon_social' => 'Micro S.A.C.']);
        Empresa::factory()->inactiva()->create(['razon_social' => 'Cerrada S.A.C.']);

        $this->actingAs($this->admin);

        $this->get(route('empresas.index', ['buscar' => '20000000010']))
            ->assertInertia(fn (Assert $page) => $page->has('empresas.data', 1)->where('empresas.data.0.razon_social', 'Andes S.A.C.'));

        $this->get(route('empresas.index', ['buscar' => 'andes']))
            ->assertInertia(fn (Assert $page) => $page->has('empresas.data', 1));

        $this->get(route('empresas.index', ['regimen' => 'micro']))
            ->assertInertia(fn (Assert $page) => $page->has('empresas.data', 1)->where('empresas.data.0.razon_social', 'Micro S.A.C.'));

        $this->get(route('empresas.index', ['estado' => 'inactivas']))
            ->assertInertia(fn (Assert $page) => $page->has('empresas.data', 1)->where('empresas.data.0.razon_social', 'Cerrada S.A.C.'));
    }

    #[Test]
    public function admin_crea_empresa_con_establecimiento_principal(): void
    {
        $this->actingAs($this->admin)
            ->post(route('empresas.store'), $this->datos())
            ->assertSessionHasNoErrors();

        $empresa = Empresa::where('ruc', '20000000001')->firstOrFail();
        $this->assertSame(RegimenLaboral::General, $empresa->regimen_laboral);
        $this->assertTrue($empresa->activo);

        $principal = $empresa->establecimientos()->sole();
        $this->assertSame('0000', $principal->codigo);
        $this->assertSame('Principal', $principal->nombre);
        $this->assertSame('Av. Ficticia 123', $principal->direccion);
    }

    #[Test]
    public function tras_crear_va_a_la_edicion_para_agregar_establecimientos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('empresas.store'), $this->datos())
            ->assertRedirect(route('empresas.edit', Empresa::firstOrFail()));
    }

    #[Test]
    public function validaciones_al_crear(): void
    {
        Empresa::factory()->create(['ruc' => '20000000028']);

        $this->actingAs($this->admin);

        $this->post(route('empresas.store'), $this->datos(['ruc' => '20000000002']))
            ->assertSessionHasErrors(['ruc' => 'El RUC no es válido. Revisa los 11 dígitos.']);

        $this->post(route('empresas.store'), $this->datos(['ruc' => '20000000028']))
            ->assertSessionHasErrors('ruc');

        $this->post(route('empresas.store'), $this->datos([
            'razon_social' => '',
            'ubigeo' => '15',
            'regimen_laboral' => 'inexistente',
        ]))->assertSessionHasErrors(['razon_social', 'ubigeo', 'regimen_laboral']);
    }

    #[Test]
    public function el_sis_solo_se_permite_en_microempresa(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('empresas.store'), $this->datos(['regimen_laboral' => 'pequena', 'seguro_salud' => 'sis']))
            ->assertSessionHasErrors(['seguro_salud' => 'El SIS solo aplica a microempresas acogidas al REMYPE.']);

        $this->post(route('empresas.store'), $this->datos(['regimen_laboral' => 'micro', 'seguro_salud' => 'sis']))
            ->assertSessionHasNoErrors();

        $this->assertSame(SeguroSalud::Sis, Empresa::firstOrFail()->seguro_salud);
    }

    #[Test]
    public function admin_edita_empresa_pero_el_ruc_no_cambia(): void
    {
        $empresa = Empresa::factory()->create(['ruc' => '20000000001']);

        $this->actingAs($this->admin)
            ->get(route('empresas.edit', $empresa))
            ->assertInertia(fn (Assert $page) => $page
                ->component('empresas/Form')
                ->where('empresa.ruc', '20000000001')
                ->has('establecimientos')
            );

        $this->put(route('empresas.update', $empresa), $this->datos([
            'ruc' => '20000000028',
            'razon_social' => 'Nueva Razón S.A.C.',
            'regimen_laboral' => 'pequena',
        ]))->assertRedirect(route('empresas.index'))->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('20000000001', $empresa->ruc);
        $this->assertSame('Nueva Razón S.A.C.', $empresa->razon_social);
        $this->assertSame(RegimenLaboral::PequenaEmpresa, $empresa->regimen_laboral);
    }

    #[Test]
    public function admin_desactiva_y_reactiva_una_empresa(): void
    {
        $empresa = Empresa::factory()->create();

        $this->actingAs($this->admin)->patch(route('empresas.estado', $empresa))->assertRedirect();
        $this->assertFalse($empresa->refresh()->activo);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('empresas', 0)->where('empresaActiva', null));

        $this->patch(route('empresas.estado', $empresa));
        $this->assertTrue($empresa->refresh()->activo);
    }

    #[Test]
    public function las_empresas_no_se_eliminan(): void
    {
        $empresa = Empresa::factory()->create();

        $this->actingAs($this->admin)
            ->delete('/empresas/'.$empresa->id)
            ->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($empresa);
    }

    #[Test]
    public function asistente_no_puede_acceder_a_empresas_ni_a_las_suyas(): void
    {
        $propia = Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($propia);

        $this->actingAs($asistente);

        $this->get(route('empresas.index'))->assertForbidden();
        $this->get(route('empresas.create'))->assertForbidden();
        $this->post(route('empresas.store'), $this->datos())->assertForbidden();
        $this->get(route('empresas.edit', $propia))->assertForbidden();
        $this->put(route('empresas.update', $propia), $this->datos())->assertForbidden();
        $this->patch(route('empresas.estado', $propia))->assertForbidden();
        $this->get(route('empresas.consulta-ruc', '20000000001'))->assertForbidden();

        $this->assertDatabaseCount('empresas', 1);
    }
}
