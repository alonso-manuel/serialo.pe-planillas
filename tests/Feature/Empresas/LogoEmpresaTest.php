<?php

namespace Tests\Feature\Empresas;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\LogoEmpresa;
use App\Services\Planilla\BoletaPago;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Logo de la empresa: subir, reducir, quitar, permisos y uso en los PDF. */
class LogoEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->empresa = Empresa::factory()->create();
    }

    #[Test]
    public function sube_el_logo_y_lo_reduce_a_600_por_200_en_png(): void
    {
        $this->actingAs($this->admin)
            ->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->image('logo.jpg', 1200, 300)])
            ->assertRedirect()->assertSessionHasNoErrors();

        $ruta = $this->empresa->refresh()->logo_path;
        $this->assertNotNull($ruta);
        Storage::disk('local')->assertExists($ruta);
        [$ancho, $alto, $tipo] = getimagesizefromstring(Storage::disk('local')->get($ruta));
        $this->assertSame([600, 150, IMAGETYPE_PNG], [$ancho, $alto, $tipo]);

        // Queda en la auditoría y la ficha indica que tiene logo.
        $this->assertTrue(Auditoria::query()->where('auditable_type', 'empresa')->where('accion', 'actualizado')->exists());
        $this->get(route('empresas.edit', $this->empresa))->assertInertia(fn (Assert $page) => $page->whereNot('empresa.logo', null));
        $this->get(route('empresas.logo.show', $this->empresa))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    #[Test]
    public function al_cambiarlo_borra_el_anterior_y_se_puede_quitar(): void
    {
        $this->actingAs($this->admin)->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->image('a.png', 300, 100)]);
        $primero = $this->empresa->refresh()->logo_path;

        $this->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->image('b.png', 300, 100)]);
        Storage::disk('local')->assertMissing($primero);

        $segundo = $this->empresa->refresh()->logo_path;
        $this->delete(route('empresas.logo.destroy', $this->empresa))->assertRedirect();
        $this->assertNull($this->empresa->refresh()->logo_path);
        Storage::disk('local')->assertMissing($segundo);
        $this->get(route('empresas.logo.show', $this->empresa))->assertNotFound();
    }

    #[Test]
    public function rechaza_archivos_que_no_son_imagen_o_pesan_mas_de_1_mb(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('logo');
        $this->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->image('grande.png')->size(1500)])
            ->assertSessionHasErrors(['logo' => 'El logo no puede pesar más de 1 MB.']);

        $this->assertNull($this->empresa->refresh()->logo_path);
    }

    #[Test]
    public function el_asistente_no_cambia_ni_ve_el_logo_desde_empresas(): void
    {
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($this->empresa);

        $this->actingAs($asistente);
        $this->post(route('empresas.logo.store', $this->empresa), ['logo' => UploadedFile::fake()->image('logo.png', 300, 100)])->assertForbidden();
        $this->delete(route('empresas.logo.destroy', $this->empresa))->assertForbidden();
        $this->get(route('empresas.logo.show', $this->empresa))->assertForbidden();
    }

    #[Test]
    public function la_boleta_lleva_el_logo_cuando_existe(): void
    {
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
        $t = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2025-01-01', 'sistema_pension' => 'onp', 'afp_id' => null]);
        $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->admin)->post(route('periodos.calcular', $periodo));
        $boletas = app(BoletaPago::class);

        $this->assertNull($boletas->vista($periodo->refresh(), $boletas->lineas($periodo))['logo']);

        app(LogoEmpresa::class)->guardar($this->empresa, UploadedFile::fake()->image('logo.png', 300, 100));
        $vista = $boletas->vista($periodo->refresh(), $boletas->lineas($periodo));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $vista['logo']);
        $this->assertStringStartsWith('%PDF', $boletas->pdf($periodo, $periodo->lineas()->sole()));
    }
}
