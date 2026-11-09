<?php

namespace Tests\Feature\Periodos;

use App\Enums\RegimenLaboral;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Pantallas del menú Boletas y Gratificaciones (empresa activa). Datos ficticios. */
class AccesosMenuTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-10 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['regimen_laboral' => RegimenLaboral::General]);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function calculado(Empresa $empresa, int $mes): Periodo
    {
        $periodo = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => $mes]);
        $t = Trabajador::factory()->for($empresa)->create([
            'fecha_ingreso' => '2024-01-01', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'email' => 'prueba@ejemplo.test',
        ]);
        $periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->withSession(['empresa_id' => $empresa->id])->post(route('periodos.calcular', $periodo));

        return $periodo;
    }

    #[Test]
    public function boletas_lista_los_meses_calculados_de_la_empresa_activa(): void
    {
        $this->calculado($this->empresa, 11);
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12]); // sin calcular

        $otra = Empresa::factory()->create();
        $this->calculado($otra, 11);

        $this->actingAs($this->asistente)->withSession(['empresa_id' => $this->empresa->id])->get(route('boletas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('boletas/Index')
                ->has('periodos', 1)
                ->where('periodos.0.etiqueta', 'Noviembre 2026')
                ->where('periodos.0.boletas', 1)
                ->where('periodos.0.con_correo', 1)
                ->where('periodos.0.enviadas', 0));
    }

    #[Test]
    public function gratificaciones_muestra_julio_y_diciembre_con_sus_montos(): void
    {
        $this->calculado($this->empresa, 12);
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 7]);

        $this->actingAs($this->asistente)->withSession(['empresa_id' => $this->empresa->id])->get(route('gratificaciones.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('gratificaciones/Index')
                ->where('aplica', true)
                ->has('periodos', 2)
                ->where('periodos.0.etiqueta', 'Diciembre 2026')
                ->where('periodos.0.calculada', true)
                ->where('periodos.0.gratificacion', '1500.00')
                ->where('periodos.0.bonificacion', '135.00')
                ->where('periodos.1.calculada', false));
    }

    #[Test]
    public function en_microempresa_indica_que_no_corresponde(): void
    {
        $this->empresa->update(['regimen_laboral' => RegimenLaboral::Microempresa, 'seguro_salud' => 'sis']);

        $this->actingAs($this->asistente)->get(route('gratificaciones.index'))
            ->assertInertia(fn (Assert $page) => $page->where('aplica', false));
    }
}
