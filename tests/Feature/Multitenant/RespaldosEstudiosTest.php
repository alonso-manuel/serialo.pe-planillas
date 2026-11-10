<?php

namespace Tests\Feature\Multitenant;

use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Models\Central\Superadmin;
use App\Models\Empresa;
use App\Services\Respaldo\Respaldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/** Respaldos en modo SaaS: uno por estudio, en su carpeta; cada estudio solo ve los suyos; exportar desde el panel. Datos ficticios. */
class RespaldosEstudiosTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private string $centralLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 03:00');
        $this->activarModoSaas();

        // Disco local central en una carpeta temporal (tenancy la restaura al salir de cada estudio).
        $this->centralLocal = storage_path('framework/testing/respaldos-'.Str::lower(Str::random(6)));
        config(['filesystems.disks.local.root' => $this->centralLocal]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->centralLocal));
    }

    /** @return list<string> */
    private function respaldosDe(Estudio $estudio): array
    {
        return $estudio->run(fn () => array_column(app(Respaldos::class)->listar(), 'archivo'));
    }

    private function contenido(Estudio $estudio, string $archivo): string
    {
        return (string) gzdecode((string) file_get_contents((string) $estudio->run(fn () => app(Respaldos::class)->ruta($archivo))));
    }

    #[Test]
    public function el_comando_respalda_la_central_y_cada_estudio_en_su_carpeta(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $beta = $this->crearEstudio('beta', 'admin@beta.test');
        $alfa->run(fn () => Empresa::factory()->create(['razon_social' => 'Solo Alfa Ficticia S.A.C.']));
        $beta->run(fn () => Empresa::factory()->create(['razon_social' => 'Solo Beta Ficticia S.A.C.']));

        $this->artisan('respaldos:crear')->assertSuccessful()
            ->expectsOutputToContain('Base central')
            ->expectsOutputToContain('Estudio alfa')
            ->expectsOutputToContain('Estudio beta');

        $this->assertCount(1, File::glob($this->centralLocal.'/respaldos/respaldo-*.sql.gz') ?: []);
        $this->assertSame(['respaldo-20261110-030000.sql.gz'], $this->respaldosDe($alfa));

        $sqlAlfa = $this->contenido($alfa, 'respaldo-20261110-030000.sql.gz');
        $this->assertStringContainsString('Solo Alfa Ficticia', $sqlAlfa);
        $this->assertStringNotContainsString('Solo Beta Ficticia', $sqlAlfa);
        $this->assertStringNotContainsString('CREATE TABLE "estudios"', $sqlAlfa, 'La base central no entra en el respaldo de un estudio.');
    }

    #[Test]
    public function cada_estudio_solo_ve_y_descarga_sus_respaldos(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $beta = $this->crearEstudio('beta', 'admin@beta.test');

        $this->travelTo('2026-11-10 03:05');
        $this->artisan('respaldos:crear', ['--estudio' => 'beta'])->assertSuccessful();
        $this->assertSame([], $this->respaldosDe($alfa));
        $this->assertSame(['respaldo-20261110-030500.sql.gz'], $this->respaldosDe($beta));

        $this->ingresar($alfa, 'admin@alfa.test');
        $this->get($this->url($alfa, '/respaldos'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('respaldos/Index')->has('respaldos', 0)->where('saas', true));
        $this->get($this->url($alfa, '/respaldos/respaldo-20261110-030500.sql.gz'))->assertNotFound();

        $this->ingresar($beta, 'admin@beta.test');
        $this->get($this->url($beta, '/respaldos/respaldo-20261110-030500.sql.gz'))->assertOk()->assertDownload();
    }

    #[Test]
    public function la_copia_externa_va_a_la_carpeta_del_estudio(): void
    {
        Storage::fake('externo');
        config(['planillas.respaldos.disco_externo' => 'externo']);
        $this->crearEstudio('alfa', 'admin@alfa.test');

        $this->artisan('respaldos:crear')->assertSuccessful();

        Storage::disk('externo')->assertExists('central/respaldo-20261110-030000.sql.gz');
        Storage::disk('externo')->assertExists('estudio-alfa/respaldo-20261110-030000.sql.gz');
    }

    #[Test]
    public function el_superadmin_exporta_la_base_de_una_cuenta_y_queda_registrado(): void
    {
        $alfa = $this->crearEstudio('alfa', 'admin@alfa.test');
        $alfa->run(fn () => Empresa::factory()->create(['razon_social' => 'Exportable Ficticia S.A.C.']));

        $this->nuevaPeticion();
        $this->get('http://admin.impulso.cfd/cuentas/alfa/exportar')->assertRedirect('http://admin.impulso.cfd/ingresar');

        $s = Superadmin::query()->create(['name' => 'Alonso', 'email' => 'yo@serialo.test', 'password' => 'Clave-Panel-2026!']);
        $s->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($s, 'superadmin');

        $respuesta = $this->get('http://admin.impulso.cfd/cuentas/alfa/exportar')->assertOk()->assertDownload('alfa-20261110-030000.sql.gz');
        $sql = (string) gzdecode((string) file_get_contents($respuesta->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('Exportable Ficticia', $sql);

        $this->assertSame('exportado', AuditoriaCentral::query()->latest('id')->value('accion'));
    }
}
