<?php

namespace Tests\Feature\Multiempresa;

use App\Http\Middleware\RequiereEmpresaActiva;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regla 4 de CLAUDE.md: un asistente nunca ve datos de una empresa que no tiene asignada.
 * Estas pruebas cubren la base común; cada módulo agrega la suya con sus rutas.
 */
class AislamientoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $propia;

    private Empresa $ajena;

    private User $asistente;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propia = Empresa::factory()->create(['razon_social' => 'Propia S.A.C.']);
        $this->ajena = Empresa::factory()->create(['razon_social' => 'Ajena S.A.C.']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->propia);
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function asistente_no_ve_empresa_ajena(): void
    {
        $this->actingAs($this->asistente)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.id', $this->propia->id)
                ->where('empresaActiva.id', $this->propia->id)
            );

        $this->put(route('empresa-activa.update'), ['empresa_id' => $this->ajena->id])->assertForbidden();

        $this->assertTrue($this->asistente->puedeVerEmpresa($this->propia));
        $this->assertFalse($this->asistente->puedeVerEmpresa($this->ajena));
        $this->assertTrue($this->asistente->puedeVerEmpresaId($this->propia->id));
        $this->assertFalse($this->asistente->puedeVerEmpresaId($this->ajena->id));
    }

    #[Test]
    public function admin_ve_todas_las_empresas(): void
    {
        $this->assertTrue($this->admin->puedeVerEmpresaId($this->propia->id));
        $this->assertTrue($this->admin->puedeVerEmpresaId($this->ajena->id));
    }

    #[Test]
    public function al_quitarle_la_empresa_deja_de_verla(): void
    {
        $this->actingAs($this->asistente);
        $this->assertTrue($this->asistente->can('view', Trabajador::factory()->for($this->propia)->create()));

        $this->asistente->empresas()->detach($this->propia);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('empresas', 0)->where('empresaActiva', null));
        $this->assertFalse($this->asistente->puedeVerEmpresaId($this->propia->id));
    }

    #[Test]
    public function politica_de_trabajadores_respeta_la_empresa(): void
    {
        $propio = Trabajador::factory()->for($this->propia)->create();
        $ajeno = Trabajador::factory()->for($this->ajena)->create();

        foreach (['view', 'update', 'delete'] as $accion) {
            $this->assertTrue($this->asistente->can($accion, $propio), "asistente {$accion} propio");
            $this->assertFalse($this->asistente->can($accion, $ajeno), "asistente {$accion} ajeno");
            $this->assertTrue($this->admin->can($accion, $ajeno), "admin {$accion} ajeno");
        }
    }

    #[Test]
    public function politica_de_periodos_respeta_la_empresa_y_el_cierre(): void
    {
        $propio = Periodo::factory()->for($this->propia)->create();
        $ajeno = Periodo::factory()->for($this->ajena)->create();
        $cerrado = Periodo::factory()->for($this->propia)->cerrado()->create();

        $this->assertTrue($this->asistente->can('view', $propio));
        $this->assertTrue($this->asistente->can('update', $propio));
        $this->assertFalse($this->asistente->can('view', $ajeno));
        $this->assertFalse($this->asistente->can('update', $ajeno));

        // Regla 5: periodo cerrado = inmutable, también para el admin.
        $this->assertTrue($this->asistente->can('view', $cerrado));
        $this->assertFalse($this->asistente->can('update', $cerrado));
        $this->assertFalse($this->admin->can('update', $cerrado));

        // Solo el admin cierra y reabre.
        $this->assertFalse($this->asistente->can('cerrar', $propio));
        $this->assertTrue($this->admin->can('cerrar', $propio));
        $this->assertFalse($this->admin->can('cerrar', $cerrado));
        $this->assertFalse($this->asistente->can('reabrir', $cerrado));
        $this->assertTrue($this->admin->can('reabrir', $cerrado));
        $this->assertFalse($this->admin->can('reabrir', $propio));
    }

    #[Test]
    public function scope_visibles_para_filtra_por_empresas_del_usuario(): void
    {
        $propio = Trabajador::factory()->for($this->propia)->create();
        Trabajador::factory()->for($this->ajena)->create();

        $this->assertSame([$propio->id], Trabajador::query()->visiblesPara($this->asistente)->pluck('id')->all());
        $this->assertSame(2, Trabajador::query()->visiblesPara($this->admin)->count());
        $this->assertSame([$propio->id], Trabajador::query()->deEmpresa($this->propia->id)->pluck('id')->all());
    }

    #[Test]
    public function rutas_de_trabajo_exigen_una_empresa_activa(): void
    {
        Route::middleware(['web', 'auth', RequiereEmpresaActiva::class])
            ->get('/_prueba/trabajo', fn () => 'ok');

        $this->actingAs($this->asistente)->get('/_prueba/trabajo')->assertOk()->assertSee('ok');

        $sinEmpresas = User::factory()->asistente()->create();
        $this->actingAs($sinEmpresas)
            ->get('/_prueba/trabajo')
            ->assertRedirect(route('dashboard'));
    }
}
