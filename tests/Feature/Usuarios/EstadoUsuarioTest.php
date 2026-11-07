<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EstadoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_desactiva_y_reactiva_a_un_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($admin)
            ->from(route('usuarios.index'))
            ->patch(route('usuarios.estado', $asistente))
            ->assertRedirect(route('usuarios.index'));
        $this->assertFalse($asistente->refresh()->activo);

        $this->patch(route('usuarios.estado', $asistente));
        $this->assertTrue($asistente->refresh()->activo);
    }

    #[Test]
    public function admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('usuarios.estado', $admin))
            ->assertRedirect();

        $this->assertTrue($admin->refresh()->activo);
    }

    #[Test]
    public function usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $inactivo = User::factory()->asistente()->create(['activo' => false]);

        $this->post(route('login.store'), [
            'email' => $inactivo->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Contacta al administrador.']);

        $this->assertGuest();
    }

    #[Test]
    public function usuario_activo_inicia_sesion_normalmente(): void
    {
        $activo = User::factory()->asistente()->create();

        $this->post(route('login.store'), [
            'email' => $activo->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($activo);
    }

    #[Test]
    public function credenciales_incorrectas_muestran_mensaje_en_espanol(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);
    }

    #[Test]
    public function usuario_desactivado_pierde_la_sesion_abierta(): void
    {
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente)->get(route('dashboard'))->assertOk();

        $asistente->update(['activo' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
