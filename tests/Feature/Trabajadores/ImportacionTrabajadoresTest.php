<?php

namespace Tests\Feature\Trabajadores;

use App\Enums\SistemaPension;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\ParametroSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Carga masiva de trabajadores (solo altas) en la empresa activa. Datos ficticios. */
class ImportacionTrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = [
        'tipo_documento', 'numero_documento', 'apellido_paterno', 'apellido_materno', 'nombres',
        'fecha_nacimiento', 'sexo', 'fecha_ingreso', 'cargo', 'establecimiento', 'tipo_contrato',
        'remuneracion_basica', 'asignacion_familiar', 'sistema_pension', 'afp', 'cuspp', 'comision_afp',
        'seguro_salud', 'cuenta_sueldo', 'cuenta_cts', 'email', 'telefono',
    ];

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15');
        $this->seed(ParametroSeeder::class);
        $this->empresa = Empresa::factory()->create();
        $this->empresa->establecimientos()->create(['codigo' => '0000', 'nombre' => 'Principal']);
        $this->empresa->establecimientos()->create(['codigo' => '0001', 'nombre' => 'Local Norte']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        Afp::create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'activo' => true]);
    }

    /** @return array<string, string|int|float|DateTimeImmutable> */
    private function fila(array $cambios = []): array
    {
        return [
            'tipo_documento' => 'DNI', 'numero_documento' => '70000001', 'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Mamani', 'nombres' => 'Rosa Elena', 'fecha_nacimiento' => '10/03/1995',
            'sexo' => 'F', 'fecha_ingreso' => '02/01/2026', 'cargo' => 'Vendedora', 'establecimiento' => '0001',
            'tipo_contrato' => 'Plazo fijo', 'remuneracion_basica' => '1500.00', 'asignacion_familiar' => 'SÍ',
            'sistema_pension' => 'ONP', 'afp' => '', 'cuspp' => '', 'comision_afp' => '', 'seguro_salud' => '',
            'cuenta_sueldo' => '191-12345678-0-11', 'cuenta_cts' => '', 'email' => '', 'telefono' => '',
            ...$cambios,
        ];
    }

    /** @param list<array<string, mixed>> $filas */
    private function csv(array $filas): UploadedFile
    {
        $lineas = [implode(';', self::ENCABEZADO)];
        foreach ($filas as $f) {
            $lineas[] = implode(';', array_map(fn ($c) => (string) ($f[$c] ?? ''), self::ENCABEZADO));
        }

        return UploadedFile::fake()->createWithContent('trabajadores.csv', implode("\n", $lineas));
    }

    /** @param list<array<string, mixed>> $filas */
    private function xlsx(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'trab').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues(self::ENCABEZADO));
        foreach ($filas as $f) {
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $f[$c] ?? '', self::ENCABEZADO)));
        }
        $writer->close();

        return new UploadedFile($ruta, 'trabajadores.xlsx', null, null, true);
    }

    private function subir(UploadedFile $archivo)
    {
        return $this->actingAs($this->asistente)->post(route('trabajadores.importar.store'), ['archivo' => $archivo]);
    }

    #[Test]
    public function plantilla_con_hoja_de_trabajadores_e_instrucciones(): void
    {
        $respuesta = $this->actingAs($this->asistente)->get(route('trabajadores.importar.plantilla'));
        $respuesta->assertOk()->assertDownload('plantilla-trabajadores.xlsx');

        $ruta = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());
        $reader = new Reader;
        $reader->open($ruta);
        $hojas = [];
        foreach ($reader->getSheetIterator() as $hoja) {
            $filas = iterator_to_array($hoja->getRowIterator(), false);
            $hojas[$hoja->getName()] = $filas;
        }
        $reader->close();

        $this->assertSame(['Trabajadores', 'Instrucciones'], array_keys($hojas));
        $this->assertSame(self::ENCABEZADO, $hojas['Trabajadores'][0]->toArray());
        $this->assertGreaterThan(5, count($hojas['Instrucciones']));
    }

    #[Test]
    public function importa_con_vista_previa_y_confirmacion_en_la_empresa_activa(): void
    {
        $this->subir($this->csv([
            $this->fila(),
            $this->fila(['numero_documento' => '70000002', 'nombres' => 'Luis', 'sistema_pension' => 'AFP',
                'afp' => 'habitat', 'cuspp' => 'abc123def456', 'comision_afp' => 'Mixta', 'establecimiento' => '',
                'tipo_contrato' => 'INDETERMINADO', 'remuneracion_basica' => '1800,50', 'asignacion_familiar' => 'no']),
        ]))->assertRedirect(route('trabajadores.importar'));

        $this->assertSame(0, Trabajador::count(), 'La vista previa no guarda.');

        $this->get(route('trabajadores.importar'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('trabajadores/Importar')
                ->where('importacion.validas', 2)
                ->where('importacion.con_errores', 0)
                ->where('importacion.filas.0.linea', 2)
                ->where('importacion.filas.0.documento', 'DNI 70000001')
                ->where('importacion.filas.0.nombre', 'Quispe Mamani, Rosa Elena')
            );

        $this->post(route('trabajadores.importar.confirmar'))->assertRedirect(route('trabajadores.index'));

        $this->assertSame(2, Trabajador::where('empresa_id', $this->empresa->id)->count());
        $rosa = Trabajador::where('numero_documento', '70000001')->sole();
        $this->assertSame(TipoDocumento::Dni, $rosa->tipo_documento);
        $this->assertSame('1995-03-10', $rosa->fecha_nacimiento->toDateString());
        $this->assertSame('0001', $rosa->establecimiento->codigo);
        $this->assertSame(TipoContrato::PlazoFijo, $rosa->tipo_contrato);
        $this->assertTrue($rosa->asignacion_familiar);

        $luis = Trabajador::where('numero_documento', '70000002')->sole();
        $this->assertSame(SistemaPension::Afp, $luis->sistema_pension);
        $this->assertSame('ABC123DEF456', $luis->cuspp);
        $this->assertSame('1800.50', $luis->remuneracion_basica);
        $this->assertSame('0000', $luis->establecimiento->codigo, 'Sin establecimiento: el principal.');
        $this->assertFalse($luis->asignacion_familiar);
        $this->assertSame('essalud', $luis->seguro_salud->value, 'Sin seguro: el de la empresa.');
    }

    #[Test]
    public function lee_fechas_como_celdas_de_excel(): void
    {
        $this->subir($this->xlsx([
            $this->fila(['fecha_ingreso' => new DateTimeImmutable('2026-02-01'), 'fecha_nacimiento' => '1990-12-31',
                'remuneracion_basica' => 1500.5, 'numero_documento' => 70000003]),
        ]));
        $this->post(route('trabajadores.importar.confirmar'));

        $t = Trabajador::sole();
        $this->assertSame('2026-02-01', $t->fecha_ingreso->toDateString());
        $this->assertSame('1990-12-31', $t->fecha_nacimiento->toDateString());
        $this->assertSame('1500.50', $t->remuneracion_basica);
        $this->assertSame('70000003', $t->numero_documento);
    }

    #[Test]
    public function errores_por_fila_y_solo_se_guardan_las_validas(): void
    {
        Trabajador::factory()->for($this->empresa)->create(['numero_documento' => '70000099']);

        $this->subir($this->csv([
            $this->fila(),                                                               // L2 válida
            $this->fila(['numero_documento' => '70000099']),                             // L3 ya existe
            $this->fila(['nombres' => 'Otra']),                                          // L4 repetido en el archivo
            $this->fila(['numero_documento' => '70000004', 'sistema_pension' => 'AFP', 'afp' => 'HABITAT']), // L5 AFP incompleta
            $this->fila(['numero_documento' => '70000005', 'seguro_salud' => 'SIS']),   // L6 SIS no micro
            $this->fila(['numero_documento' => '70000006', 'fecha_ingreso' => '31/02/2026']), // L7 fecha inválida
            $this->fila(['numero_documento' => '70000007', 'establecimiento' => '0099']), // L8 local inexistente
            $this->fila(['numero_documento' => '70000008', 'afp' => 'MODERNA', 'sistema_pension' => 'AFP',
                'cuspp' => 'ABC123DEF456', 'comision_afp' => 'flujo']),                  // L9 AFP desconocida
        ]));

        $this->get(route('trabajadores.importar'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 1)
                ->where('importacion.con_errores', 7)
                ->where('importacion.filas.1.errores.0', 'Ya existe un trabajador con ese documento en esta empresa.')
                ->where('importacion.filas.2.errores.0', 'Documento repetido: ya aparece en la línea 2.')
                ->where('importacion.filas.3.errores', fn ($e) => collect($e)->contains(fn ($m) => str_contains($m, 'CUSPP')))
                ->where('importacion.filas.4.errores.0', 'El SIS solo aplica a trabajadores de microempresas.')
                ->where('importacion.filas.5.errores.0', 'Fecha de ingreso no válida: usa dd/mm/aaaa.')
                ->where('importacion.filas.6.errores.0', 'El establecimiento «0099» no existe en esta empresa.')
                ->where('importacion.filas.7.errores.0', 'AFP «MODERNA» no reconocida.')
            );

        $this->post(route('trabajadores.importar.confirmar'));

        $this->assertSame(2, Trabajador::count(), 'El existente + la única fila válida.');
    }

    #[Test]
    public function las_filas_de_ejemplo_de_la_plantilla_se_rechazan(): void
    {
        $this->subir($this->csv([$this->fila(['nombres' => 'BORRAR ESTA FILA'])]));

        $this->get(route('trabajadores.importar'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 0)
                ->where('importacion.filas.0.errores.0', 'Es una fila de ejemplo de la plantilla: bórrala antes de subir el archivo.'));
    }

    #[Test]
    public function avisos_que_no_bloquean(): void
    {
        $this->subir($this->csv([
            $this->fila(['remuneracion_basica' => '800', 'fecha_nacimiento' => '01/01/2010']),
        ]));

        $this->get(route('trabajadores.importar'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('importacion.validas', 1)
                ->has('importacion.filas.0.avisos', 2)
            );
    }

    #[Test]
    public function archivo_sin_encabezado_o_demasiado_grande(): void
    {
        $this->subir(UploadedFile::fake()->createWithContent('t.csv', "a;b\n1;2"))
            ->assertSessionHasErrors('archivo');

        $filas = [];
        for ($i = 0; $i < 501; $i++) {
            $filas[] = $this->fila(['numero_documento' => (string) (71000000 + $i)]);
        }
        $this->subir($this->csv($filas))
            ->assertSessionHasErrors(['archivo' => 'El archivo tiene más de 500 filas. Divídelo en varios archivos.']);
    }

    #[Test]
    public function si_cambia_la_empresa_activa_no_se_importa_en_otra(): void
    {
        $otra = Empresa::factory()->create();
        $this->asistente->empresas()->attach($otra);

        $this->subir($this->csv([$this->fila()]));
        $this->put(route('empresa-activa.update'), ['empresa_id' => $otra->id]);

        $this->post(route('trabajadores.importar.confirmar'))->assertRedirect();

        $this->assertSame(0, Trabajador::count());
    }

    #[Test]
    public function cancelar_la_importacion(): void
    {
        $this->subir($this->csv([$this->fila()]));
        $this->delete(route('trabajadores.importar.cancelar'))->assertRedirect();
        $this->post(route('trabajadores.importar.confirmar'));

        $this->assertSame(0, Trabajador::count());
    }

    #[Test]
    public function sin_empresa_activa_vuelve_al_panel(): void
    {
        $this->actingAs(User::factory()->asistente()->create())
            ->get(route('trabajadores.importar'))
            ->assertRedirect(route('dashboard'));
    }
}
