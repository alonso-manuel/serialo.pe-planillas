<?php

namespace Tests\Feature\Respaldos;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Respaldo\Respaldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConLicencia;
use Tests\TestCase;

/** Respaldos de la base en una instalación única: volcado restaurable, pantalla de admin, poda y copia externa. Datos ficticios. */
class RespaldosTest extends TestCase
{
    use ConLicencia;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-10 10:00');
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function el_respaldo_se_restaura_completo_en_una_base_vacia(): void
    {
        $empresa = Empresa::factory()->create(['razon_social' => "Panadería D'Ángelo \"El Ñandú\" S.A.C."]);
        Trabajador::factory()->count(3)->create(['empresa_id' => $empresa->id]);
        $empresa->update(['direccion' => "Jr. Ficticio 123\nInterior B; DROP TABLE x; --"]);

        $r = app(Respaldos::class)->crear();
        $sql = (string) gzdecode((string) file_get_contents((string) app(Respaldos::class)->ruta($r['archivo'])));

        config(['database.connections.restaurada' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $restaurada = DB::connection('restaurada');
        $restaurada->getPdo()->exec($sql);

        foreach (['empresas', 'trabajadores', 'users', 'auditorias', 'parametros', 'conceptos', 'migrations'] as $tabla) {
            $this->assertSame(DB::table($tabla)->count(), $restaurada->table($tabla)->count(), "Filas de {$tabla}");
        }
        $copia = $restaurada->table('empresas')->where('id', $empresa->id)->first();
        $this->assertNotNull($copia);
        $this->assertSame("Panadería D'Ángelo \"El Ñandú\" S.A.C.", $copia->razon_social);
        $this->assertSame("Jr. Ficticio 123\nInterior B; DROP TABLE x; --", $copia->direccion);
        $this->assertSame(DB::table('trabajadores')->orderBy('id')->pluck('numero_documento')->all(), $restaurada->table('trabajadores')->orderBy('id')->pluck('numero_documento')->all());
    }

    #[Test]
    public function el_admin_crea_lista_y_descarga_y_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)->post(route('respaldos.store'))->assertRedirect(route('respaldos.index'));

        $archivo = '';
        $this->get(route('respaldos.index'))->assertOk()->assertInertia(function (Assert $p) use (&$archivo) {
            $p->component('respaldos/Index')->has('respaldos', 1)->where('dias', 14);
            $archivo = $p->toArray()['props']['respaldos'][0]['archivo'];
        });
        $this->assertSame('respaldo-20261110-100000.sql.gz', $archivo); // hora de Lima

        $this->get(route('respaldos.descargar', $archivo))->assertOk()->assertDownload($archivo);

        $this->assertSame(['creado', 'descargado'], Auditoria::query()->where('auditable_type', 'respaldo')->orderBy('id')->pluck('accion')->all());
        $this->assertSame($this->admin->id, Auditoria::query()->where('accion', 'descargado')->value('user_id'));
    }

    #[Test]
    public function un_asistente_no_ve_ni_descarga_respaldos(): void
    {
        $r = app(Respaldos::class)->crear();
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente)->get(route('respaldos.index'))->assertForbidden();
        $this->post(route('respaldos.store'))->assertForbidden();
        $this->get(route('respaldos.descargar', $r['archivo']))->assertForbidden();
    }

    #[Test]
    public function solo_se_descargan_archivos_de_respaldo_existentes(): void
    {
        Storage::disk('local')->put('respaldos/notas.txt', 'x');

        $this->actingAs($this->admin);
        $this->get(route('respaldos.descargar', 'notas.txt'))->assertNotFound();
        $this->get(route('respaldos.descargar', 'respaldo-20260101-000000.sql.gz'))->assertNotFound();
        $this->get('/respaldos/..%2F..%2F.env')->assertNotFound();
    }

    #[Test]
    public function conserva_los_ultimos_dias_y_nunca_borra_el_mas_reciente(): void
    {
        $disco = Storage::disk('local');
        foreach (['respaldo-20261001-030000.sql.gz' => '2026-10-01', 'respaldo-20261020-030000.sql.gz' => '2026-10-20', 'respaldo-20261101-030000.sql.gz' => '2026-11-01'] as $archivo => $fecha) {
            $disco->put("respaldos/{$archivo}", 'x');
            touch($disco->path("respaldos/{$archivo}"), strtotime("{$fecha} 03:00 America/Lima"));
        }

        // 14 días: se van el del 1/10 y el del 20/10; queda el del 1/11.
        $this->assertSame(2, app(Respaldos::class)->podar());
        $this->assertSame(['respaldo-20261101-030000.sql.gz'], array_column(app(Respaldos::class)->listar(), 'archivo'));

        // Aunque el único que queda sea antiguo, no se borra.
        $this->travelTo('2027-03-01 10:00');
        $this->assertSame(0, app(Respaldos::class)->podar());
        $this->assertCount(1, app(Respaldos::class)->listar());
    }

    #[Test]
    public function copia_el_respaldo_fuera_del_servidor_si_hay_disco_externo(): void
    {
        Storage::fake('externo');
        config(['planillas.respaldos.disco_externo' => 'externo', 'app.url' => 'https://planillas.estudio-ficticio.pe']);

        $r = app(Respaldos::class)->crear();

        $this->assertTrue($r['externo']);
        Storage::disk('externo')->assertExists("planillas-estudio-ficticio-pe/{$r['archivo']}");
    }

    #[Test]
    public function el_comando_diario_crea_el_respaldo(): void
    {
        $this->artisan('respaldos:crear')->assertSuccessful();

        $this->assertCount(1, app(Respaldos::class)->listar());
    }

    #[Test]
    public function en_solo_lectura_igual_se_puede_crear_un_respaldo(): void
    {
        $this->activarLicencia(instaladoEn: '2026-10-01'); // prueba vencida
        $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('avisoLicencia.solo_lectura', true));

        $this->post(route('respaldos.store'))->assertRedirect(route('respaldos.index'));
        $this->assertCount(1, app(Respaldos::class)->listar());
    }
}
