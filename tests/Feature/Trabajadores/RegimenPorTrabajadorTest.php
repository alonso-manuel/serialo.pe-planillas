<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\CategoriaOcupacional;
use App\Enums\RegimenLaboral;
use App\Enums\SituacionEducativa;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\LiquidacionesCts;
use App\Services\Planilla\VacacionesTrabajador;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/**
 * Régimen laboral por trabajador: en una micro o pequeña empresa, quien fue contratado antes de la inscripción en el
 * REMYPE conserva el régimen general y sus beneficios completos ⚠. Datos ficticios (Ley 29733).
 */
class RegimenPorTrabajadorTest extends TestCase
{
    use RefreshDatabase;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-10');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->asistente = User::factory()->asistente()->create();
    }

    private function empresa(RegimenLaboral $regimen, string $seguro = 'essalud'): Empresa
    {
        $empresa = Empresa::factory()->create(['ruc' => '20000000044', 'regimen_laboral' => $regimen, 'seguro_salud' => $seguro]);
        $empresa->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
        $this->asistente->empresas()->attach($empresa);

        return $empresa;
    }

    private function trabajador(Empresa $empresa, ?RegimenLaboral $regimen, array $datos = []): Trabajador
    {
        static $dni = 70000001;

        return Trabajador::factory()->for($empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => (string) $dni++,
            'fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'fecha_cese' => null, 'seguro_salud' => 'essalud',
            'regimen_laboral' => $regimen, ...$datos,
        ]);
    }

    /** @return array<int, array<string, string>> trabajador_id => código => monto */
    private function diciembre(Empresa $empresa, Trabajador ...$trabajadores): array
    {
        $periodo = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 12]);
        foreach ($trabajadores as $t) {
            $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        }
        $this->actingAs($this->asistente)->withSession(['empresa_id' => $empresa->id])
            ->post(route('periodos.calcular', $periodo))->assertRedirect();
        $this->assertSame('calculado', $periodo->refresh()->estado->value, implode(' ', session('problemas_calculo', [])));

        return $periodo->lineas()->with('conceptos.concepto')->get()->mapWithKeys(fn (PlanillaLinea $l) => [
            $l->trabajador_id => $l->conceptos->mapWithKeys(fn ($c) => [$c->concepto->codigo_plame => (string) $c->monto_pagado])->all(),
        ])->all();
    }

    #[Test]
    public function en_una_pequena_empresa_cada_trabajador_cobra_la_gratificacion_de_su_regimen(): void
    {
        $empresa = $this->empresa(RegimenLaboral::PequenaEmpresa);
        $antiguo = $this->trabajador($empresa, RegimenLaboral::General);
        $mype = $this->trabajador($empresa, null);

        $conceptos = $this->diciembre($empresa, $antiguo, $mype);

        $this->assertSame('1500.00', $conceptos[$antiguo->id]['0406']); // régimen general: gratificación completa
        $this->assertSame('750.00', $conceptos[$mype->id]['0406']);     // pequeña empresa: media gratificación
    }

    #[Test]
    public function en_una_microempresa_quien_conserva_el_regimen_general_si_tiene_gratificacion(): void
    {
        $empresa = $this->empresa(RegimenLaboral::Microempresa, 'sis');
        $antiguo = $this->trabajador($empresa, RegimenLaboral::General);
        $micro = $this->trabajador($empresa, null, ['seguro_salud' => 'sis']);

        $conceptos = $this->diciembre($empresa, $antiguo, $micro);

        $this->assertSame('1500.00', $conceptos[$antiguo->id]['0406']);
        $this->assertSame('135.00', $conceptos[$antiguo->id]['0312']); // bonificación extraordinaria 9 % (EsSalud)
        $this->assertArrayNotHasKey('0406', $conceptos[$micro->id]);  // microempresa: sin gratificación
        $this->assertTrue($empresa->tieneGratificacionYCts());
    }

    #[Test]
    public function cts_completa_para_el_regimen_general_y_media_para_la_pequena_empresa(): void
    {
        $empresa = $this->empresa(RegimenLaboral::PequenaEmpresa);
        $antiguo = $this->trabajador($empresa, RegimenLaboral::General);
        $mype = $this->trabajador($empresa, null);

        $lineas = app(LiquidacionesCts::class)->calcular($empresa, 2026, 11)->lineas()->get()->keyBy('trabajador_id');

        // Semestre completo: (1500 + 1/6 de la gratificación) / 2 × factor del régimen.
        $this->assertSame('875.00', (string) $lineas[$antiguo->id]->monto); // (1500 + 250) / 2
        $this->assertSame('406.25', (string) $lineas[$mype->id]->monto);    // (1500 + 125) / 2 × 0.5
    }

    #[Test]
    public function vacaciones_de_30_dias_para_quien_conserva_el_regimen_general(): void
    {
        $empresa = $this->empresa(RegimenLaboral::PequenaEmpresa);
        $vacaciones = app(VacacionesTrabajador::class);

        $this->assertSame(30, $vacaciones->periodos($this->trabajador($empresa, RegimenLaboral::General))[0]->derecho);
        $this->assertSame(15, $vacaciones->periodos($this->trabajador($empresa, null))[0]->derecho);
    }

    #[Test]
    public function la_ficha_solo_admite_el_regimen_general_dentro_de_una_mype_y_sin_sis(): void
    {
        $micro = $this->empresa(RegimenLaboral::Microempresa, 'sis');
        $datos = fn (array $cambios = []) => [
            'tipo_documento' => '01', 'numero_documento' => '70000099', 'apellido_paterno' => 'Prueba', 'nombres' => 'Ana',
            'establecimiento_id' => $micro->establecimientos()->first()->id, 'fecha_ingreso' => '2020-01-02',
            'tipo_contrato' => 'indeterminado', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'seguro_salud' => 'essalud', 'regimen_laboral' => 'general', ...$cambios,
        ];
        $this->actingAs($this->asistente)->withSession(['empresa_id' => $micro->id]);

        // Con el régimen general no puede ir con SIS.
        $this->post(route('trabajadores.store'), $datos(['seguro_salud' => 'sis']))->assertSessionHasErrors('seguro_salud');

        $this->post(route('trabajadores.store'), $datos())->assertSessionHasNoErrors();
        $this->assertSame(RegimenLaboral::General, Trabajador::query()->where('numero_documento', '70000099')->sole()->regimen_laboral);

        // En una empresa del régimen general no hay excepción que registrar; tampoco otros regímenes.
        $general = Empresa::factory()->create(['ruc' => '20000000052', 'regimen_laboral' => RegimenLaboral::General]);
        $general->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
        $this->asistente->empresas()->attach($general);
        $this->withSession(['empresa_id' => $general->id])
            ->post(route('trabajadores.store'), $datos(['numero_documento' => '70000098', 'establecimiento_id' => $general->establecimientos()->first()->id]))
            ->assertSessionHasErrors('regimen_laboral');
        $this->withSession(['empresa_id' => $micro->id])
            ->post(route('trabajadores.store'), $datos(['numero_documento' => '70000097', 'regimen_laboral' => 'agrario']))
            ->assertSessionHasErrors('regimen_laboral');
    }

    #[Test]
    public function el_t_registro_informa_el_regimen_de_cada_trabajador(): void
    {
        Carbon::setTestNow('2026-11-20 10:00');
        $empresa = $this->empresa(RegimenLaboral::Microempresa, 'sis');
        $sunat = [
            'nombres' => 'Ana', 'apellido_materno' => 'Prueba', 'fecha_nacimiento' => '1990-03-15', 'sexo' => 'F',
            'telefono' => '987654321', 'email' => 'ana@correo-ficticio.pe', 'fecha_ingreso' => '2026-11-03', 'categoria_ocupacional' => CategoriaOcupacional::Empleado,
            'ocupacion' => '251003', 'situacion_educativa' => SituacionEducativa::UniversitariaCompleta,
        ];
        $antiguo = $this->trabajador($empresa, RegimenLaboral::General, $sunat);
        $micro = $this->trabajador($empresa, null, [...$sunat, 'seguro_salud' => 'sis']);

        $this->actingAs($this->asistente)->withSession(['empresa_id' => $empresa->id])
            ->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn ($p) => $p->where('listo', true));
        $respuesta = $this->actingAs($this->asistente)->withSession(['empresa_id' => $empresa->id])
            ->get(route('tregistro.zip', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk();
        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $tra = (string) $zip->getFromName('RP_20000000044.tra');

        // Tabla 33: 01 = régimen general, 16 = microempresa (cuarto campo de la línea del trabajador).
        $this->assertStringContainsString("01|{$antiguo->numero_documento}|604|01|", $tra);
        $this->assertStringContainsString("01|{$micro->numero_documento}|604|16|", $tra);
    }
}
