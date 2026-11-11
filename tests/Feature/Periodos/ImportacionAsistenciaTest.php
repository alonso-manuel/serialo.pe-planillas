<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Asistencia del mes desde Excel/CSV: todo o nada, con todos los errores. Datos ficticios. */
class ImportacionAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-25 10:00');
        $this->seed(CatalogosSeeder::class);
        $this->empresa = Empresa::factory()->create();
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $dni): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create(['numero_documento' => $dni, 'fecha_ingreso' => '2025-01-01']);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    private function csv(string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'asis');
        file_put_contents((string) $ruta, "numero_documento,trabajador,faltas,licencia_sin_goce,dias_subsidio,tipo_subsidio,horas_extra_25,horas_extra_35\n".$contenido);

        return new UploadedFile((string) $ruta, 'asistencia.csv', 'text/csv', null, true);
    }

    private function importar(string $contenido)
    {
        return $this->actingAs($this->asistente)->post(route('periodos.asistencia.importar', $this->periodo), ['archivo' => $this->csv($contenido)]);
    }

    #[Test]
    public function actualiza_faltas_subsidios_y_horas_extra_de_cada_trabajador(): void
    {
        $a = $this->enPlanilla('70000001');
        $b = $this->enPlanilla('70000002');

        $this->importar("70000001,Uno,2,0,0,,4.5,1\n70000002,Dos,0,1,5,22,0,0\n")
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Asistencia importada: 2 trabajadores actualizados. Recalcula la planilla.');

        $a->refresh();
        $b->refresh();
        $this->assertSame([2, 28, '4.50', '1.00'], [$a->dias_no_laborados, $a->dias_laborados, (string) $a->horas_extra_25, (string) $a->horas_extra_35]);
        $this->assertSame([1, 5, '22', 24], [$b->dias_licencia, $b->dias_subsidiados, $b->tipo_subsidio, $b->dias_laborados]);
    }

    #[Test]
    public function con_un_error_no_guarda_nada_y_lista_todos_los_errores(): void
    {
        $a = $this->enPlanilla('70000001');
        $this->enPlanilla('70000002');

        $this->importar("70000001,Uno,3,0,0,,0,0\n99999999,X,0,0,0,,0,0\n70000002,Dos,20,15,0,,abc,0\n")
            ->assertSessionHasErrors(['archivo' => "Fila 3: el documento 99999999 no está en la planilla de Noviembre 2026.\nFila 4: faltas, licencias y subsidios suman 35 días y el trabajador solo tiene 30 en el mes."]);

        $this->assertSame(0, $a->refresh()->dias_no_laborados, 'Todo o nada: la fila válida tampoco se guardó');
    }

    #[Test]
    public function la_plantilla_trae_a_los_trabajadores_del_periodo(): void
    {
        $this->enPlanilla('70000001');

        $respuesta = $this->actingAs($this->asistente)->get(route('periodos.asistencia.plantilla', $this->periodo))->assertOk();
        $this->assertStringContainsString('asistencia-noviembre-2026.xlsx', (string) $respuesta->headers->get('Content-Disposition'));
    }

    #[Test]
    public function no_importa_en_un_periodo_cerrado_ni_de_otra_empresa(): void
    {
        $this->enPlanilla('70000001');
        $this->periodo->update(['estado' => EstadoPeriodo::Cerrado]);
        $this->importar("70000001,Uno,1,0,0,,0,0\n")->assertForbidden();

        $ajeno = Periodo::factory()->for(Empresa::factory())->create(['anio' => 2026, 'mes' => 11]);
        $this->post(route('periodos.asistencia.importar', $ajeno), ['archivo' => $this->csv("1,x,0,0,0,,0,0\n")])->assertForbidden();
        $this->get(route('periodos.asistencia.plantilla', $ajeno))->assertForbidden();
    }
}
