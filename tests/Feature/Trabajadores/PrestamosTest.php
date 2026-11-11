<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\EstadoPeriodo;
use App\Models\Auditoria;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Prestamo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Préstamos en cuotas: cronograma, descuento automático (0706) en la planilla y estado de cuenta. Datos ficticios. */
class PrestamosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null]);
    }

    private function prestar(array $datos = [])
    {
        return $this->actingAs($this->asistente)->post(route('trabajadores.prestamos.store', $this->trabajador), [
            'fecha' => '2026-11-05', 'monto' => '1000.00', 'numero_cuotas' => 3, 'mes_inicio' => '2026-11', 'glosa' => 'Préstamo ficticio', ...$datos,
        ]);
    }

    private function calcularMes(int $mes, int $anio = 2026): Periodo
    {
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => $anio, 'mes' => $mes]);
        $periodo->lineas()->create(['trabajador_id' => $this->trabajador->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        return $periodo->refresh();
    }

    private function descuento(Periodo $periodo): ?string
    {
        $c = $periodo->lineas()->sole()->conceptos()->whereHas('concepto', fn ($q) => $q->where('codigo_plame', '0706'))->first();

        return $c === null ? null : (string) $c->monto_pagado;
    }

    #[Test]
    public function registra_el_cronograma_y_descuenta_la_cuota_de_cada_mes(): void
    {
        $this->prestar()->assertSessionHasNoErrors();
        $this->assertSame(['333.33', '333.33', '333.34'], Prestamo::query()->sole()->cuotas->pluck('monto')->map(fn ($m) => (string) $m)->all());
        $this->assertSame('creado', Auditoria::query()->where('auditable_type', 'prestamo')->value('accion'));

        $noviembre = $this->calcularMes(11);
        $this->assertSame('333.33', $this->descuento($noviembre));
        $this->assertSame('971.67', (string) $noviembre->lineas()->sole()->neto_pagar); // 1,500 − ONP 195 − 333.33

        // Estado de cuenta: al cerrar noviembre, esa cuota cuenta como descontada.
        $noviembre->update(['estado' => EstadoPeriodo::Cerrado]);
        $this->get(route('trabajadores.edit', $this->trabajador))->assertInertia(fn (Assert $p) => $p
            ->where('prestamos.0.saldo', '666.67')
            ->where('prestamos.0.cuotas.0.descontada', true)
            ->where('prestamos.0.eliminable', false));

        $this->assertSame('333.33', $this->descuento($this->calcularMes(12)));
        $this->assertSame('333.34', $this->descuento($this->calcularMes(1, 2027)));
    }

    #[Test]
    public function el_0706_registrado_a_mano_reemplaza_la_cuota(): void
    {
        $this->prestar()->assertSessionHasNoErrors();
        $periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
        $linea = $periodo->lineas()->create(['trabajador_id' => $this->trabajador->id, 'dias_laborados' => 30]);
        $linea->novedades()->create(['concepto_id' => Concepto::query()->where('codigo_plame', '0706')->value('id'), 'monto' => '100.00']);

        $this->post(route('periodos.calcular', $periodo))->assertSessionHasNoErrors();

        $this->assertSame('100.00', $this->descuento($periodo));
    }

    #[Test]
    public function no_empieza_en_un_mes_cerrado_ni_se_elimina_con_cuotas_descontadas(): void
    {
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11, 'estado' => EstadoPeriodo::Cerrado]);

        $this->prestar()->assertSessionHasErrors(['mes_inicio' => 'La planilla de Noviembre 2026 ya está cerrada: la primera cuota debe ser en un mes posterior.']);

        $this->prestar(['mes_inicio' => '2026-12'])->assertSessionHasNoErrors();
        $prestamo = Prestamo::query()->sole();
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12, 'estado' => EstadoPeriodo::Cerrado]);

        $this->delete(route('trabajadores.prestamos.destroy', [$this->trabajador, $prestamo]))
            ->assertInertiaFlash('toast.message', 'Ya se descontaron cuotas en planillas cerradas: no se puede eliminar el préstamo.');
        $this->assertSame(1, Prestamo::query()->count());
    }

    #[Test]
    public function no_toca_prestamos_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();

        $this->actingAs($this->asistente)->post(route('trabajadores.prestamos.store', $ajeno), [
            'fecha' => '2026-11-05', 'monto' => '500', 'numero_cuotas' => 1, 'mes_inicio' => '2026-12',
        ])->assertForbidden();
        $this->assertSame(0, Prestamo::query()->count());
    }
}
