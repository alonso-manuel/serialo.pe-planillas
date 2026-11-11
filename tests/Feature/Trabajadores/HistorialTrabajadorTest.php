<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoContrato;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\TrabajadorHistorial;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use App\Services\Trabajadores\HistorialTrabajador;
use Carbon\CarbonImmutable;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cambios de sueldo, cargo, pensión... con vigencia mensual. Regla 3: un periodo
 * se recalcula siempre con los valores vigentes en ese mes. Datos ficticios.
 */
class HistorialTrabajadorTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Afp $afp;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->afp = Afp::create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'activo' => true]);
    }

    private function trabajador(bool $enPlanilla = true): Trabajador
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2026-01-02',
            'remuneracion_basica' => '1500.00',
            'cargo' => 'Vendedor',
            'sistema_pension' => SistemaPension::Onp,
            'tipo_contrato' => TipoContrato::PlazoFijo,
        ]);

        if ($enPlanilla) {
            Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 1])
                ->lineas()->create(['trabajador_id' => $t->id]);
        }

        return $t;
    }

    /** @param array<string, mixed> $datos */
    private function cambiar(Trabajador $t, string $grupo, string $desde, array $datos)
    {
        return $this->actingAs($this->asistente)->post(
            route('trabajadores.cambios.store', $t),
            ['grupo' => $grupo, 'vigente_desde' => $desde, ...$datos],
        );
    }

    #[Test]
    public function registra_un_aumento_de_sueldo_con_vigencia(): void
    {
        $t = $this->trabajador();

        $this->cambiar($t, 'remuneracion', '2026-03', ['remuneracion_basica' => '1800'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fila = TrabajadorHistorial::sole();
        $this->assertSame('remuneracion', $fila->grupo);
        $this->assertSame('remuneracion_basica', $fila->campo);
        $this->assertSame('1500.00', $fila->valor_anterior);
        $this->assertSame('1800.00', $fila->valor_nuevo);
        $this->assertSame('2026-03-01', $fila->vigente_desde->toDateString());
        $this->assertSame($this->asistente->id, $fila->user_id);
        $this->assertSame('1800.00', $t->refresh()->remuneracion_basica, 'La ficha guarda el último valor.');
    }

    #[Test]
    public function el_valor_de_cada_fecha_se_reconstruye_incluso_con_cambios_futuros(): void
    {
        $t = $this->trabajador();
        $this->cambiar($t, 'remuneracion', '2026-03', ['remuneracion_basica' => '1800']);
        $this->cambiar($t, 'remuneracion', '2026-12', ['remuneracion_basica' => '2000']);

        $historial = app(HistorialTrabajador::class);
        $t->refresh();
        $al = fn (string $fecha) => $historial->valoresAl($t, CarbonImmutable::parse($fecha))['remuneracion_basica'];

        $this->assertSame('1500.00', $al('2026-02-28'));
        $this->assertSame('1800.00', $al('2026-03-31'));
        $this->assertSame('1800.00', $al('2026-11-15'));
        $this->assertSame('2000.00', $al('2026-12-31'));

        $this->get(route('trabajadores.index'))
            ->assertInertia(fn (Assert $page) => $page->where('trabajadores.data.0.remuneracion_basica', '1800.00'));
    }

    #[Test]
    public function el_calculo_usa_el_sueldo_vigente_de_cada_mes(): void
    {
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $t = $this->trabajador(enPlanilla: false);
        $febrero = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 2]);
        $marzo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 3]);
        foreach ([$febrero, $marzo] as $p) {
            $p->lineas()->create(['trabajador_id' => $t->id]);
        }

        $this->cambiar($t, 'remuneracion', '2026-03', ['remuneracion_basica' => '1800'])->assertSessionHasNoErrors();

        $calcular = app(CalcularPlanillaPeriodo::class);
        $basico = fn (Periodo $p) => $p->lineas()->sole()->conceptos()
            ->whereHas('concepto', fn ($q) => $q->where('codigo_plame', '0121'))->sole()->monto_devengado;

        $calcular->ejecutar($marzo);
        $calcular->ejecutar($febrero);
        $this->assertSame('1500.00', $basico($febrero));
        $this->assertSame('1800.00', $basico($marzo));

        $calcular->ejecutar($febrero->refresh());
        $this->assertSame('1500.00', $basico($febrero), 'Recalcular febrero no toma el sueldo de marzo.');
    }

    #[Test]
    public function el_cambio_de_pension_registra_el_grupo_completo(): void
    {
        $t = $this->trabajador();

        $this->cambiar($t, 'pension', '2026-04', [
            'sistema_pension' => 'afp', 'afp_id' => $this->afp->id,
            'tipo_comision_afp' => 'mixta', 'cuspp' => 'abc123def456',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['sistema_pension', 'afp_id', 'tipo_comision_afp', 'cuspp'],
            TrabajadorHistorial::where('grupo', 'pension')->pluck('campo')->all(),
        );

        $t->refresh();
        $this->assertSame(SistemaPension::Afp, $t->sistema_pension);
        $this->assertSame('ABC123DEF456', $t->cuspp);

        $historial = app(HistorialTrabajador::class);
        $this->assertSame('onp', $historial->valoresAl($t, CarbonImmutable::parse('2026-03-31'))['sistema_pension']);
        $this->assertNull($historial->valoresAl($t, CarbonImmutable::parse('2026-03-31'))['afp_id']);
        $this->assertSame('afp', $historial->valoresAl($t, CarbonImmutable::parse('2026-04-30'))['sistema_pension']);

        // Y la AFP sin CUSPP no se acepta.
        $this->cambiar($t, 'pension', '2026-06', ['sistema_pension' => 'afp', 'afp_id' => $this->afp->id, 'tipo_comision_afp' => 'flujo'])
            ->assertSessionHasErrors('cuspp');
    }

    #[Test]
    public function cambios_de_cargo_asignacion_seguro_y_contrato(): void
    {
        $t = $this->trabajador();

        $this->cambiar($t, 'cargo', '2026-05', ['cargo' => 'Supervisor'])->assertSessionHasNoErrors();
        $this->cambiar($t, 'asignacion_familiar', '2026-05', ['asignacion_familiar' => true])->assertSessionHasNoErrors();
        $this->cambiar($t, 'tipo_contrato', '2026-07', ['tipo_contrato' => 'indeterminado'])->assertSessionHasNoErrors();
        $this->cambiar($t, 'seguro_salud', '2026-07', ['seguro_salud' => 'sis'])
            ->assertSessionHasErrors(['seguro_salud' => 'El SIS solo aplica a trabajadores de microempresas.']);

        $t->refresh();
        $this->assertSame('Supervisor', $t->cargo);
        $this->assertTrue($t->asignacion_familiar);
        $this->assertSame(TipoContrato::Indeterminado, $t->tipo_contrato);
        $this->assertSame(SeguroSalud::Essalud, $t->seguro_salud);
        $this->assertSame(3, TrabajadorHistorial::count());
    }

    #[Test]
    public function validaciones_del_cambio(): void
    {
        $t = $this->trabajador();
        $this->cambiar($t, 'remuneracion', '2026-06', ['remuneracion_basica' => '1800']);

        $this->cambiar($t, 'remuneracion', '2026-08', ['remuneracion_basica' => '1800.00'])
            ->assertSessionHasErrors(['remuneracion_basica' => 'Es el mismo valor que ya tiene; no hay cambio que registrar.']);

        $this->cambiar($t, 'remuneracion', '2026-05', ['remuneracion_basica' => '2000'])
            ->assertSessionHasErrors(['vigente_desde' => 'Ya hay un cambio de remuneración básica desde 06/2026: registra uno posterior o anula ese.']);

        $this->cambiar($t, 'cargo', '2025-12', ['cargo' => 'Jefe'])
            ->assertSessionHasErrors(['vigente_desde' => 'El cambio no puede regir antes del mes de ingreso (01/2026).']);

        $this->cambiar($t, 'inventado', '2026-08', [])->assertSessionHasErrors('grupo');
        $this->cambiar($t, 'cargo', '2026-08-15', ['cargo' => 'Jefe'])->assertSessionHasErrors('vigente_desde');

        $this->assertSame(1, TrabajadorHistorial::count());
    }

    #[Test]
    public function no_se_registran_cambios_que_alteren_un_periodo_cerrado(): void
    {
        $t = $this->trabajador();
        Periodo::factory()->for($this->empresa)->cerrado()->create(['anio' => 2026, 'mes' => 5]);

        $this->cambiar($t, 'remuneracion', '2026-04', ['remuneracion_basica' => '1800'])
            ->assertSessionHasErrors(['vigente_desde' => 'Hay un periodo cerrado en 05/2026 o después: el cambio debe regir desde 06/2026.']);

        $this->cambiar($t, 'remuneracion', '2026-06', ['remuneracion_basica' => '1800'])->assertSessionHasNoErrors();
    }

    #[Test]
    public function en_la_ficha_estos_campos_se_bloquean_si_ya_estuvo_en_planilla(): void
    {
        $t = $this->trabajador();
        $datos = $this->datosFicha($t);

        $this->actingAs($this->asistente)
            ->put(route('trabajadores.update', $t), [...$datos, 'remuneracion_basica' => '1900', 'cargo' => 'Jefe'])
            ->assertSessionHasErrors([
                'remuneracion_basica' => 'Ya figura en planillas: usa «Registrar cambio» para que rija desde un mes.',
                'cargo',
            ]);

        $this->put(route('trabajadores.update', $t), [...$datos, 'email' => 'nuevo@ejemplo.pe'])->assertSessionHasNoErrors();
        $this->assertSame('1500.00', $t->refresh()->remuneracion_basica);
    }

    #[Test]
    public function sin_planilla_se_corrige_directo_en_la_ficha(): void
    {
        $t = $this->trabajador(enPlanilla: false);

        $this->actingAs($this->asistente)
            ->put(route('trabajadores.update', $t), [...$this->datosFicha($t), 'remuneracion_basica' => '1900'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1900.00', $t->refresh()->remuneracion_basica);
        $this->assertSame(0, TrabajadorHistorial::count());
    }

    #[Test]
    public function se_anula_solo_el_ultimo_cambio_del_grupo_y_si_no_toca_un_cierre(): void
    {
        $t = $this->trabajador();
        $this->cambiar($t, 'remuneracion', '2026-03', ['remuneracion_basica' => '1800']);
        $this->cambiar($t, 'remuneracion', '2026-09', ['remuneracion_basica' => '2000']);
        $primero = TrabajadorHistorial::where('valor_nuevo', '1800.00')->sole();
        $ultimo = TrabajadorHistorial::where('valor_nuevo', '2000.00')->sole();

        $this->delete(route('trabajadores.cambios.destroy', [$t, $primero]))->assertRedirect();
        $this->assertModelExists($primero);

        $this->delete(route('trabajadores.cambios.destroy', [$t, $ultimo]))->assertRedirect();
        $this->assertModelMissing($ultimo);
        $this->assertSame('1800.00', $t->refresh()->remuneracion_basica, 'Vuelve al valor anterior.');

        Periodo::factory()->for($this->empresa)->cerrado()->create(['anio' => 2026, 'mes' => 4]);
        $this->delete(route('trabajadores.cambios.destroy', [$t, $primero]))->assertRedirect();
        $this->assertModelExists($primero);
    }

    #[Test]
    public function la_ficha_muestra_el_historial(): void
    {
        $t = $this->trabajador();
        $this->cambiar($t, 'remuneracion', '2026-03', ['remuneracion_basica' => '1800']);

        $this->get(route('trabajadores.edit', $t))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trabajador.en_planilla', true)
                ->has('historial', 1)
                ->where('historial.0.grupo_etiqueta', 'Remuneración básica')
                ->where('historial.0.desde', '03/2026')
                ->where('historial.0.cambios.0.antes', 'S/ 1,500.00')
                ->where('historial.0.cambios.0.despues', 'S/ 1,800.00')
                ->where('historial.0.anulable', true)
                ->has('grupos_historial', 7) // incluye el centro de costo
            );
    }

    #[Test]
    public function asistente_no_cambia_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();

        $this->cambiar($ajeno, 'cargo', '2026-08', ['cargo' => 'X'])->assertForbidden();
        $this->assertSame(0, TrabajadorHistorial::count());
    }

    /** @return array<string, mixed> */
    private function datosFicha(Trabajador $t): array
    {
        return [
            'tipo_documento' => $t->tipo_documento->value,
            'numero_documento' => $t->numero_documento,
            'apellido_paterno' => $t->apellido_paterno,
            'apellido_materno' => $t->apellido_materno,
            'nombres' => $t->nombres,
            'establecimiento_id' => null,
            'cargo' => $t->cargo,
            'fecha_ingreso' => $t->fecha_ingreso->toDateString(),
            'tipo_contrato' => $t->tipo_contrato->value,
            'remuneracion_basica' => $t->remuneracion_basica,
            'asignacion_familiar' => $t->asignacion_familiar,
            'sistema_pension' => $t->sistema_pension->value,
            'afp_id' => $t->afp_id,
            'cuspp' => $t->cuspp,
            'tipo_comision_afp' => $t->tipo_comision_afp?->value,
            'seguro_salud' => $t->seguro_salud->value,
        ];
    }
}
