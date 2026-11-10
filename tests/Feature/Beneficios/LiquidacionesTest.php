<?php

namespace Tests\Feature\Beneficios;

use App\Enums\EstadoPeriodo;
use App\Enums\MotivoCese;
use App\Enums\RegimenLaboral;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Liquidación de beneficios sociales (cese 20/09/2026, régimen general, ONP). Datos ficticios.
 * Montos esperados calculados a mano desde la norma: ⚠ pendientes de validar con el contador.
 */
class LiquidacionesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 09:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044', 'regimen_laboral' => RegimenLaboral::General]);
        $this->admin = User::factory()->admin()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function cesado(array $datos = []): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => 'Cesado', 'fecha_ingreso' => '2025-01-01',
            'fecha_cese' => '2026-09-20', 'motivo_cese' => MotivoCese::Renuncia,
            'remuneracion_basica' => '3000.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'entidad_cts' => 'BCP', 'cuenta_cts' => '191-0000001-0-00', ...$datos,
        ]);
    }

    /** Planilla de julio calculada (paga la gratificación de enero–junio: 3000). */
    private function julioCalculado(Trabajador $t): void
    {
        $julio = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 7]);
        $julio->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->admin)->post(route('periodos.calcular', $julio))->assertSessionHasNoErrors();
    }

    private function setiembre(Trabajador $t): Periodo
    {
        $setiembre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 9]);
        $setiembre->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 20]);

        return $setiembre;
    }

    /** @return array<string, string> código => monto */
    private function conceptosDeLinea(Periodo $periodo, Trabajador $t): array
    {
        $linea = PlanillaLinea::query()->where('periodo_id', $periodo->id)->where('trabajador_id', $t->id)->with('conceptos.concepto')->sole();

        return $linea->conceptos->mapWithKeys(fn ($c) => [$c->concepto->codigo_plame => (string) $c->monto_pagado])->all();
    }

    #[Test]
    public function calcula_la_liquidacion_y_la_planilla_del_mes_del_cese_la_paga(): void
    {
        $t = $this->cesado();
        $this->julioCalculado($t);
        $setiembre = $this->setiembre($t);

        $this->actingAs($this->asistente)->post(route('liquidaciones.calcular', $t))->assertRedirect(route('liquidaciones.index'));

        $l = Liquidacion::query()->sole();
        $this->assertSame('1000.00', (string) $l->gratificacion_trunca);   // julio y agosto: 3000 × 2/6
        $this->assertSame('90.00', (string) $l->bonificacion_trunca);
        $this->assertSame('1361.11', (string) $l->cts);                   // (3000 + 500) × 140 / 360
        $this->assertSame('3000.00', (string) $l->vacaciones_no_gozadas); // año 2025 sin gozar (aún no vence)
        $this->assertSame('0.00', (string) $l->indemnizacion_vacacional);
        $this->assertSame('2166.67', (string) $l->vacaciones_truncas);    // enero–20/09/2026: 8 meses 20 días
        $this->assertSame('7617.78', (string) $l->total);

        // Planilla de setiembre: 20 días de sueldo + beneficios. ONP y EsSalud solo sobre lo remunerativo.
        $this->actingAs($this->admin)->post(route('periodos.calcular', $setiembre))->assertSessionHasNoErrors();
        $conceptos = $this->conceptosDeLinea($setiembre, $t);
        $this->assertSame('2000.00', $conceptos['0121']);
        $this->assertSame('1000.00', $conceptos['0407']);
        $this->assertSame('90.00', $conceptos['0313']);
        $this->assertSame('1361.11', $conceptos['0904']);
        $this->assertSame('3000.00', $conceptos['0118']);
        $this->assertSame('2166.67', $conceptos['0114']);
        $this->assertSame('931.67', $conceptos['0607']);  // 13 % de 2000 + 3000 + 2166.67
        $this->assertSame('645.00', $conceptos['0804']);  // 9 % de 7166.67
        $this->assertSame('8686.11', (string) PlanillaLinea::query()->where('periodo_id', $setiembre->id)->sole()->neto_pagar);
    }

    #[Test]
    public function recalcular_la_liquidacion_deja_la_planilla_del_mes_por_recalcular(): void
    {
        $t = $this->cesado();
        $setiembre = $this->setiembre($t);
        $this->actingAs($this->admin)->post(route('periodos.calcular', $setiembre));
        $this->assertSame(EstadoPeriodo::Calculado, $setiembre->fresh()?->estado);

        $this->post(route('liquidaciones.calcular', $t));

        $this->assertSame(EstadoPeriodo::Abierto, $setiembre->fresh()?->estado);
    }

    #[Test]
    public function con_la_planilla_del_cese_cerrada_no_se_calcula(): void
    {
        $t = $this->cesado();
        Periodo::factory()->for($this->empresa)->cerrado()->create(['anio' => 2026, 'mes' => 9]);

        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t))
            ->assertInertiaFlash('toast.message', 'La planilla de Septiembre 2026 está cerrada: reábrela para incluir la liquidación.');
        $this->assertSame(0, Liquidacion::query()->count());
    }

    #[Test]
    public function el_listado_muestra_los_cesados_y_su_liquidacion(): void
    {
        $t = $this->cesado();
        Trabajador::factory()->for($this->empresa)->create(['fecha_cese' => null]); // activo: no aparece
        $this->actingAs($this->asistente)->post(route('liquidaciones.calcular', $t));

        $this->get(route('liquidaciones.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('liquidaciones/Index')
                ->has('filas', 1)
                ->where('filas.0.cese', '20/09/2026')
                ->where('filas.0.liquidacion.total', '7617.78')
                ->where('filas.0.liquidacion.puede_cerrar', false)); // el asistente no cierra
    }

    #[Test]
    public function un_asistente_no_ve_ni_calcula_liquidaciones_de_otra_empresa(): void
    {
        $ajena = Empresa::factory()->create();
        $deOtra = Trabajador::factory()->for($ajena)->create(['fecha_cese' => '2026-09-20', 'motivo_cese' => MotivoCese::Renuncia]);
        $this->cesado();

        $this->actingAs($this->asistente)->get(route('liquidaciones.index'))
            ->assertInertia(fn (Assert $p) => $p->has('filas', 1)->where('filas.0.nombre', fn (string $n) => str_contains($n, 'Cesado')));

        $this->post(route('liquidaciones.calcular', $deOtra))->assertNotFound();
        $this->get(route('trabajadores.certificado', $deOtra))->assertNotFound();
        $this->assertSame(0, Liquidacion::query()->where('trabajador_id', $deOtra->id)->count());
    }

    #[Test]
    public function cerrar_y_reabrir_es_del_admin_y_cerrada_no_cambia(): void
    {
        $t = $this->cesado();
        $this->actingAs($this->asistente)->post(route('liquidaciones.calcular', $t));
        $l = Liquidacion::query()->sole();

        $this->post(route('liquidaciones.cerrar', $l))->assertForbidden();

        $this->actingAs($this->admin)->post(route('liquidaciones.cerrar', $l))->assertRedirect();
        $this->assertTrue($l->fresh()?->estaCerrada());

        // Cerrada: no se recalcula ni cambia el cese.
        $this->post(route('liquidaciones.calcular', $t))->assertInertiaFlash('toast.message', 'La liquidación está cerrada. Reábrela para recalcular.');
        $this->delete(route('trabajadores.cese.destroy', $t))->assertSessionHasErrors('cese');
        $this->assertNotNull($t->fresh()?->fecha_cese);

        $this->post(route('liquidaciones.reabrir', $l), ['motivo' => 'corto'])->assertSessionHasErrors('motivo');
        $this->post(route('liquidaciones.reabrir', $l), ['motivo' => 'Faltó considerar un día de falta'])->assertRedirect();
        $this->assertFalse($l->fresh()?->estaCerrada());

        $acciones = Auditoria::query()->where('auditable_type', 'liquidacion')->pluck('accion')->all();
        $this->assertSame(['creado', 'cerrado', 'reabierto'], $acciones);
    }

    #[Test]
    public function revertir_el_cese_descarta_la_liquidacion_en_borrador(): void
    {
        $t = $this->cesado();
        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t));
        $this->assertSame(1, Liquidacion::query()->count());

        $this->delete(route('trabajadores.cese.destroy', $t))->assertRedirect();

        $this->assertSame(0, Liquidacion::query()->count());
    }

    #[Test]
    public function genera_la_hoja_el_certificado_y_la_carta_de_cts(): void
    {
        $t = $this->cesado();
        $this->actingAs($this->admin)->post(route('liquidaciones.calcular', $t));
        $l = Liquidacion::query()->sole();

        foreach ([route('liquidaciones.pdf', $l), route('trabajadores.certificado', $t), route('liquidaciones.carta-cts', $l)] as $url) {
            $respuesta = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());
        }
    }
}
