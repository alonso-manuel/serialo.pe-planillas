<?php

namespace Tests\Feature\Parametros;

use App\Enums\ClaveParametro;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Planilla\ParametrosRepositorio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Parámetros legales con vigencia (regla 3 de CLAUDE.md): nunca se editan;
 * un cambio legal es una vigencia nueva. Valores de prueba ficticios.
 */
class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->admin = User::factory()->admin()->create();
    }

    private function vigencia(ClaveParametro $clave, string $valor, string $desde, ?string $base = 'Norma ficticia'): Parametro
    {
        return Parametro::create([
            'clave' => $clave, 'valor' => $valor, 'vigente_desde' => $desde, 'base_legal' => $base,
        ]);
    }

    #[Test]
    public function listado_muestra_por_clave_el_valor_vigente_el_proximo_y_el_historial(): void
    {
        $this->vigencia(ClaveParametro::Rmv, '1025.00', '2022-05-01');
        $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        $this->vigencia(ClaveParametro::Rmv, '1200.00', '2027-01-01');
        $this->vigencia(ClaveParametro::TasaEssalud, '0.09', '2000-01-01', 'Ley 26790 — VERIFICAR');

        $this->actingAs($this->admin)
            ->get(route('parametros.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/Index')
                ->has('claves', count(ClaveParametro::cases()))
                ->where('claves.0.clave', 'rmv')
                ->where('claves.0.etiqueta', 'Remuneración Mínima Vital (RMV)')
                ->where('claves.0.tipo', 'monto')
                ->where('claves.0.vigente.valor_mostrado', 'S/ 1,130.00')
                ->where('claves.0.vigente.vigente_desde', '2025-01-01')
                ->where('claves.0.proxima.valor_mostrado', 'S/ 1,200.00')
                ->has('claves.0.historial', 3)
                ->where('claves.0.historial.0.vigente_desde', '2027-01-01')
                ->where('claves.2.clave', 'tasa_essalud')
                ->where('claves.2.vigente.valor_mostrado', '9.00 %')
                ->where('claves.2.vigente.verificar', true)
                ->where('claves.1.vigente', null)
            );
    }

    #[Test]
    public function admin_registra_una_nueva_vigencia_de_monto(): void
    {
        $this->actingAs($this->admin)
            ->post(route('parametros.store'), [
                'clave' => 'rmv',
                'valor' => '1200.00',
                'vigente_desde' => '2027-01',
                'base_legal' => 'D.S. ficticio 001-2026-TR',
            ])
            ->assertRedirect(route('parametros.index'))
            ->assertSessionHasNoErrors();

        $p = Parametro::sole();
        $this->assertSame(ClaveParametro::Rmv, $p->clave);
        $this->assertSame('1200.0000', $p->valor);
        $this->assertSame('2027-01-01', $p->vigente_desde->toDateString());
    }

    #[Test]
    public function las_tasas_se_ingresan_en_porcentaje(): void
    {
        $this->actingAs($this->admin)
            ->post(route('parametros.store'), [
                'clave' => 'tasa_onp',
                'valor' => '13.5',
                'vigente_desde' => '2027-01',
                'base_legal' => 'Norma ficticia',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.1350', Parametro::sole()->valor);
    }

    #[Test]
    public function validaciones_de_nueva_vigencia(): void
    {
        $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        $this->actingAs($this->admin);

        $this->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1130.005', 'vigente_desde' => '2027-01', 'base_legal' => 'X',
        ])->assertSessionHasErrors(['valor' => 'El monto debe ser mayor que cero y tener como máximo 2 decimales.']);

        $this->post(route('parametros.store'), [
            'clave' => 'tasa_essalud', 'valor' => '120', 'vigente_desde' => '2027-01', 'base_legal' => 'X',
        ])->assertSessionHasErrors(['valor' => 'La tasa debe estar entre 0 % y 100 %, con hasta 2 decimales.']);

        $this->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1200', 'vigente_desde' => '2025-01', 'base_legal' => 'X',
        ])->assertSessionHasErrors(['vigente_desde' => 'Ya existe una vigencia de este parámetro desde esa fecha.']);

        $this->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1200', 'vigente_desde' => '2027-01-15', 'base_legal' => 'X',
        ])->assertSessionHasErrors('vigente_desde');

        $this->post(route('parametros.store'), [
            'clave' => 'inventado', 'valor' => '1', 'vigente_desde' => '2027-01', 'base_legal' => '',
        ])->assertSessionHasErrors(['clave', 'base_legal']);

        $this->assertSame(1, Parametro::count());
    }

    #[Test]
    public function las_vigencias_no_se_editan(): void
    {
        $p = $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');

        $this->actingAs($this->admin)
            ->put('/parametros/'.$p->id, ['valor' => '9999'])
            ->assertMethodNotAllowed();

        $this->assertSame('1130.0000', $p->refresh()->valor);
    }

    #[Test]
    public function se_anula_una_vigencia_que_ningun_periodo_cerrado_uso(): void
    {
        $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        $erronea = $this->vigencia(ClaveParametro::Rmv, '11300.00', '2026-11-01');

        // Un periodo abierto o calculado sí puede haberla usado: se recalculará.
        Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);
        // Uno cerrado anterior a la vigencia no la usó.
        Periodo::factory()->for(Empresa::factory())->cerrado()->create(['anio' => 2026, 'mes' => 10]);

        $this->actingAs($this->admin)
            ->delete(route('parametros.destroy', $erronea))
            ->assertRedirect();

        $this->assertModelMissing($erronea);
    }

    #[Test]
    public function no_se_anula_una_vigencia_usada_por_un_periodo_cerrado(): void
    {
        $usada = $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        $this->vigencia(ClaveParametro::Rmv, '1200.00', '2026-12-01');
        Periodo::factory()->for(Empresa::factory())->cerrado()->create(['anio' => 2026, 'mes' => 3]);

        $this->actingAs($this->admin)
            ->get(route('parametros.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('claves.0.historial.1.id', $usada->id)
                ->where('claves.0.historial.1.anulable', false)
                ->where('claves.0.historial.0.anulable', true)
            );

        $this->delete(route('parametros.destroy', $usada))->assertRedirect();

        $this->assertModelExists($usada);
    }

    #[Test]
    public function un_periodo_cerrado_posterior_a_la_siguiente_vigencia_no_bloquea(): void
    {
        $antigua = $this->vigencia(ClaveParametro::Rmv, '1025.00', '2022-05-01');
        $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        // Cerrado en 2026: usó la de 2025, no la de 2022.
        Periodo::factory()->for(Empresa::factory())->cerrado()->create(['anio' => 2026, 'mes' => 2]);

        $this->actingAs($this->admin)->delete(route('parametros.destroy', $antigua));

        $this->assertModelMissing($antigua);
    }

    #[Test]
    public function un_periodo_antiguo_se_sigue_calculando_con_su_valor(): void
    {
        foreach (ClaveParametro::cases() as $clave) {
            $this->vigencia($clave, $clave->tipo() === 'tasa' ? '0.10' : '100.00', '2020-01-01');
        }
        $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');

        $this->actingAs($this->admin)->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1200.00', 'vigente_desde' => '2027-01', 'base_legal' => 'Norma ficticia',
        ])->assertSessionHasNoErrors();

        $repositorio = app(ParametrosRepositorio::class);
        $empresa = Empresa::factory()->create();

        $this->assertSame('1130.0000', $repositorio->delPeriodo(Periodo::factory()->for($empresa)->make(['anio' => 2026, 'mes' => 12]))->rmv);
        $this->assertSame('1200.0000', $repositorio->delPeriodo(Periodo::factory()->for($empresa)->make(['anio' => 2027, 'mes' => 1]))->rmv);
    }

    #[Test]
    public function asistente_no_gestiona_parametros(): void
    {
        $p = $this->vigencia(ClaveParametro::Rmv, '1130.00', '2025-01-01');
        $asistente = User::factory()->asistente()->create();

        $this->actingAs($asistente);
        $this->get(route('parametros.index'))->assertForbidden();
        $this->post(route('parametros.store'), [
            'clave' => 'rmv', 'valor' => '1', 'vigente_desde' => '2027-01', 'base_legal' => 'X',
        ])->assertForbidden();
        $this->delete(route('parametros.destroy', $p))->assertForbidden();

        $this->assertSame(1, Parametro::count());
    }
}
