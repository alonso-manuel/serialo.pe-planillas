<?php

namespace Tests\Feature\Beneficios;

use App\Enums\RegimenLaboral;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\LiquidacionCts;
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
use ZipArchive;

/** CTS de mayo 2027 (periodo noviembre 2026 – abril 2027). Datos ficticios. */
class CtsTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2027-05-05 09:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044', 'regimen_laboral' => RegimenLaboral::General]);
        $this->admin = User::factory()->admin()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    private function trabajador(array $datos = []): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => 'Alfa', 'fecha_ingreso' => '2024-01-01', 'fecha_cese' => null,
            'remuneracion_basica' => '1500.00', 'asignacion_familiar' => false,
            'sistema_pension' => 'onp', 'afp_id' => null, 'entidad_cts' => 'BCP', 'cuenta_cts' => '191-0000001-0-00', ...$datos,
        ]);
    }

    private function calcular(): void
    {
        $this->actingAs($this->asistente)->post(route('cts.calcular'), ['anio' => 2027, 'mes' => 5])->assertRedirect();
    }

    private function liquidacion(): LiquidacionCts
    {
        return LiquidacionCts::query()->with('lineas')->sole();
    }

    #[Test]
    public function usa_la_gratificacion_pagada_en_diciembre(): void
    {
        $t = $this->trabajador();
        $diciembre = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 12]);
        $diciembre->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $diciembre));

        $this->calcular();

        $linea = $this->liquidacion()->lineas->sole();
        $this->assertSame('250.00', (string) $linea->sexto_gratificacion);  // 1500 / 6
        $this->assertSame('1750.00', (string) $linea->remuneracion_computable);
        $this->assertSame('875.00', (string) $linea->monto);
        $this->assertSame('BCP', $linea->entidad_cts);
        $this->assertStringContainsString('Sin planilla en el sistema', implode(' ', $linea->avisos ?? []));
    }

    #[Test]
    public function sin_planilla_de_diciembre_estima_la_gratificacion_y_avisa(): void
    {
        $this->trabajador();

        $this->calcular();

        $linea = $this->liquidacion()->lineas->sole();
        $this->assertSame('875.00', (string) $linea->monto);
        $this->assertStringContainsString('Gratificación de diciembre 2026 estimada', implode(' ', $linea->avisos ?? []));
    }

    #[Test]
    public function las_faltas_de_las_planillas_del_periodo_se_descuentan(): void
    {
        $t = $this->trabajador();
        $enero = Periodo::factory()->for($this->empresa)->create(['anio' => 2027, 'mes' => 1]);
        $enero->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 27, 'dias_no_laborados' => 3]);

        $this->calcular();

        $linea = $this->liquidacion()->lineas->sole();
        $this->assertSame(3, $linea->dias_descontados);
        $this->assertSame('860.42', (string) $linea->monto);              // 1750 × 177 / 360
    }

    #[Test]
    public function excluye_cesados_antes_del_cierre_e_ingresos_posteriores(): void
    {
        $this->trabajador();
        $this->trabajador(['apellido_paterno' => 'Cesado', 'fecha_cese' => '2027-03-15', 'motivo_cese' => '01']);
        $this->trabajador(['apellido_paterno' => 'Nuevo', 'fecha_ingreso' => '2027-05-02']);

        $this->calcular();

        $this->assertSame(1, $this->liquidacion()->lineas->count());
    }

    #[Test]
    public function pequena_empresa_recibe_la_mitad(): void
    {
        $this->empresa->update(['regimen_laboral' => RegimenLaboral::PequenaEmpresa]);
        $this->trabajador();
        $this->calcular();

        // En pequeña empresa la gratificación también es la mitad: sexto 125 → (1500 + 125) / 2 × 0.5
        $this->assertSame('406.25', (string) $this->liquidacion()->lineas->sole()->monto);
    }

    #[Test]
    public function microempresa_no_tiene_cts(): void
    {
        $this->empresa->update(['regimen_laboral' => RegimenLaboral::Microempresa, 'seguro_salud' => 'sis']);
        $this->trabajador();
        $this->calcular();

        $this->assertSame(0, $this->liquidacion()->lineas->count());
    }

    #[Test]
    public function el_admin_cierra_y_ya_no_se_recalcula_hasta_reabrir(): void
    {
        $this->trabajador();
        $this->calcular();
        $liquidacion = $this->liquidacion();

        $this->actingAs($this->asistente)->post(route('cts.cerrar', $liquidacion))->assertForbidden();
        $this->actingAs($this->admin)->post(route('cts.cerrar', $liquidacion))->assertRedirect();
        $this->assertTrue($liquidacion->refresh()->estaCerrada());

        $this->actingAs($this->asistente)->post(route('cts.calcular'), ['anio' => 2027, 'mes' => 5])->assertForbidden();

        $this->actingAs($this->admin)->post(route('cts.reabrir', $liquidacion), ['motivo' => 'Corrección de la cuenta de un trabajador'])
            ->assertRedirect();
        $this->assertFalse($liquidacion->refresh()->estaCerrada());
        $this->assertSame(['cerrado', 'reabierto'], Auditoria::query()->where('auditable_type', 'liquidacion_cts')
            ->whereIn('accion', ['cerrado', 'reabierto'])->orderBy('id')->pluck('accion')->all());
    }

    #[Test]
    public function constancias_en_pdf_y_zip(): void
    {
        $this->trabajador();
        $this->trabajador(['apellido_paterno' => 'Beta', 'numero_documento' => '70000099']);
        $this->calcular();
        $liquidacion = $this->liquidacion();

        $pdf = $this->get(route('cts.constancia', [$liquidacion, $liquidacion->lineas->first()]))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $zip = $this->get(route('cts.constancias.zip', $liquidacion))->assertOk();
        $archivo = new ZipArchive;
        $archivo->open($zip->baseResponse->getFile()->getPathname());
        $this->assertSame(2, $archivo->numFiles);
        $archivo->close();

        $this->get(route('cts.constancias.pdf', $liquidacion))->assertOk();
    }

    #[Test]
    public function la_pantalla_muestra_el_deposito_vigente(): void
    {
        $this->trabajador();
        $this->calcular();

        $this->get(route('cts.index'))->assertInertia(fn (Assert $page) => $page
            ->component('cts/Index')
            ->where('anio', 2027)->where('mes', 5)
            ->where('periodo_computable', 'noviembre 2026 – abril 2027')
            ->where('liquidacion.total', '875.00')
            ->where('liquidacion.puede_cerrar', false)
            ->has('lineas', 1));
    }

    #[Test]
    public function un_usuario_de_otra_empresa_no_ve_la_cts(): void
    {
        $this->trabajador();
        $this->calcular();
        $liquidacion = $this->liquidacion();
        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($intruso);
        $this->get(route('cts.constancias.pdf', $liquidacion))->assertForbidden();
        $this->get(route('cts.constancia', [$liquidacion, $liquidacion->lineas->first()]))->assertForbidden();
        $this->post(route('cts.cerrar', $liquidacion))->assertForbidden();
    }

    #[Test]
    public function la_ficha_guarda_la_entidad_de_la_cuenta_cts(): void
    {
        $t = $this->trabajador(['entidad_cts' => null]);

        $this->actingAs($this->asistente)->get(route('trabajadores.edit', $t))
            ->assertInertia(fn (Assert $page) => $page->where('catalogos.entidades_cts', fn ($e) => collect($e)->contains('value', 'CMAC_AREQUIPA')));
    }
}
