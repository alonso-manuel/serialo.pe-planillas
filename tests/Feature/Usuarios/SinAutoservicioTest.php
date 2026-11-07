<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los usuarios solo los crea un administrador: no hay registro público
 * ni opción de eliminar la propia cuenta.
 */
class SinAutoservicioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@ejemplo.pe',
            'password' => 'Clave-Segura-2026',
            'password_confirmation' => 'Clave-Segura-2026',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@ejemplo.pe']);
    }

    #[Test]
    public function usuario_no_puede_eliminar_su_propia_cuenta(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertModelExists($usuario);
    }

    #[Test]
    public function la_raiz_lleva_al_login_o_al_panel(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
