<?php

namespace Tests\Feature\Sunat;

use App\Enums\EstadoPeriodo;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Sunat\ExportacionAfpnet;
use App\Services\Sunat\ExportacionPlame;
use Database\Seeders\AfpSeeder;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\TasaAfpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Planilla para AFPnet y licencia sin goce. Datos ficticios. */
class ExportacionAfpnetTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class, AfpSeeder::class, TasaAfpSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $dni, array $trabajador = [], array $linea = []): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => $dni, 'fecha_ingreso' => '2025-01-01', 'fecha_cese' => null,
            'apellido_paterno' => 'Núñez', 'apellido_materno' => 'Ávila', 'nombres' => 'José',
            'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'afp', 'afp_id' => Afp::query()->where('codigo', 'INTEGRA')->value('id'),
            'tipo_comision_afp' => 'flujo', 'cuspp' => 'DEMO'.$dni, ...$trabajador,
        ]);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30, ...$linea]);
    }

    private function cerrar(): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo))->assertRedirect();
        $this->assertSame('calculado', $this->periodo->refresh()->estado->value, implode(' ', session('problemas_calculo', [])));
        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);
    }

    /** @return list<string> */
    private function lineas(string $contenido): array
    {
        return explode("\r\n", rtrim($contenido, "\r\n"));
    }

    #[Test]
    public function solo_entran_los_trabajadores_en_afp_con_su_remuneracion_asegurable(): void
    {
        $this->enPlanilla('70000002', ['fecha_ingreso' => '2026-11-10'], ['dias_laborados' => 21]);
        $this->enPlanilla('70000001');
        $this->enPlanilla('70000003', ['sistema_pension' => 'onp', 'afp_id' => null, 'cuspp' => null]);
        $this->cerrar();

        $archivo = app(ExportacionAfpnet::class)->archivo($this->periodo->refresh());
        $lineas = $this->lineas($archivo['contenido']);

        $this->assertSame('AFPNET 2026-11 20000000044.txt', $archivo['nombre']);
        $this->assertCount(2, $lineas);                               // el de ONP no entra
        $this->assertSame('00001DEMO70000001'.'0', substr($lineas[0], 0, 18));
        $this->assertSame("NU\xD1EZ", rtrim(substr($lineas[0], 38, 20))); // ANSI, sin tilde, con Ñ
        $this->assertSame('AVILA', rtrim(substr($lineas[0], 58, 20)));
        $this->assertSame('SNN ', substr($lineas[0], 98, 4));          // vigente, sin inicio ni cese, aporta
        $this->assertSame('000150000', substr($lineas[0], 102, 9));    // 1500.00
        $this->assertSame('NIN', substr($lineas[0], 138, 3));          // tipo de trabajo N, Integra
        // El segundo inició en el mes: 1500 × 21/30 = 1050.00
        $this->assertSame('SSN ', substr($lineas[1], 98, 4));
        $this->assertSame('000105000', substr($lineas[1], 102, 9));
    }

    #[Test]
    public function sin_remuneracion_indica_el_motivo_de_la_excepcion(): void
    {
        $this->enPlanilla('70000001', [], ['dias_laborados' => 0, 'dias_subsidiados' => 30, 'tipo_subsidio' => '22']);
        $this->enPlanilla('70000002', [], ['dias_laborados' => 0, 'dias_licencia' => 30]);
        $this->cerrar();

        $lineas = $this->lineas(app(ExportacionAfpnet::class)->archivo($this->periodo->refresh())['contenido']);

        $this->assertSame('U000000000', substr($lineas[0], 101, 10)); // subsidio EsSalud
        $this->assertSame('L000000000', substr($lineas[1], 101, 10)); // licencia sin goce
    }

    #[Test]
    public function la_licencia_sin_goce_va_al_snl_con_el_codigo_05_y_reduce_los_dias_pagados(): void
    {
        $linea = $this->enPlanilla('70000001');

        $this->actingAs($this->asistente)->putJson(route('periodos.lineas.update', [$this->periodo, $linea]), [
            'dias_no_laborados' => 1, 'dias_subsidiados' => 0, 'dias_licencia' => 4,
            'horas_extra_25' => '0', 'horas_extra_35' => '0', 'novedades' => [],
        ])->assertOk()->assertJsonPath('dias_licencia', 4)->assertJsonPath('dias_laborados', 25);

        $this->cerrar();
        $snl = collect(app(ExportacionPlame::class)->archivos($this->periodo->refresh()))
            ->first(fn ($c, $nombre) => str_ends_with($nombre, '.snl'));

        $this->assertSame("01|70000001|07|1|\r\n01|70000001|05|4|\r\n", $snl);
    }

    #[Test]
    public function descarga_el_txt_y_deja_constancia(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar();

        $respuesta = $this->get(route('afpnet.periodo', $this->periodo))->assertOk();

        $this->assertStringContainsString('AFPNET 2026-11 20000000044.txt', (string) $respuesta->headers->get('Content-Disposition'));
        $constancia = Exportacion::query()->sole();
        $this->assertSame('afpnet', $constancia->tipo);
        $this->assertSame(hash('sha256', (string) $respuesta->getContent()), $constancia->archivos[0]['sha256']);
    }

    #[Test]
    public function el_zip_de_todas_omite_las_empresas_sin_afp(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar();

        $soloOnp = Empresa::factory()->create(['ruc' => '20000000052']);
        $this->asistente->empresas()->attach($soloOnp);
        $p = Periodo::factory()->for($soloOnp)->create(['anio' => 2026, 'mes' => 11]);
        $p->lineas()->create(['trabajador_id' => Trabajador::factory()->for($soloOnp)->create([
            'fecha_ingreso' => '2025-01-01', 'sistema_pension' => 'onp', 'afp_id' => null,
        ])->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $p));
        $p->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);

        $respuesta = $this->get(route('afpnet.todas', ['anio' => 2026, 'mes' => 11]))->assertOk();

        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('AFPNET 2026-11 20000000044.txt', $zip->getNameIndex(0));
        $zip->close();
    }

    #[Test]
    public function sin_trabajadores_en_afp_avisa_y_no_descarga(): void
    {
        $this->enPlanilla('70000001', ['sistema_pension' => 'onp', 'afp_id' => null, 'cuspp' => null]);
        $this->cerrar();

        $this->get(route('afpnet.periodo', $this->periodo))
            ->assertRedirect(route('afpnet.index', ['anio' => 2026, 'mes' => 11]))
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertSame(0, Exportacion::query()->count());
    }

    #[Test]
    public function la_pantalla_y_los_permisos(): void
    {
        $this->enPlanilla('70000001');
        $this->cerrar();

        $this->get(route('afpnet.index'))->assertInertia(fn (Assert $page) => $page
            ->component('afpnet/Index')->where('mes', 11)->where('empresas.0.listo', true));

        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());
        $this->actingAs($intruso)->get(route('afpnet.periodo', $this->periodo))->assertForbidden();
    }
}
