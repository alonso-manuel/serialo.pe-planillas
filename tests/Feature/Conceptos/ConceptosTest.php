<?php

namespace Tests\Feature\Conceptos;

use App\Enums\TipoConcepto;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Conceptos de la tabla 22 SUNAT y su afectación.
 * Los de `sistema` los calcula el motor: no se borran, desactivan ni cambian afectaciones.
 */
class ConceptosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConceptoSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function propio(array $datos = []): Concepto
    {
        return Concepto::create([
            'codigo_plame' => '0304',
            'nombre' => 'Bonificación ficticia',
            'tipo' => TipoConcepto::Ingreso,
            'afecto_essalud' => false,
            'afecto_onp' => false,
            'afecto_afp' => false,
            'afecto_quinta' => false,
            'sistema' => false,
            'activo' => true,
            ...$datos,
        ]);
    }

    /** @return array<string, mixed> */
    private function afectaciones(bool $essalud = false, bool $onp = false, bool $afp = false, bool $quinta = false): array
    {
        return ['afecto_essalud' => $essalud, 'afecto_onp' => $onp, 'afecto_afp' => $afp, 'afecto_quinta' => $quinta];
    }

    #[Test]
    public function listado_agrupado_por_tipo_con_marca_de_sistema(): void
    {
        $this->actingAs($this->admin)
            ->get(route('conceptos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conceptos/Index')
                ->has('grupos', 4)
                ->where('grupos.0.tipo', 'ingreso')
                ->where('grupos.0.etiqueta', 'Ingresos')
                ->where('grupos.0.conceptos.0.codigo_plame', '0105')
                ->where('grupos.0.conceptos.0.sistema', true)
                ->where('grupos.0.conceptos.0.usado', false)
                ->where('grupos.1.tipo', 'descuento')
            );
    }

    #[Test]
    public function admin_crea_un_ingreso_y_el_tipo_se_deduce_del_codigo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('conceptos.store'), [
                'codigo_plame' => '0107',
                'nombre' => 'Trabajo en día feriado',
                ...$this->afectaciones(essalud: true, onp: true, afp: true, quinta: true),
            ])
            ->assertRedirect(route('conceptos.index'))
            ->assertSessionHasNoErrors();

        $c = Concepto::where('codigo_plame', '0107')->sole();
        $this->assertSame(TipoConcepto::Ingreso, $c->tipo);
        $this->assertTrue($c->afecto_essalud);
        $this->assertFalse($c->sistema);
        $this->assertTrue($c->activo);
    }

    #[Test]
    public function un_descuento_se_guarda_sin_afectaciones(): void
    {
        $this->actingAs($this->admin)->post(route('conceptos.store'), [
            'codigo_plame' => '0705',
            'nombre' => 'Inasistencias',
            ...$this->afectaciones(essalud: true, onp: true),
        ])->assertSessionHasNoErrors();

        $c = Concepto::where('codigo_plame', '0705')->sole();
        $this->assertSame(TipoConcepto::DescuentoTrabajador, $c->tipo);
        $this->assertFalse($c->afecto_essalud);
        $this->assertFalse($c->afecto_onp);
    }

    #[Test]
    public function no_se_crean_aportes_ni_codigos_invalidos_o_repetidos(): void
    {
        $this->actingAs($this->admin);

        foreach (['0610', '0805'] as $aporte) {
            $this->post(route('conceptos.store'), ['codigo_plame' => $aporte, 'nombre' => 'X', ...$this->afectaciones()])
                ->assertSessionHasErrors(['codigo_plame' => 'Los aportes (06xx y 08xx) los calcula el sistema; solo se crean ingresos y descuentos.']);
        }

        $this->post(route('conceptos.store'), ['codigo_plame' => '1500', 'nombre' => 'X', ...$this->afectaciones()])
            ->assertSessionHasErrors(['codigo_plame' => 'El código no corresponde a la tabla 22 (01xx–05xx, 07xx, 09xx o 10xx).']);

        $this->post(route('conceptos.store'), ['codigo_plame' => '12', 'nombre' => 'X', ...$this->afectaciones()])
            ->assertSessionHasErrors('codigo_plame');

        $this->post(route('conceptos.store'), ['codigo_plame' => '0121', 'nombre' => 'Duplicado', ...$this->afectaciones()])
            ->assertSessionHasErrors(['codigo_plame' => 'Ese código ya está registrado.']);

        $this->post(route('conceptos.store'), ['codigo_plame' => '0107', 'nombre' => '', ...$this->afectaciones()])
            ->assertSessionHasErrors('nombre');
    }

    #[Test]
    public function admin_edita_nombre_y_afectaciones_de_un_concepto_propio(): void
    {
        $c = $this->propio();

        $this->actingAs($this->admin)
            ->put(route('conceptos.update', $c), [
                'nombre' => 'Bonificación por productividad',
                ...$this->afectaciones(essalud: true, quinta: true),
            ])
            ->assertRedirect(route('conceptos.index'))
            ->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame('Bonificación por productividad', $c->nombre);
        $this->assertTrue($c->afecto_essalud);
        $this->assertTrue($c->afecto_quinta);
        $this->assertSame('0304', $c->codigo_plame, 'El código no cambia.');
    }

    #[Test]
    public function un_concepto_de_sistema_solo_cambia_de_nombre(): void
    {
        $basico = Concepto::where('codigo_plame', '0121')->sole();

        $this->actingAs($this->admin)
            ->put(route('conceptos.update', $basico), [
                'nombre' => 'Sueldo básico',
                ...$this->afectaciones(),
            ])
            ->assertSessionHasErrors(['afectaciones' => 'Las afectaciones de un concepto del sistema las fija el motor de cálculo.']);
        $this->assertTrue($basico->refresh()->afecto_essalud);

        $this->put(route('conceptos.update', $basico), [
            'nombre' => 'Sueldo básico',
            ...$this->afectaciones(true, true, true, true),
        ])->assertSessionHasNoErrors();
        $this->assertSame('Sueldo básico', $basico->refresh()->nombre);
    }

    #[Test]
    public function un_concepto_de_sistema_no_se_desactiva_ni_elimina(): void
    {
        $basico = Concepto::where('codigo_plame', '0121')->sole();
        $this->actingAs($this->admin);

        $this->patch(route('conceptos.estado', $basico))->assertRedirect();
        $this->delete(route('conceptos.destroy', $basico))->assertRedirect();

        $this->assertTrue($basico->refresh()->activo);
        $this->assertModelExists($basico);
    }

    #[Test]
    public function se_desactiva_y_reactiva_un_concepto_propio(): void
    {
        $c = $this->propio();
        $this->actingAs($this->admin);

        $this->patch(route('conceptos.estado', $c));
        $this->assertFalse($c->refresh()->activo);

        $this->patch(route('conceptos.estado', $c));
        $this->assertTrue($c->refresh()->activo);
    }

    #[Test]
    public function se_elimina_un_concepto_propio_sin_uso_pero_no_uno_usado(): void
    {
        $sinUso = $this->propio(['codigo_plame' => '0107']);
        $usado = $this->propio();
        $linea = Periodo::factory()->create()->lineas()->create(['trabajador_id' => Trabajador::factory()->create()->id]);
        $linea->novedades()->create(['concepto_id' => $usado->id, 'monto' => '100.00']);

        $this->actingAs($this->admin);

        $this->get(route('conceptos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('grupos.0.conceptos', fn ($conceptos) => collect($conceptos)
                ->firstWhere('codigo_plame', '0304')['usado'] === true));

        $this->delete(route('conceptos.destroy', $sinUso))->assertRedirect();
        $this->assertModelMissing($sinUso);

        $this->delete(route('conceptos.destroy', $usado))->assertRedirect();
        $this->assertModelExists($usado);
    }

    #[Test]
    public function la_afectacion_cambia_el_recalculo_pero_no_un_periodo_cerrado(): void
    {
        $this->seed(ParametroSeeder::class);
        $bono = $this->propio();
        $empresa = Empresa::factory()->create();
        $trabajador = Trabajador::factory()->for($empresa)->create(['remuneracion_basica' => '1500.00']);

        $abierto = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 10]);
        $cerrado = Periodo::factory()->for($empresa)->create(['anio' => 2026, 'mes' => 9]);
        foreach ([$abierto, $cerrado] as $periodo) {
            $periodo->lineas()->create(['trabajador_id' => $trabajador->id])
                ->novedades()->create(['concepto_id' => $bono->id, 'monto' => '100.00']);
        }

        $calcular = app(CalcularPlanillaPeriodo::class);
        $essalud = fn (Periodo $p) => $p->lineas()->sole()->conceptos()
            ->whereHas('concepto', fn ($q) => $q->where('codigo_plame', '0804'))->sole()->monto_devengado;

        $calcular->ejecutar($abierto);
        $calcular->ejecutar($cerrado);
        $cerrado->update(['estado' => 'cerrado']);
        $this->assertSame('135.00', $essalud($abierto), 'Bono no afecto: 9 % de 1,500.');

        $this->actingAs($this->admin)->put(route('conceptos.update', $bono), [
            'nombre' => $bono->nombre, ...$this->afectaciones(essalud: true),
        ])->assertSessionHasNoErrors();

        $calcular->ejecutar($abierto->refresh());
        $this->assertSame('144.00', $essalud($abierto), 'Bono afecto: 9 % de 1,600.');
        $this->assertSame('135.00', $essalud($cerrado), 'El periodo cerrado conserva su resultado.');
    }

    #[Test]
    public function asistente_no_gestiona_conceptos(): void
    {
        $c = $this->propio();
        $this->actingAs(User::factory()->asistente()->create());

        $this->get(route('conceptos.index'))->assertForbidden();
        $this->post(route('conceptos.store'), ['codigo_plame' => '0107', 'nombre' => 'X', ...$this->afectaciones()])->assertForbidden();
        $this->put(route('conceptos.update', $c), ['nombre' => 'X', ...$this->afectaciones()])->assertForbidden();
        $this->patch(route('conceptos.estado', $c))->assertForbidden();
        $this->delete(route('conceptos.destroy', $c))->assertForbidden();
    }
}
