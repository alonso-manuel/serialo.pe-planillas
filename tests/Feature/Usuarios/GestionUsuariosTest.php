<?php

namespace Tests\Feature\Usuarios;

use App\Enums\RolUsuario;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-Segura-2026';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
    }

    #[Test]
    public function admin_ve_el_listado_de_usuarios(): void
    {
        $asistente = User::factory()->asistente()->create(['name' => 'Beto Asistente']);
        $asistente->empresas()->attach(Empresa::factory()->count(2)->create());

        $this->actingAs($this->admin)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('usuarios/Index')
                ->has('usuarios.data', 2)
                ->where('usuarios.data.1.name', 'Beto Asistente')
                ->where('usuarios.data.1.rol', 'asistente')
                ->where('usuarios.data.1.empresas_count', 2)
                ->where('usuarios.data.0.es_actual', true)
            );
    }

    #[Test]
    public function listado_filtra_por_busqueda_rol_y_estado(): void
    {
        User::factory()->asistente()->create(['name' => 'Carla Ríos', 'email' => 'carla@ejemplo.pe']);
        User::factory()->asistente()->create(['name' => 'Diego Paz', 'activo' => false]);

        $this->actingAs($this->admin);

        $this->get(route('usuarios.index', ['buscar' => 'carla']))
            ->assertInertia(fn (Assert $page) => $page->has('usuarios.data', 1)->where('usuarios.data.0.name', 'Carla Ríos'));

        $this->get(route('usuarios.index', ['rol' => 'admin']))
            ->assertInertia(fn (Assert $page) => $page->has('usuarios.data', 1)->where('usuarios.data.0.name', 'Ana Admin'));

        $this->get(route('usuarios.index', ['estado' => 'inactivos']))
            ->assertInertia(fn (Assert $page) => $page->has('usuarios.data', 1)->where('usuarios.data.0.name', 'Diego Paz'));
    }

    #[Test]
    public function formulario_de_creacion_ofrece_solo_empresas_activas(): void
    {
        Empresa::factory()->create();
        Empresa::factory()->inactiva()->create();

        $this->actingAs($this->admin)
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('usuarios/Form')
                ->where('usuario', null)
                ->has('empresas', 1)
                ->has('roles', 2)
            );
    }

    #[Test]
    public function admin_crea_asistente_con_empresas_asignadas(): void
    {
        $empresas = Empresa::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), [
                'name' => 'Elena Torres',
                'email' => 'elena@ejemplo.pe',
                'rol' => 'asistente',
                'password' => self::CLAVE,
                'empresas' => $empresas->pluck('id')->all(),
            ])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $nuevo = User::where('email', 'elena@ejemplo.pe')->firstOrFail();
        $this->assertSame(RolUsuario::Asistente, $nuevo->rol);
        $this->assertTrue($nuevo->activo);
        $this->assertNotNull($nuevo->email_verified_at, 'Creado por el admin = correo verificado.');
        $this->assertTrue(Hash::check(self::CLAVE, $nuevo->password));
        $this->assertEqualsCanonicalizing($empresas->pluck('id')->all(), $nuevo->empresas()->pluck('empresas.id')->all());
    }

    #[Test]
    public function admin_creado_no_guarda_empresas_porque_ve_todas(): void
    {
        $empresa = Empresa::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), [
                'name' => 'Fernando Admin',
                'email' => 'fernando@ejemplo.pe',
                'rol' => 'admin',
                'password' => self::CLAVE,
                'empresas' => [$empresa->id],
            ])
            ->assertRedirect(route('usuarios.index'));

        $nuevo = User::where('email', 'fernando@ejemplo.pe')->firstOrFail();
        $this->assertSame(RolUsuario::Admin, $nuevo->rol);
        $this->assertSame(0, $nuevo->empresas()->count());
    }

    #[Test]
    public function validaciones_al_crear(): void
    {
        $inactiva = Empresa::factory()->inactiva()->create();

        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), [
                'name' => '',
                'email' => $this->admin->email,
                'rol' => 'jefe',
                'password' => '123',
                'empresas' => [$inactiva->id],
            ])
            ->assertSessionHasErrors(['name', 'email', 'rol', 'password', 'empresas.0']);

        $this->post(route('usuarios.store'), [
            'name' => 'Sin Clave',
            'email' => 'sinclave@ejemplo.pe',
            'rol' => 'asistente',
        ])->assertSessionHasErrors('password');
    }

    #[Test]
    public function mensajes_de_validacion_en_espanol(): void
    {
        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), ['email' => 'no-es-correo'])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
            ]);
    }

    #[Test]
    public function admin_edita_usuario_sin_cambiar_su_contrasena(): void
    {
        $asistente = User::factory()->asistente()->create(['password' => self::CLAVE]);
        $asistente->empresas()->attach($vieja = Empresa::factory()->create());
        $nueva = Empresa::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('usuarios.edit', $asistente))
            ->assertInertia(fn (Assert $page) => $page
                ->component('usuarios/Form')
                ->where('usuario.id', $asistente->id)
                ->where('usuario.empresas', [$vieja->id])
                ->missing('usuario.password')
            );

        $this->put(route('usuarios.update', $asistente), [
            'name' => 'Nombre Nuevo',
            'email' => $asistente->email,
            'rol' => 'asistente',
            'password' => '',
            'empresas' => [$nueva->id],
        ])->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors();

        $asistente->refresh();
        $this->assertSame('Nombre Nuevo', $asistente->name);
        $this->assertTrue(Hash::check(self::CLAVE, $asistente->password));
        $this->assertSame([$nueva->id], $asistente->empresas()->pluck('empresas.id')->all());
    }

    #[Test]
    public function admin_cambia_la_contrasena_de_un_usuario(): void
    {
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($this->admin)
            ->put(route('usuarios.update', $asistente), [
                'name' => $asistente->name,
                'email' => $asistente->email,
                'rol' => 'asistente',
                'password' => 'Otra-Clave-2026',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Otra-Clave-2026', $asistente->refresh()->password));
    }

    #[Test]
    public function ascender_a_admin_limpia_las_empresas_asignadas(): void
    {
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($this->admin)
            ->put(route('usuarios.update', $asistente), [
                'name' => $asistente->name,
                'email' => $asistente->email,
                'rol' => 'admin',
            ])->assertSessionHasNoErrors();

        $this->assertSame(RolUsuario::Admin, $asistente->refresh()->rol);
        $this->assertSame(0, $asistente->empresas()->count());
    }

    #[Test]
    public function admin_no_puede_quitarse_el_rol_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->put(route('usuarios.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'rol' => 'asistente',
            ])->assertSessionHasErrors('rol');

        $this->assertSame(RolUsuario::Admin, $this->admin->refresh()->rol);
    }

    #[Test]
    public function asistente_no_puede_acceder_a_usuarios(): void
    {
        $asistente = User::factory()->asistente()->create();
        $otro = User::factory()->asistente()->create();

        $this->actingAs($asistente);

        $this->get(route('usuarios.index'))->assertForbidden();
        $this->get(route('usuarios.create'))->assertForbidden();
        $this->post(route('usuarios.store'), [])->assertForbidden();
        $this->get(route('usuarios.edit', $otro))->assertForbidden();
        $this->put(route('usuarios.update', $otro), [])->assertForbidden();
        $this->patch(route('usuarios.estado', $otro))->assertForbidden();
    }

    #[Test]
    public function invitado_es_enviado_al_login(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function los_usuarios_no_se_eliminan(): void
    {
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($this->admin)
            ->delete('/usuarios/'.$asistente->id)
            ->assertMethodNotAllowed();

        $this->assertModelExists($asistente);
    }
}
