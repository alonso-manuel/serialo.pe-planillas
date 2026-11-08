<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\MotivoCese;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cese del trabajador: fecha y motivo (tabla 17 de T-Registro). Datos ficticios. */
class CeseTrabajadorTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2026-01-02', 'apellido_paterno' => 'Rojas',
        ]);
    }

    private function cesar(string $fecha, string $motivo = '01')
    {
        return $this->actingAs($this->asistente)->post(
            route('trabajadores.cese.store', $this->trabajador),
            ['fecha_cese' => $fecha, 'motivo_cese' => $motivo],
        );
    }

    #[Test]
    public function registra_el_cese_con_fecha_y_motivo(): void
    {
        $this->cesar('2026-10-31', '01')->assertRedirect()->assertSessionHasNoErrors();

        $t = $this->trabajador->refresh();
        $this->assertSame('2026-10-31', $t->fecha_cese->toDateString());
        $this->assertSame(MotivoCese::Renuncia, $t->motivo_cese);
        $this->assertTrue($t->estaCesado());

        $this->get(route('trabajadores.index', ['estado' => 'cesados']))
            ->assertInertia(fn (Assert $page) => $page->has('trabajadores.data', 1));
        $this->assertTrue(Auditoria::where('auditable_type', 'trabajador')->where('accion', 'actualizado')->exists());
    }

    #[Test]
    public function un_cese_futuro_mantiene_activo_al_trabajador_hasta_esa_fecha(): void
    {
        $this->cesar('2026-12-31', '07')->assertSessionHasNoErrors();

        $this->assertFalse($this->trabajador->refresh()->estaCesado());
        $this->get(route('trabajadores.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('trabajadores.data', 1)
                ->where('trabajadores.data.0.cesado', false)
                ->where('trabajadores.data.0.fecha_cese', '31/12/2026'));
    }

    #[Test]
    public function validaciones_del_cese(): void
    {
        $this->cesar('2025-12-31')
            ->assertSessionHasErrors(['fecha_cese' => 'El cese no puede ser anterior al ingreso (02/01/2026).']);

        $this->cesar('2026-10-31', '99')->assertSessionHasErrors('motivo_cese');
        $this->cesar('', '01')->assertSessionHasErrors('fecha_cese');

        $this->assertNull($this->trabajador->refresh()->fecha_cese);
    }

    #[Test]
    public function no_se_cesa_si_figura_en_una_planilla_posterior(): void
    {
        Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11])
            ->lineas()->create(['trabajador_id' => $this->trabajador->id]);

        $this->cesar('2026-10-31')
            ->assertSessionHasErrors(['fecha_cese' => 'Figura en la planilla de 11/2026, posterior al cese. Retíralo de ese periodo primero.']);

        // En el mismo mes del cese sí puede figurar (trabaja parte del mes).
        $this->cesar('2026-11-10')->assertSessionHasNoErrors();
    }

    #[Test]
    public function se_revierte_un_cese_registrado_por_error(): void
    {
        $this->cesar('2026-10-31', '03');

        $this->delete(route('trabajadores.cese.destroy', $this->trabajador))->assertRedirect();

        $t = $this->trabajador->refresh();
        $this->assertNull($t->fecha_cese);
        $this->assertNull($t->motivo_cese);
    }

    #[Test]
    public function no_se_registran_cambios_de_historial_despues_del_cese(): void
    {
        $this->cesar('2026-08-20');

        $this->post(route('trabajadores.cambios.store', $this->trabajador), [
            'grupo' => 'cargo', 'vigente_desde' => '2026-09', 'cargo' => 'Jefe',
        ])->assertSessionHasErrors(['vigente_desde' => 'El trabajador cesó el 20/08/2026: no se registran cambios posteriores.']);

        $this->post(route('trabajadores.cambios.store', $this->trabajador), [
            'grupo' => 'cargo', 'vigente_desde' => '2026-08', 'cargo' => 'Jefe',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function la_ficha_muestra_el_cese_y_los_motivos(): void
    {
        $this->cesar('2026-10-31', '08');

        $this->get(route('trabajadores.edit', $this->trabajador))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trabajador.cese.fecha', '31/10/2026')
                ->where('trabajador.cese.motivo', '08')
                ->where('trabajador.cese.motivo_etiqueta', 'Mutuo disenso')
                ->has('catalogos.motivos_cese', count(MotivoCese::cases()))
            );
    }

    #[Test]
    public function asistente_no_cesa_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();

        $this->actingAs($this->asistente)
            ->post(route('trabajadores.cese.store', $ajeno), ['fecha_cese' => '2026-10-31', 'motivo_cese' => '01'])
            ->assertForbidden();
        $this->delete(route('trabajadores.cese.destroy', $ajeno))->assertForbidden();

        $this->assertNull($ajeno->refresh()->fecha_cese);
    }
}
