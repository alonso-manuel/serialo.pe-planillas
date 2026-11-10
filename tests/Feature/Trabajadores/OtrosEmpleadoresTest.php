<?php

namespace Tests\Feature\Trabajadores;

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\RentaOtroEmpleador;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Certificados de otros empleadores (quinta categoría) en la ficha del trabajador. Datos ficticios. */
class OtrosEmpleadoresTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    /** @return array<string, string> */
    private function certificado(array $datos = []): array
    {
        return ['anio' => '2026', 'ruc_empleador' => '20000000001', 'empleador' => 'Empleador Ficticio S.A.C.', 'renta' => '16000.00', 'retencion' => '300.00', ...$datos];
    }

    #[Test]
    public function registra_lista_y_quita_un_certificado_con_auditoria(): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create();

        $this->actingAs($this->asistente)->post(route('trabajadores.otros-empleadores.store', $t), $this->certificado())->assertSessionHasNoErrors();

        $renta = RentaOtroEmpleador::query()->sole();
        $this->assertSame('16000.00', $renta->renta);
        $this->assertSame('300.00', $renta->retencion);

        $this->get(route('trabajadores.edit', $t))->assertInertia(fn (Assert $p) => $p
            ->has('otros_empleadores', 1)
            ->where('otros_empleadores.0.empleador', 'Empleador Ficticio S.A.C.'));

        $this->delete(route('trabajadores.otros-empleadores.destroy', [$t, $renta]))->assertRedirect();
        $this->assertSame(0, RentaOtroEmpleador::query()->count());

        $this->assertSame(['creado', 'eliminado'], Auditoria::query()->where('auditable_type', 'renta_otro_empleador')->orderBy('id')->pluck('accion')->all());
        $this->assertSame($this->empresa->id, Auditoria::query()->where('auditable_type', 'renta_otro_empleador')->value('empresa_id'));
    }

    #[Test]
    public function valida_montos_y_ruc(): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create();

        $this->actingAs($this->asistente)
            ->post(route('trabajadores.otros-empleadores.store', $t), $this->certificado(['ruc_empleador' => '123', 'renta' => '100.00', 'retencion' => '200.00']))
            ->assertSessionHasErrors(['ruc_empleador', 'retencion' => 'La retención no puede ser mayor que la renta.']);
        $this->assertSame(0, RentaOtroEmpleador::query()->count());
    }

    #[Test]
    public function no_ve_ni_toca_certificados_de_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();
        $renta = RentaOtroEmpleador::query()->create(['trabajador_id' => $ajeno->id, 'anio' => 2026, 'renta' => '1000.00', 'retencion' => '0']);

        $this->actingAs($this->asistente)->post(route('trabajadores.otros-empleadores.store', $ajeno), $this->certificado())->assertForbidden();
        $this->delete(route('trabajadores.otros-empleadores.destroy', [$ajeno, $renta]))->assertForbidden();
        $this->assertSame(1, RentaOtroEmpleador::query()->count());

        // Ni mezclando un trabajador propio con el certificado ajeno.
        $propio = Trabajador::factory()->for($this->empresa)->create();
        $this->delete(route('trabajadores.otros-empleadores.destroy', [$propio, $renta]))->assertNotFound();
        $this->assertSame(1, RentaOtroEmpleador::query()->count());
    }
}
