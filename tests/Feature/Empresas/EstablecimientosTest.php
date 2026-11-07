<?php

namespace Tests\Feature\Empresas;

use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EstablecimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Empresa $empresa;

    private Establecimiento $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->empresa = Empresa::factory()->create();
        $this->principal = $this->empresa->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
    }

    #[Test]
    public function admin_agrega_un_establecimiento(): void
    {
        $this->actingAs($this->admin)
            ->post(route('empresas.establecimientos.store', $this->empresa), [
                'codigo' => '0001',
                'nombre' => 'Local Arequipa',
                'direccion' => 'Calle Ficticia 456',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('establecimientos', [
            'empresa_id' => $this->empresa->id,
            'codigo' => '0001',
            'nombre' => 'Local Arequipa',
        ]);
    }

    #[Test]
    public function el_codigo_es_de_4_digitos_y_unico_por_empresa(): void
    {
        $otra = Empresa::factory()->create();
        $otra->establecimientos()->create(['codigo' => '0001', 'nombre' => 'De otra empresa']);

        $this->actingAs($this->admin);

        $this->post(route('empresas.establecimientos.store', $this->empresa), ['codigo' => '1', 'nombre' => 'X'])
            ->assertSessionHasErrors('codigo');

        $this->post(route('empresas.establecimientos.store', $this->empresa), ['codigo' => '0000', 'nombre' => 'Repetido'])
            ->assertSessionHasErrors('codigo');

        // El mismo código en otra empresa sí está permitido.
        $this->post(route('empresas.establecimientos.store', $this->empresa), ['codigo' => '0001', 'nombre' => 'Propio'])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function admin_edita_un_establecimiento(): void
    {
        $local = $this->empresa->establecimientos()->create(['codigo' => '0001', 'nombre' => 'Antes']);

        $this->actingAs($this->admin)
            ->put(route('empresas.establecimientos.update', [$this->empresa, $local]), [
                'codigo' => '0002',
                'nombre' => 'Después',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Después', $local->refresh()->nombre);
        $this->assertSame('0002', $local->codigo);
    }

    #[Test]
    public function el_principal_mantiene_su_codigo_y_no_se_elimina(): void
    {
        $this->actingAs($this->admin);

        $this->put(route('empresas.establecimientos.update', [$this->empresa, $this->principal]), [
            'codigo' => '0009',
            'nombre' => 'Sede central',
        ])->assertSessionHasErrors(['codigo' => 'El establecimiento principal siempre tiene el código 0000.']);

        $this->put(route('empresas.establecimientos.update', [$this->empresa, $this->principal]), [
            'codigo' => '0000',
            'nombre' => 'Sede central',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Sede central', $this->principal->refresh()->nombre);

        $this->delete(route('empresas.establecimientos.destroy', [$this->empresa, $this->principal]))
            ->assertRedirect();
        $this->assertModelExists($this->principal);
    }

    #[Test]
    public function admin_elimina_un_establecimiento_adicional(): void
    {
        $local = $this->empresa->establecimientos()->create(['codigo' => '0001', 'nombre' => 'Temporal']);

        $this->actingAs($this->admin)
            ->delete(route('empresas.establecimientos.destroy', [$this->empresa, $local]))
            ->assertRedirect();

        $this->assertModelMissing($local);
    }

    #[Test]
    public function no_se_toca_un_establecimiento_desde_la_url_de_otra_empresa(): void
    {
        $otra = Empresa::factory()->create();
        $ajeno = $otra->establecimientos()->create(['codigo' => '0001', 'nombre' => 'Ajeno']);

        $this->actingAs($this->admin);

        $this->put(route('empresas.establecimientos.update', [$this->empresa, $ajeno]), [
            'codigo' => '0001',
            'nombre' => 'Hackeado',
        ])->assertNotFound();

        $this->delete(route('empresas.establecimientos.destroy', [$this->empresa, $ajeno]))->assertNotFound();

        $this->assertSame('Ajeno', $ajeno->refresh()->nombre);
    }

    #[Test]
    public function asistente_no_gestiona_establecimientos(): void
    {
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($this->empresa);

        $this->actingAs($asistente);

        $this->post(route('empresas.establecimientos.store', $this->empresa), ['codigo' => '0001', 'nombre' => 'X'])
            ->assertForbidden();
        $this->put(route('empresas.establecimientos.update', [$this->empresa, $this->principal]), ['codigo' => '0000', 'nombre' => 'X'])
            ->assertForbidden();
        $this->delete(route('empresas.establecimientos.destroy', [$this->empresa, $this->principal]))
            ->assertForbidden();
    }
}
