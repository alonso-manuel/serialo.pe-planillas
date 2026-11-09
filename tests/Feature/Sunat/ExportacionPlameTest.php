<?php

namespace Tests\Feature\Sunat;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Sunat\ExportacionPlame;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Exportación al PLAME (.rem, .jor, .snl, .toc) con constancia. Datos ficticios. */
class ExportacionPlameTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044', 'razon_social' => 'Alfa Demo S.A.C.']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $dni, array $linea = []): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => $dni, 'fecha_ingreso' => '2025-01-01',
            'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false, 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30, ...$linea]);
    }

    private function cerrar(Periodo $periodo): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo));
        $periodo->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);
    }

    #[Test]
    public function genera_los_cuatro_archivos_con_el_formato_del_plame(): void
    {
        $this->enPlanilla('70000001');
        $this->enPlanilla('70000002', [
            'dias_no_laborados' => 2, 'dias_subsidiados' => 5, 'tipo_subsidio' => '22', 'dias_laborados' => 23,
            'horas_extra_25' => '2.50', 'horas_extra_35' => '1.00',
        ]);
        $this->cerrar($this->periodo);

        $archivos = app(ExportacionPlame::class)->archivos($this->periodo->refresh());

        $this->assertSame([
            '060120261120000000044.rem', '060120261120000000044.jor', '060120261120000000044.snl', '060120261120000000044.toc',
        ], array_keys($archivos));

        // .rem: básico, ONP y EsSalud del primero (ordenado por documento)
        $this->assertStringStartsWith(
            "01|70000001|0121|1500.00|1500.00|\r\n01|70000001|0607|195.00|195.00|\r\n01|70000001|0804|135.00|135.00|\r\n",
            $archivos['060120261120000000044.rem'],
        );
        // .jor: días × 8 h y horas extra 3.5 → 3 h 30 min
        $this->assertSame("01|70000001|240|0|0|0|\r\n01|70000002|184|0|3|30|\r\n", $archivos['060120261120000000044.jor']);
        // .snl: faltas (07) y subsidio por maternidad (22) solo de quien los tiene
        $this->assertSame("01|70000002|07|2|\r\n01|70000002|22|5|\r\n", $archivos['060120261120000000044.snl']);
        // .toc: valores por defecto
        $this->assertSame("01|70000001|0|0||1|\r\n01|70000002|0|0||1|\r\n", $archivos['060120261120000000044.toc']);
    }

    #[Test]
    public function sin_suspensiones_no_incluye_el_snl(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar($this->periodo);

        $this->assertArrayNotHasKey('060120261120000000044.snl', app(ExportacionPlame::class)->archivos($this->periodo->refresh()));
    }

    #[Test]
    public function descarga_el_zip_de_una_empresa_y_deja_constancia_con_hash(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar($this->periodo);

        $respuesta = $this->get(route('plame.periodo', $this->periodo))->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        $this->assertSame(3, $zip->numFiles); // rem, jor, toc
        $rem = (string) $zip->getFromName('060120261120000000044.rem');
        $zip->close();

        $constancia = Exportacion::query()->sole();
        $this->assertSame('plame', $constancia->tipo);
        $this->assertSame($this->asistente->id, $constancia->user_id);
        $this->assertSame(hash('sha256', $rem), collect($constancia->archivos)->firstWhere('nombre', '060120261120000000044.rem')['sha256']);
    }

    #[Test]
    public function el_zip_de_todas_trae_una_carpeta_por_empresa_cerrada(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar($this->periodo);

        $beta = Empresa::factory()->create(['ruc' => '20000000052', 'razon_social' => 'Beta Demo E.I.R.L.']);
        $this->asistente->empresas()->attach($beta);
        $pBeta = Periodo::factory()->for($beta)->create(['anio' => 2026, 'mes' => 11]);
        $pBeta->lineas()->create(['trabajador_id' => Trabajador::factory()->for($beta)->create([
            'fecha_ingreso' => '2025-01-01', 'sistema_pension' => 'onp', 'afp_id' => null,
        ])->id, 'dias_laborados' => 30]);
        $this->cerrar($pBeta);

        // Una tercera empresa con el mes abierto no entra.
        $gamma = Empresa::factory()->create();
        $this->asistente->empresas()->attach($gamma);
        Periodo::factory()->for($gamma)->create(['anio' => 2026, 'mes' => 11]);

        $respuesta = $this->get(route('plame.todas', ['anio' => 2026, 'mes' => 11]))->assertOk();

        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('20000000044 Alfa Demo S.A.C./060120261120000000044.rem', $nombres);
        $this->assertContains('20000000052 Beta Demo E.I.R.L./060120261120000000052.rem', $nombres);
        $this->assertSame(2, Exportacion::query()->count());
    }

    #[Test]
    public function un_periodo_abierto_no_se_exporta(): void
    {
        $this->enPlanilla('70000001');
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo));

        $this->get(route('plame.periodo', $this->periodo))
            ->assertRedirect(route('plame.index', ['anio' => 2026, 'mes' => 11]))
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertSame(0, Exportacion::query()->count());
    }

    #[Test]
    public function la_pantalla_lista_las_empresas_visibles_con_su_estado(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar($this->periodo);
        Empresa::factory()->create(['razon_social' => 'Ajena S.A.']); // no asignada

        // Por defecto, el mes anterior (noviembre).
        $this->get(route('plame.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('plame/Index')
                ->where('mes', 11)
                ->has('empresas', 1)
                ->where('empresas.0.listo', true)
                ->where('empresas.0.periodo.trabajadores', 1)
                ->where('empresas.0.ultima', null),
            );
    }

    #[Test]
    public function un_usuario_de_otra_empresa_no_exporta_sus_archivos(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar($this->periodo);
        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($intruso)->get(route('plame.periodo', $this->periodo))->assertForbidden();
        $this->actingAs($intruso)->get(route('plame.todas', ['anio' => 2026, 'mes' => 11]))
            ->assertRedirect()->assertInertiaFlash('toast.type', 'error');
        $this->assertSame(0, Exportacion::query()->count());
    }

    #[Test]
    public function la_grilla_guarda_el_tipo_de_subsidio(): void
    {
        $linea = $this->enPlanilla('70000001');

        $this->actingAs($this->asistente)->putJson(route('periodos.lineas.update', [$this->periodo, $linea]), [
            'dias_no_laborados' => 0, 'dias_subsidiados' => 4, 'tipo_subsidio' => '22',
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertOk()->assertJsonPath('tipo_subsidio', '22');

        // Un código que no es de subsidio se rechaza.
        $this->putJson(route('periodos.lineas.update', [$this->periodo, $linea]), [
            'dias_no_laborados' => 0, 'dias_subsidiados' => 4, 'tipo_subsidio' => '07',
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertJsonValidationErrors('tipo_subsidio');

        // Sin días subsidiados no queda tipo.
        $this->putJson(route('periodos.lineas.update', [$this->periodo, $linea]), [
            'dias_no_laborados' => 0, 'dias_subsidiados' => 0, 'tipo_subsidio' => '22',
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertOk()->assertJsonPath('tipo_subsidio', null);
    }
}
