<?php

namespace Tests\Feature\Periodos;

use App\Enums\ClaveParametro;
use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cálculo de todas las empresas visibles en cola (en tests la cola es síncrona).
 * Datos ficticios.
 */
class CalculoMasivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
    }

    private function empresaConPeriodo(string $razon, ?EstadoPeriodo $estado = EstadoPeriodo::Abierto, int $trabajadores = 1): Empresa
    {
        $empresa = Empresa::factory()->create(['razon_social' => $razon]);

        if ($estado !== null) {
            $periodo = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 11, 'estado' => $estado]);
            for ($i = 0; $i < $trabajadores; $i++) {
                $t = Trabajador::factory()->for($empresa)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00']);
                $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
            }
        }

        return $empresa;
    }

    #[Test]
    public function calcula_el_mes_de_todas_las_empresas_y_reporta_cada_una(): void
    {
        $admin = User::factory()->admin()->create();
        $this->empresaConPeriodo('Alfa S.A.C.', trabajadores: 2);
        $this->empresaConPeriodo('Beta S.A.C.');
        $this->empresaConPeriodo('Cerrada S.A.C.', EstadoPeriodo::Cerrado);
        $this->empresaConPeriodo('Sin Mes S.A.C.', null);
        $this->empresaConPeriodo('Vacía S.A.C.', trabajadores: 0);

        $respuesta = $this->actingAs($admin)
            ->post(route('calculos.store'), ['anio' => 2026, 'mes' => 11])
            ->assertRedirect();

        $id = (string) str($respuesta->headers->get('Location'))->after('calculo=');

        $this->getJson(route('calculos.show', $id))
            ->assertOk()
            ->assertJson([
                'mes' => '11/2026',
                'total' => 5,
                'pendientes' => 0,
                'terminado' => true,
                'empresas' => [
                    ['empresa' => 'Alfa S.A.C.', 'estado' => 'calculado', 'trabajadores' => 2, 'neto' => '2610.00'],
                    ['empresa' => 'Beta S.A.C.', 'estado' => 'calculado', 'trabajadores' => 1, 'neto' => '1305.00'],
                    ['empresa' => 'Cerrada S.A.C.', 'estado' => 'omitido', 'detalle' => 'Periodo cerrado.'],
                    ['empresa' => 'Sin Mes S.A.C.', 'estado' => 'sin_periodo'],
                    ['empresa' => 'Vacía S.A.C.', 'estado' => 'con_problemas', 'problemas' => ['No hay trabajadores en este periodo.']],
                ],
            ]);

        $this->assertSame(2, Periodo::where('estado', EstadoPeriodo::Calculado)->count());
    }

    #[Test]
    public function los_problemas_de_una_empresa_no_detienen_a_las_demas(): void
    {
        $admin = User::factory()->admin()->create();
        $this->empresaConPeriodo('Alfa S.A.C.');
        Parametro::where('clave', ClaveParametro::TasaOnp)->delete();

        $id = (string) str($this->actingAs($admin)
            ->post(route('calculos.store'), ['anio' => 2026, 'mes' => 11])
            ->headers->get('Location'))->after('calculo=');

        $this->getJson(route('calculos.show', $id))
            ->assertJsonPath('empresas.0.estado', 'con_problemas')
            ->assertJsonPath('empresas.0.problemas.0', 'Falta el parámetro «Aporte a la ONP» vigente en 11/2026.');
    }

    #[Test]
    public function asistente_solo_calcula_sus_empresas_y_solo_ve_sus_lotes(): void
    {
        $propia = $this->empresaConPeriodo('Propia S.A.C.');
        $this->empresaConPeriodo('Ajena S.A.C.');
        $asistente = User::factory()->asistente()->create();
        $asistente->empresas()->attach($propia);

        $id = (string) str($this->actingAs($asistente)
            ->post(route('calculos.store'), ['anio' => 2026, 'mes' => 11])
            ->headers->get('Location'))->after('calculo=');

        $this->getJson(route('calculos.show', $id))->assertJsonCount(1, 'empresas');
        $this->assertSame(EstadoPeriodo::Abierto, Periodo::whereHas('empresa', fn ($q) => $q->where('razon_social', 'Ajena S.A.C.'))->sole()->estado);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('calculos.show', $id))
            ->assertNotFound();
    }

    #[Test]
    public function valida_el_mes(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('calculos.store'), ['anio' => 2026, 'mes' => 13])
            ->assertSessionHasErrors('mes');
    }
}
