<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otro;

    private Empresa $andes;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->admin, $this->otro] = Auditoria::sinRegistrar(fn () => [
            User::factory()->admin()->create(['name' => 'Ana Admin']),
            User::factory()->admin()->create(['name' => 'Otro Admin']),
        ]);

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->actingAs($this->admin);
        $this->andes = Empresa::factory()->create(['razon_social' => 'Andes S.A.C.']);

        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->actingAs($this->otro);
        $this->andes->update(['regimen_laboral' => 'pequena']);
        Empresa::factory()->create(['razon_social' => 'Pacífico S.A.C.']);
        Carbon::setTestNow();
    }

    #[Test]
    public function admin_ve_el_historial_con_lo_mas_reciente_primero(): void
    {
        $this->actingAs($this->admin)
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auditoria/Index')
                ->has('registros.data', 3)
                ->where('registros.data.0.descripcion', fn ($d) => str_starts_with($d, 'Pacífico S.A.C.'))
                ->where('registros.data.1.accion', 'actualizado')
                ->where('registros.data.1.accion_etiqueta', 'Actualizado')
                ->where('registros.data.1.tipo_etiqueta', 'Empresa')
                ->where('registros.data.1.usuario', 'Otro Admin')
                ->where('registros.data.1.cambios.0.campo', 'Régimen laboral')
                ->where('registros.data.1.cambios.0.antes', 'general')
                ->where('registros.data.1.cambios.0.despues', 'pequena')
                ->has('opciones.usuarios', 2)
                ->has('opciones.tipos')
            );
    }

    #[Test]
    public function filtra_por_empresa_usuario_tipo_accion_y_fechas(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('auditoria.index', ['empresa' => $this->andes->id]))
            ->assertInertia(fn (Assert $page) => $page->has('registros.data', 2));

        $this->get(route('auditoria.index', ['usuario' => $this->admin->id]))
            ->assertInertia(fn (Assert $page) => $page->has('registros.data', 1));

        $this->get(route('auditoria.index', ['tipo' => 'empresa', 'accion' => 'actualizado']))
            ->assertInertia(fn (Assert $page) => $page->has('registros.data', 1));

        $this->get(route('auditoria.index', ['desde' => '2026-11-11', 'hasta' => '2026-11-12']))
            ->assertInertia(fn (Assert $page) => $page->has('registros.data', 2));

        $this->get(route('auditoria.index', ['hasta' => '2026-11-10']))
            ->assertInertia(fn (Assert $page) => $page->has('registros.data', 1));
    }

    #[Test]
    public function asistente_no_ve_la_auditoria(): void
    {
        $this->actingAs(Auditoria::sinRegistrar(fn () => User::factory()->asistente()->create()))
            ->get(route('auditoria.index'))
            ->assertForbidden();
    }
}
