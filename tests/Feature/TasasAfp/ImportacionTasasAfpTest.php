<?php

namespace Tests\Feature\TasasAfp;

use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\TasaAfp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Importación de tasas AFP desde CSV o XLSX con vista previa (valores ficticios). */
class ImportacionTasasAfpTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = ['anio', 'mes', 'afp', 'aporte', 'prima', 'comision_flujo', 'comision_mixta', 'rma'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        foreach (['HABITAT', 'INTEGRA', 'PRIMA', 'PROFUTURO'] as $codigo) {
            Afp::create(['codigo' => $codigo, 'nombre' => $codigo, 'activo' => true]);
        }
    }

    /** @param list<list<string|int|float>> $filas */
    private function csv(array $filas, string $separador = ','): UploadedFile
    {
        $lineas = array_map(fn (array $f) => implode($separador, $f), [self::ENCABEZADO, ...$filas]);

        return UploadedFile::fake()->createWithContent('tasas.csv', implode("\n", $lineas));
    }

    /** @param list<list<string|int|float>> $filas */
    private function xlsx(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'tasas').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues(self::ENCABEZADO));
        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues($fila));
        }
        $writer->close();

        return new UploadedFile($ruta, 'tasas.xlsx', null, null, true);
    }

    #[Test]
    public function plantilla_descargable_en_excel(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('tasas-afp.plantilla'));

        $respuesta->assertOk()->assertDownload('plantilla-tasas-afp.xlsx');

        $ruta = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());
        $reader = new Reader;
        $reader->open($ruta);
        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $this->assertSame(self::ENCABEZADO, $fila->toArray());
                break 2;
            }
        }
        $reader->close();
    }

    #[Test]
    public function importa_csv_con_vista_previa_y_confirmacion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tasas-afp.importar'), ['archivo' => $this->csv([
                [2026, 4, 'HABITAT', '10', '1.37', '1.47', '0', '12500.50'],
                [2026, 4, 'integra', '10', '1.37', '1.55', '0', '12500.50'],
            ])])
            ->assertRedirect(route('tasas-afp.index', ['anio' => 2026, 'mes' => 4]));

        $this->assertSame(0, TasaAfp::count(), 'La vista previa todavía no guarda.');

        $this->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 4]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 2)
                ->where('importacion.con_errores', 0)
                ->where('importacion.filas.1.afp', 'INTEGRA')
                ->where('importacion.filas.1.comision_flujo', '1.55')
            );

        $this->post(route('tasas-afp.importar.confirmar'))
            ->assertRedirect(route('tasas-afp.index', ['anio' => 2026, 'mes' => 4]));

        $this->assertSame(2, TasaAfp::count());
        $this->assertSame('0.0147', Afp::where('codigo', 'HABITAT')->sole()->tasaDe(2026, 4)->comision_flujo);

        $this->get(route('tasas-afp.index'))
            ->assertInertia(fn (Assert $page) => $page->where('importacion', null));
    }

    #[Test]
    public function importa_xlsx_y_csv_con_punto_y_coma(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tasas-afp.importar'), ['archivo' => $this->xlsx([
                [2026, 5, 'PRIMA', 10, 1.37, 1.6, 0, 12500.5],
            ])]);
        $this->post(route('tasas-afp.importar.confirmar'));

        $this->post(route('tasas-afp.importar'), ['archivo' => $this->csv([
            [2026, 5, 'PROFUTURO', '10', '1.37', '1.69', '0', '12500.50'],
        ], ';')]);
        $this->post(route('tasas-afp.importar.confirmar'));

        $this->assertSame('0.0160', Afp::where('codigo', 'PRIMA')->sole()->tasaDe(2026, 5)->comision_flujo);
        $this->assertSame('12500.50', Afp::where('codigo', 'PRIMA')->sole()->tasaDe(2026, 5)->remuneracion_maxima_asegurable);
        $this->assertSame('0.0169', Afp::where('codigo', 'PROFUTURO')->sole()->tasaDe(2026, 5)->comision_flujo);
    }

    #[Test]
    public function filas_en_fraccion_se_convierten_con_aviso(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tasas-afp.importar'), ['archivo' => $this->xlsx([
                [2026, 6, 'HABITAT', 0.1, 0.0137, 0.0147, 0, 12500.5],
            ])]);

        $this->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 6]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 1)
                ->where('importacion.filas.0.prima_seguro', '1.37')
                ->where('importacion.filas.0.avisos.0', 'Las tasas venían como fracción (0.10); se interpretaron como porcentaje (10 %).')
            );

        $this->post(route('tasas-afp.importar.confirmar'));
        $this->assertSame('0.1000', TasaAfp::sole()->aporte_obligatorio);
    }

    #[Test]
    public function errores_por_fila_y_solo_se_guardan_las_validas(): void
    {
        Periodo::factory()->for(Empresa::factory())->cerrado()->create(['anio' => 2026, 'mes' => 1]);

        $this->actingAs($this->admin)
            ->post(route('tasas-afp.importar'), ['archivo' => $this->csv([
                [2026, 7, 'HABITAT', '10', '1.37', '1.47', '0', '12500.50'],  // válida
                [2026, 7, 'MODERNA', '10', '1.37', '1.47', '0', '12500.50'],  // AFP desconocida
                [2026, 7, 'INTEGRA', '10', '9', '1.55', '0', '12500.50'],     // prima fuera de rango
                [2026, 13, 'PRIMA', '10', '1.37', '1.6', '0', '12500.50'],    // mes inválido
                [2026, 1, 'PRIMA', '10', '1.37', '1.6', '0', '12500.50'],     // mes cerrado
                [2026, 7, 'HABITAT', '10', '1.37', '1.50', '0', '12500.50'],  // repetida
                [2026, 7, 'PROFUTURO', '10', '1.37', 'abc', '0', ''],         // no numérica, sin RMA
            ])]);

        $this->get(route('tasas-afp.index', ['anio' => 2026, 'mes' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 1)
                ->where('importacion.con_errores', 6)
                ->where('importacion.filas.0.errores', [])
                ->where('importacion.filas.1.linea', 3)
                ->where('importacion.filas.1.errores.0', 'AFP «MODERNA» no reconocida (usa HABITAT, INTEGRA, PRIMA o PROFUTURO).')
                ->where('importacion.filas.2.errores.0', 'Prima de seguro fuera de rango (0 % a 5 %).')
                ->where('importacion.filas.3.errores.0', 'Año o mes no válido.')
                ->where('importacion.filas.4.errores.0', 'Hay periodos cerrados de 01/2026: sus tasas ya no se pueden modificar.')
                ->where('importacion.filas.5.errores.0', 'Fila repetida: HABITAT 07/2026 ya aparece en la línea 2.')
                ->has('importacion.filas.6.errores', 2)
            );

        $this->post(route('tasas-afp.importar.confirmar'));

        $this->assertSame(1, TasaAfp::count());
    }

    #[Test]
    public function archivo_invalido_o_sin_encabezado(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('tasas-afp.importar'), ['archivo' => UploadedFile::fake()->create('tasas.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('archivo');

        $this->post(route('tasas-afp.importar'), ['archivo' => UploadedFile::fake()->createWithContent('tasas.csv', "a,b,c\n1,2,3")])
            ->assertSessionHasErrors(['archivo' => 'El archivo no tiene las columnas de la plantilla: anio, mes, afp, aporte, prima, comision_flujo, comision_mixta, rma.']);
    }

    #[Test]
    public function confirmar_o_cancelar_sin_vista_previa(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('tasas-afp.importar'), ['archivo' => $this->csv([
            [2026, 8, 'HABITAT', '10', '1.37', '1.47', '0', '12500.50'],
        ])]);
        $this->delete(route('tasas-afp.importar.cancelar'))->assertRedirect();
        $this->post(route('tasas-afp.importar.confirmar'))->assertRedirect();

        $this->assertSame(0, TasaAfp::count());
    }

    #[Test]
    public function asistente_no_importa(): void
    {
        $this->actingAs(User::factory()->asistente()->create());

        $this->post(route('tasas-afp.importar'), ['archivo' => $this->csv([
            [2026, 8, 'HABITAT', '10', '1.37', '1.47', '0', '12500.50'],
        ])])->assertForbidden();
        $this->post(route('tasas-afp.importar.confirmar'))->assertForbidden();
    }
}
