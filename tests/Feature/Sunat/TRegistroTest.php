<?php

namespace Tests\Feature\Sunat;

use App\Enums\CategoriaOcupacional;
use App\Enums\MotivoCese;
use App\Enums\SituacionEducativa;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** T-Registro: revisión de datos y zip con RP_<RUC>.ide/.tra/.per/.est para el PVS. Datos ficticios. */
class TRegistroTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044', 'regimen_laboral' => 'general']);
        Establecimiento::query()->create(['empresa_id' => $this->empresa->id, 'codigo' => '0000', 'nombre' => 'Principal']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
    }

    /** Alta de noviembre con todos los datos que pide SUNAT. */
    private function completo(array $datos = []): Trabajador
    {
        return Trabajador::factory()->for($this->empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => '70000001', 'apellido_paterno' => 'Núñez', 'apellido_materno' => 'Prueba',
            'nombres' => 'Ana', 'fecha_nacimiento' => '1990-03-15', 'sexo' => 'F', 'telefono' => '987 654 321',
            'email' => 'ana@correo-ficticio.pe', 'fecha_ingreso' => '2026-11-03', 'remuneracion_basica' => '3000.00',
            'tipo_contrato' => 'indeterminado', 'sistema_pension' => 'onp', 'afp_id' => null, 'seguro_salud' => 'essalud',
            'categoria_ocupacional' => CategoriaOcupacional::Empleado, 'ocupacion' => '251003',
            'situacion_educativa' => SituacionEducativa::UniversitariaCompleta, ...$datos,
        ]);
    }

    /** @return array<string, string> nombre => contenido */
    private function zip(): array
    {
        $respuesta = $this->actingAs($this->asistente)->get(route('tregistro.zip', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk();
        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $archivos[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }

        return $archivos;
    }

    #[Test]
    public function genera_los_archivos_de_las_altas_y_bajas_del_rango(): void
    {
        $this->completo();
        Trabajador::factory()->for($this->empresa)->create([
            'tipo_documento' => '01', 'numero_documento' => '70000002', 'fecha_ingreso' => '2025-01-01',
            'fecha_cese' => '2026-11-15', 'motivo_cese' => MotivoCese::Renuncia,
        ]);

        $this->actingAs($this->asistente)->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->component('tregistro/Index')->has('altas', 1)->has('bajas', 1)->where('listo', true));

        $archivos = $this->zip();
        $this->assertSame(['RP_20000000044.ide', 'RP_20000000044.tra', 'RP_20000000044.per', 'RP_20000000044.est'], array_keys($archivos));

        // Nombres en ANSI (Windows-1252): la «ú» de Núñez es un solo byte.
        $this->assertStringContainsString(mb_convert_encoding('|Núñez|Prueba|Ana|2|||987654321|', 'Windows-1252', 'UTF-8'), $archivos['RP_20000000044.ide']);
        $this->assertStringContainsString('01|70000001|604|01|13|251003|0|||1||||0|1|3000.00|1|0|0|1|03|0||', $archivos['RP_20000000044.tra']);
        $this->assertStringContainsString("01|70000001|604|1|2|03/11/2026||21||\r\n", $archivos['RP_20000000044.per']);
        $this->assertStringContainsString("01|70000001|604|1|4|03/11/2026||02||\r\n", $archivos['RP_20000000044.per']);
        $this->assertStringContainsString("01|70000002|604|1|1|01/01/2025|15/11/2026|01||\r\n", $archivos['RP_20000000044.per']);
        $this->assertSame("01|70000001|604|20000000044|0000|\r\n", $archivos['RP_20000000044.est']);
    }

    #[Test]
    public function avisa_los_datos_que_faltan_y_no_genera_el_zip(): void
    {
        $this->completo(['telefono' => '014445566', 'ocupacion' => null, 'categoria_ocupacional' => null]);

        $this->actingAs($this->asistente)->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->where('listo', false)
                ->where('altas.0.problemas', [
                    'Falta un celular de 9 dígitos (empieza con 9).',
                    'Falta la categoría ocupacional (ejecutivo, empleado u obrero).',
                    'Falta la ocupación (tabla 30 de SUNAT).',
                ]));

        $this->get(route('tregistro.zip', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Completa los datos de los trabajadores marcados antes de generar los archivos.');
    }

    #[Test]
    public function la_afp_va_con_su_codigo_de_la_tabla_11(): void
    {
        $afp = Afp::query()->create(['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'codigo_sunat' => '25', 'activo' => true]);
        $this->completo(['sistema_pension' => 'afp', 'afp_id' => $afp->id, 'cuspp' => '123456ABCDE1', 'tipo_comision_afp' => 'flujo']);

        $per = $this->zip()['RP_20000000044.per'];
        $this->assertStringContainsString("01|70000001|604|1|4|03/11/2026||25||\r\n", $per);
    }

    #[Test]
    public function el_regimen_agrario_usa_sus_codigos_de_las_tablas_8_32_y_33(): void
    {
        $this->empresa->update(['regimen_laboral' => 'agrario']);
        $this->completo(['categoria_ocupacional' => CategoriaOcupacional::Obrero, 'ocupacion' => '961007']);

        $archivos = $this->zip();
        $this->assertStringStartsWith('01|70000001|604|26|', $archivos['RP_20000000044.tra']); // tabla 33: Ley 31110
        $this->assertStringContainsString("01|70000001|604|1|2|03/11/2026||76||\r\n", $archivos['RP_20000000044.per']); // tabla 8
        $this->assertStringContainsString("01|70000001|604|1|3|03/11/2026||04||\r\n", $archivos['RP_20000000044.per']); // tabla 32
    }

    #[Test]
    public function el_rango_incluye_altas_y_bajas_de_sus_dias_extremos(): void
    {
        Trabajador::factory()->for($this->empresa)->create(['fecha_ingreso' => '2026-11-01', 'fecha_cese' => null]);
        Trabajador::factory()->for($this->empresa)->create([
            'fecha_ingreso' => '2025-01-01', 'fecha_cese' => '2026-11-30', 'motivo_cese' => MotivoCese::Renuncia,
        ]);

        $this->actingAs($this->asistente)->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->has('altas', 1)->has('bajas', 1));
    }

    #[Test]
    public function solo_ve_a_los_trabajadores_de_su_empresa(): void
    {
        $otra = Empresa::factory()->create();
        Trabajador::factory()->for($otra)->create(['fecha_ingreso' => '2026-11-05']);

        $this->actingAs($this->asistente)->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->has('altas', 0)->has('bajas', 0)->where('listo', false));
    }

    #[Test]
    public function busca_ocupaciones_de_la_tabla_30_segun_la_categoria(): void
    {
        $this->actingAs($this->asistente);

        $empleado = $this->getJson(route('tregistro.ocupaciones', ['q' => 'contador', 'categoria' => '03']))->assertOk()->json();
        $this->assertContains(['codigo' => '251003', 'nombre' => 'CONTADOR'], $empleado);

        // «Contador» no está habilitado para obreros.
        $obrero = $this->getJson(route('tregistro.ocupaciones', ['q' => '251003', 'categoria' => '02']))->assertOk()->json();
        $this->assertSame([], $obrero);
    }

    #[Test]
    public function la_ficha_guarda_los_datos_de_sunat_y_valida_la_ocupacion_por_categoria(): void
    {
        $t = $this->completo();
        $datos = fn (array $cambios) => [
            'tipo_documento' => '01', 'numero_documento' => '70000001', 'apellido_paterno' => 'Núñez', 'nombres' => 'Ana',
            'fecha_ingreso' => '2026-11-03', 'tipo_contrato' => 'plazo_fijo', 'remuneracion_basica' => '3000.00',
            'sistema_pension' => 'onp', 'seguro_salud' => 'essalud', ...$cambios,
        ];

        $this->actingAs($this->asistente)->put(route('trabajadores.update', $t), $datos(['categoria_ocupacional' => '02', 'ocupacion' => '251003']))
            ->assertSessionHasErrors(['ocupacion' => 'Esa ocupación no aplica a la categoría «Obrero» según la tabla 30 de SUNAT.']);

        $this->put(route('trabajadores.update', $t), $datos([
            'categoria_ocupacional' => '03', 'ocupacion' => '251003', 'situacion_educativa' => '15',
            'modalidad_contrato' => '9', 'situacion_especial' => '2', 'discapacidad' => true, 'sindicalizado' => false,
        ]))->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('9', $t->modalidad_contrato?->value);
        $this->assertSame('2', $t->situacion_especial->value);
        $this->assertTrue($t->discapacidad);
        $this->assertSame(SituacionEducativa::Titulado, $t->situacion_educativa);
    }
}
