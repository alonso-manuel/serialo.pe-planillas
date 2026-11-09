<?php

namespace Tests\Feature\Panel;

use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Models\Empresa;
use App\Models\EnvioBoleta;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\AfpSeeder;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\TasaAfpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Panel del estudio. Datos ficticios. */
class PanelEstudioTest extends TestCase
{
    use RefreshDatabase;

    private User $asistente;

    private Empresa $alfa;

    private Empresa $beta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class, AfpSeeder::class, TasaAfpSeeder::class]);
        $this->alfa = Empresa::factory()->create(['razon_social' => 'Alfa Demo S.A.C.', 'regimen_laboral' => RegimenLaboral::General]);
        $this->beta = Empresa::factory()->create(['razon_social' => 'Beta Demo S.A.C.', 'regimen_laboral' => RegimenLaboral::General]);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach([$this->alfa->id, $this->beta->id]);
    }

    private function periodoCerrado(Empresa $empresa): Periodo
    {
        $periodo = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 11]);
        $t = Trabajador::factory()->for($empresa)->create([
            'fecha_ingreso' => '2026-01-01', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'email' => 'alfa@ejemplo.test',
        ]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo));
        $periodo->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);
        EnvioBoleta::query()->create(['planilla_linea_id' => $linea->id, 'email' => 'alfa@ejemplo.test', 'estado' => 'enviado', 'enviado_en' => now()]);

        return $periodo;
    }

    #[Test]
    public function muestra_el_avance_del_mes_de_cada_empresa(): void
    {
        $periodo = $this->periodoCerrado($this->alfa);
        Exportacion::registrar(Exportacion::PLAME, $periodo, ['x.rem' => 'contenido'], $this->asistente);

        $this->actingAs($this->asistente)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('mes', 11)
                ->where('resumen.empresas', 2)
                ->where('resumen.cerradas', 1)
                ->where('resumen.pendientes', 1)
                ->where('resumen.neto', '1305.00')
                ->where('empresas.0.razon_social', 'Alfa Demo S.A.C.')
                ->where('empresas.0.periodo.estado', 'cerrado')
                ->where('empresas.0.plame', '20/11/2026')
                ->where('empresas.0.afpnet', null)
                ->where('empresas.0.boletas.enviadas', 1)
                ->where('empresas.0.boletas.con_correo', 1)
                ->where('empresas.1.periodo', null));
    }

    #[Test]
    public function arma_la_serie_de_12_meses_la_composicion_y_el_avance(): void
    {
        $periodo = $this->periodoCerrado($this->alfa);
        Exportacion::registrar(Exportacion::PLAME, $periodo, ['x.rem' => 'contenido'], $this->asistente);

        // Otra empresa (no visible) con planilla en el mismo mes: no suma.
        $ajena = Empresa::factory()->create();
        $p = Periodo::factory()->for($ajena)->create(['anio' => 2026, 'mes' => 11]);
        $t = Trabajador::factory()->for($ajena)->create(['fecha_ingreso' => '2026-11-03', 'sistema_pension' => 'onp', 'afp_id' => null]);
        $p->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30, 'calculado_en' => now(), 'neto_pagar' => '9999.00']);

        $this->actingAs($this->asistente)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('serie', 12)
                ->where('serie.0.etiqueta', 'Dic 25')
                ->where('serie.0.neto', null)
                ->where('serie.11.etiqueta', 'Nov 26')
                ->where('serie.11.neto', '1305.00')
                ->where('serie.11.aportes', '135.00')
                ->where('serie.11.costo', '1635.00')
                ->where('serie.11.trabajadores', 1)
                // 1500 de sueldo: 1305 neto + 195 ONP; EsSalud 135 a cargo del empleador.
                ->where('composicion.neto', '1305.00')
                ->where('composicion.pensiones', '195.00')
                ->where('composicion.descuentos', '0.00')
                ->where('composicion.aportes', '135.00')
                ->where('composicion.total', '1635.00')
                ->where('avance.con_periodo', 1)
                ->where('avance.calculadas', 1)
                ->where('avance.cerradas', 1)
                ->where('avance.plame', 1)
                ->where('avance.afpnet', 0)
                ->where('avance.boletas', 1)
                ->where('movimiento.altas', 0)
                ->where('movimiento.vigentes', 1));
    }

    #[Test]
    public function el_asistente_solo_ve_sus_empresas(): void
    {
        Empresa::factory()->create(['razon_social' => 'Ajena S.A.']);

        $this->actingAs($this->asistente)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('empresas', 2));
    }

    #[Test]
    public function avisa_cambios_sin_recalcular_y_tasas_afp_faltantes(): void
    {
        $periodo = $this->periodoCerrado($this->alfa);
        $periodo->update(['estado' => EstadoPeriodo::Abierto]); // se editó después de calcular

        $this->actingAs($this->asistente)->get(route('dashboard', ['anio' => 2027, 'mes' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas', fn ($alertas) => collect($alertas)->contains('tipo', 'tasas_afp')));

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas', fn ($alertas) => collect($alertas)->contains('tipo', 'desactualizado')));
    }

    #[Test]
    public function recuerda_la_cts_y_la_gratificacion_en_sus_meses(): void
    {
        $this->actingAs($this->asistente)->get(route('dashboard', ['anio' => 2026, 'mes' => 11]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas', fn ($alertas) => collect($alertas)->where('tipo', 'cts')->count() === 2));

        $this->get(route('dashboard', ['anio' => 2026, 'mes' => 12]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas', fn ($alertas) => collect($alertas)->where('tipo', 'gratificacion')->count() === 2));
    }

    #[Test]
    public function avisa_vacaciones_vencidas_de_la_empresa_activa(): void
    {
        Trabajador::factory()->for($this->alfa)->create(['fecha_ingreso' => '2023-01-01', 'fecha_cese' => null]);

        $this->actingAs($this->asistente)->withSession(['empresa_id' => $this->alfa->id])->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas', fn ($alertas) => collect($alertas)->contains('tipo', 'vacaciones')));
    }
}
