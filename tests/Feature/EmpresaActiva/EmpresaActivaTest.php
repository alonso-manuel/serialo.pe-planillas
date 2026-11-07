<?php

namespace Tests\Feature\EmpresaActiva;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmpresaActivaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function primera_empresa_visible_por_razon_social_es_la_activa_por_defecto(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->create(['razon_social' => 'Zeta S.A.C.']);
        $alfa = Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresaActiva.id', $alfa->id)
                ->where('empresaActiva.razon_social', 'Alfa S.A.C.')
                ->has('empresas', 2)
            );
    }

    #[Test]
    public function admin_puede_activar_cualquier_empresa(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.']);
        $beta = Empresa::factory()->create(['razon_social' => 'Beta S.A.C.']);

        $this->actingAs($admin)
            ->from(route('dashboard'))
            ->put(route('empresa-activa.update'), ['empresa_id' => $beta->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('empresa_id', $beta->id);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('empresaActiva.id', $beta->id));
    }

    #[Test]
    public function asistente_puede_activar_empresa_asignada(): void
    {
        $empresa = Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($empresa);

        $this->actingAs($asistente)
            ->put(route('empresa-activa.update'), ['empresa_id' => $empresa->id])
            ->assertRedirect()
            ->assertSessionHas('empresa_id', $empresa->id);
    }

    #[Test]
    public function asistente_no_puede_activar_empresa_ajena(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($propia);

        $this->actingAs($asistente)
            ->withSession(['empresa_id' => $propia->id])
            ->put(route('empresa-activa.update'), ['empresa_id' => $ajena->id])
            ->assertForbidden();

        $this->assertSame($propia->id, session('empresa_id'));
    }

    #[Test]
    public function asistente_solo_recibe_sus_empresas_en_props_compartidas(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($propia);

        $this->actingAs($asistente)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.id', $propia->id)
                ->where('empresaActiva.id', $propia->id)
            );
    }

    #[Test]
    public function asistente_no_hereda_empresa_ajena_guardada_en_sesion(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($propia);

        $this->actingAs($asistente)
            ->withSession(['empresa_id' => $ajena->id])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('empresaActiva.id', $propia->id));
    }

    #[Test]
    public function si_pierde_acceso_a_la_empresa_activa_pasa_a_otra_visible(): void
    {
        $alfa = Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.']);
        $beta = Empresa::factory()->create(['razon_social' => 'Beta S.A.C.']);
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach([$alfa->id, $beta->id]);

        $this->actingAs($asistente)->withSession(['empresa_id' => $beta->id]);
        $asistente->empresas()->detach($beta);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('empresaActiva.id', $alfa->id));
    }

    #[Test]
    public function empresas_inactivas_no_aparecen_ni_se_pueden_activar(): void
    {
        $admin = User::factory()->admin()->create();
        $activa = Empresa::factory()->create();
        $inactiva = Empresa::factory()->inactiva()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.id', $activa->id)
            );

        $this->put(route('empresa-activa.update'), ['empresa_id' => $inactiva->id])
            ->assertSessionHasErrors('empresa_id');
    }

    #[Test]
    public function empresas_eliminadas_no_aparecen(): void
    {
        $admin = User::factory()->admin()->create();
        Empresa::factory()->create()->delete();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 0)
                ->where('empresaActiva', null)
            );
    }

    #[Test]
    public function usuario_sin_empresas_recibe_empresa_activa_nula(): void
    {
        Empresa::factory()->create();
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('empresaActiva', null)
                ->has('empresas', 0)
            );
    }

    #[Test]
    public function empresa_id_es_obligatorio_y_debe_existir(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('empresa-activa.update'), [])
            ->assertSessionHasErrors('empresa_id');

        $this->put(route('empresa-activa.update'), ['empresa_id' => 999])
            ->assertSessionHasErrors('empresa_id');
    }

    #[Test]
    public function invitado_no_puede_cambiar_empresa(): void
    {
        $empresa = Empresa::factory()->create();

        $this->put(route('empresa-activa.update'), ['empresa_id' => $empresa->id])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function comparte_si_el_usuario_es_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.esAdmin', true));

        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.esAdmin', false));
    }
}
