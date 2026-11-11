<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PrestadorServicio;
use App\Models\ReciboHonorarios;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Sunat\ExportacionPlame;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Recibos por honorarios (cuarta categoría) del periodo y su exportación al PLAME. Datos ficticios. */
class HonorariosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-25 10:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    /** @return array<string, mixed> */
    private function recibo(array $datos = []): array
    {
        return [
            'tipo_documento' => '06', 'numero_documento' => '10456789012', 'apellido_paterno' => 'Pérez', 'apellido_materno' => 'López',
            'nombres' => 'Juan', 'domiciliado' => true, 'suspension' => false, 'tipo_comprobante' => 'R', 'serie' => 'E001',
            'numero' => '123', 'fecha_emision' => '2026-11-05', 'fecha_pago' => '2026-11-20', 'monto' => '2000.00', ...$datos,
        ];
    }

    private function registrar(array $datos = [])
    {
        return $this->actingAs($this->asistente)->post(route('periodos.honorarios.store', $this->periodo), $this->recibo($datos));
    }

    #[Test]
    public function registra_recibos_con_la_retencion_que_corresponde(): void
    {
        $this->registrar()->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'Recibo registrado. Retención de cuarta: S/ 160.00.');
        $this->registrar(['numero' => '124', 'monto' => '1500.00'])->assertSessionHasNoErrors(); // no supera el mínimo
        $this->registrar(['numero_documento' => '10777777771', 'numero' => '9', 'monto' => '3000.00', 'suspension' => true])->assertSessionHasNoErrors();

        $this->assertSame(['160.00', '0.00', '0.00'], ReciboHonorarios::query()->orderBy('id')->pluck('retencion')->map(fn ($v) => (string) $v)->all());
        $this->assertSame(2026, PrestadorServicio::query()->where('numero_documento', '10777777771')->value('suspension_anio'));

        $this->get(route('periodos.honorarios.index', $this->periodo))->assertInertia(fn (Assert $p) => $p
            ->component('periodos/Honorarios')->has('recibos', 3)
            ->where('totales', ['monto' => '6500.00', 'retencion' => '160.00'])
            ->has('prestadores', 2));
    }

    #[Test]
    public function valida_ruc_fecha_dentro_del_mes_y_comprobante_repetido(): void
    {
        $this->registrar(['numero_documento' => '20123456789'])->assertSessionHasErrors(['numero_documento' => 'El RUC de una persona natural empieza con 10, 15 o 17.']);
        $this->registrar(['fecha_pago' => '2026-12-01'])->assertSessionHasErrors(['fecha_pago' => 'La fecha de pago debe estar dentro de Noviembre 2026.']);
        $this->registrar()->assertSessionHasNoErrors();
        $this->registrar(['numero' => '00123'])->assertSessionHasErrors(['numero' => 'Ese comprobante ya está registrado.']);

        $this->assertSame(1, ReciboHonorarios::query()->count());
    }

    #[Test]
    public function con_el_periodo_cerrado_no_se_modifica(): void
    {
        $this->registrar()->assertSessionHasNoErrors();
        $recibo = ReciboHonorarios::query()->sole();
        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);

        $this->registrar(['numero' => '200'])->assertForbidden();
        $this->delete(route('periodos.honorarios.destroy', [$this->periodo, $recibo]))->assertForbidden();
        $this->assertSame(1, ReciboHonorarios::query()->count());
    }

    #[Test]
    public function el_plame_incluye_los_archivos_ps4_y_4ta(): void
    {
        $t = Trabajador::factory()->for($this->empresa)->create(['tipo_documento' => '01', 'numero_documento' => '70000001', 'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null]);
        $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
        $this->registrar()->assertSessionHasNoErrors();
        $this->post(route('periodos.calcular', $this->periodo));
        $this->periodo->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);

        $archivos = app(ExportacionPlame::class)->archivos($this->periodo->refresh());

        $this->assertSame("06|10456789012|Pérez|López|Juan|1|0|\r\n", $archivos['060120261120000000044.ps4']);
        $this->assertSame("06|10456789012|R|E001|123|2000.00|05/11/2026|20/11/2026|1|3||\r\n", $archivos['060120261120000000044.4ta']);
    }

    #[Test]
    public function no_accede_a_los_recibos_de_otra_empresa(): void
    {
        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);

        $this->actingAs($this->asistente)->get(route('periodos.honorarios.index', $ajeno))->assertForbidden();
        $this->post(route('periodos.honorarios.store', $ajeno), $this->recibo())->assertForbidden();
        $this->assertSame(0, ReciboHonorarios::query()->count());
    }
}
