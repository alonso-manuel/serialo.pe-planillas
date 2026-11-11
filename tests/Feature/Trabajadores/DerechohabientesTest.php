<?php

namespace Tests\Feature\Trabajadores;

use App\Models\Auditoria;
use App\Models\Derechohabiente;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Derechohabientes del trabajador y su carga masiva en el Registro de Derechohabientes. Datos ficticios. */
class DerechohabientesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00');
        $this->empresa = Empresa::factory()->create(['ruc' => '20000000044']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->trabajador = Trabajador::factory()->for($this->empresa)->create(['tipo_documento' => '01', 'numero_documento' => '70000001', 'asignacion_familiar' => false]);
    }

    /** @return array<string, string> */
    private function hijo(array $datos = []): array
    {
        return [
            'tipo_documento' => '01', 'numero_documento' => '80000001', 'fecha_nacimiento' => '2018-05-10',
            'apellido_paterno' => 'Pérez', 'apellido_materno' => 'Ruiz', 'nombres' => 'Luis', 'sexo' => 'M',
            'vinculo' => '05', 'fecha_alta' => '2026-11-10', ...$datos,
        ];
    }

    private function registrar(array $datos)
    {
        return $this->actingAs($this->asistente)->post(route('trabajadores.derechohabientes.store', $this->trabajador), $datos);
    }

    #[Test]
    public function registra_un_hijo_menor_y_avisa_de_la_asignacion_familiar(): void
    {
        $this->registrar($this->hijo())->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Derechohabiente registrado. Tiene un hijo menor registrado: revisa si le corresponde asignación familiar.');

        $this->assertSame(1, Derechohabiente::query()->count());
        $this->assertSame('creado', Auditoria::query()->where('auditable_type', 'derechohabiente')->value('accion'));
        $this->get(route('trabajadores.edit', $this->trabajador))
            ->assertInertia(fn (Assert $p) => $p->has('derechohabientes', 1)->where('derechohabientes.0.vinculo', 'Hijo(a) menor de edad'));
    }

    #[Test]
    public function exige_el_documento_que_sustenta_cada_vinculo(): void
    {
        // Cónyuge sin documento; con un documento de otro vínculo.
        $this->registrar($this->hijo(['numero_documento' => '40000001', 'fecha_nacimiento' => '1992-01-01', 'vinculo' => '02']))
            ->assertSessionHasErrors(['documento_vinculo' => 'Indica el documento que sustenta el vínculo.']);
        $this->registrar($this->hijo(['numero_documento' => '40000001', 'fecha_nacimiento' => '1992-01-01', 'vinculo' => '02', 'documento_vinculo' => '10']))
            ->assertSessionHasErrors(['documento_vinculo' => 'Ese documento no sustenta el vínculo «Cónyuge» (tabla 27 de SUNAT).']);

        // Concubina: escritura de unión de hecho con su número. Gestante: mes de concepción.
        $this->registrar($this->hijo(['numero_documento' => '40000002', 'fecha_nacimiento' => '1992-01-01', 'vinculo' => '03', 'documento_vinculo' => '08']))
            ->assertSessionHasErrors(['numero_documento_vinculo']);
        $this->registrar($this->hijo(['numero_documento' => '40000003', 'fecha_nacimiento' => '1995-02-01', 'sexo' => 'F', 'vinculo' => '04', 'documento_vinculo' => '01']))
            ->assertSessionHasErrors(['mes_concepcion' => 'Indica el mes de concepción.']);

        // Hijo «menor» con 20 años.
        $this->registrar($this->hijo(['fecha_nacimiento' => '2006-01-01']))->assertSessionHasErrors('fecha_nacimiento');

        $this->assertSame(0, Derechohabiente::query()->count());
    }

    #[Test]
    public function da_de_baja_con_motivo_y_genera_los_archivos_de_alta_y_baja(): void
    {
        $this->registrar($this->hijo())->assertSessionHasNoErrors();
        $this->registrar($this->hijo(['numero_documento' => '40000003', 'fecha_nacimiento' => '1995-02-01', 'apellido_paterno' => 'Núñez', 'nombres' => 'Rosa', 'sexo' => 'F', 'vinculo' => '04', 'documento_vinculo' => '01', 'mes_concepcion' => '2026-09']))->assertSessionHasNoErrors();
        $hijo = Derechohabiente::query()->where('numero_documento', '80000001')->sole();

        $this->patch(route('trabajadores.derechohabientes.baja', [$this->trabajador, $hijo]), ['fecha_baja' => '2026-11-15', 'motivo_baja' => '08'])->assertSessionHasNoErrors();
        $this->assertSame('08', $hijo->refresh()->motivo_baja?->value);

        $this->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->where('derechohabientes', ['altas' => 2, 'bajas' => 1]));

        $respuesta = $this->get(route('tregistro.derechohabientes', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk();
        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $alta = (string) $zip->getFromName('RD_20000000044_20112026_ALTA.TXT');
        $baja = (string) $zip->getFromName('RD_20000000044_20112026_BAJA.TXT');

        $this->assertStringContainsString(mb_convert_encoding('01|70000001|01|40000003|604|01/02/1995|Núñez|Ruiz|Rosa|2|04|01||092026|', 'Windows-1252', 'UTF-8'), $alta);
        $this->assertSame("01|70000001|01|80000001|604|10/05/2018|Pérez|Ruiz|Luis|05|15/11/2026|08|\r\n", mb_convert_encoding($baja, 'UTF-8', 'Windows-1252'));
    }

    #[Test]
    public function no_toca_derechohabientes_de_trabajadores_de_otra_empresa(): void
    {
        $ajeno = Trabajador::factory()->for(Empresa::factory())->create();
        $dh = Derechohabiente::query()->create([
            'trabajador_id' => $ajeno->id, 'tipo_documento' => '01', 'numero_documento' => '80000009', 'fecha_nacimiento' => '2019-01-01',
            'apellido_paterno' => 'Ajeno', 'nombres' => 'Hijo', 'sexo' => 'M', 'vinculo' => '05', 'fecha_alta' => '2026-11-01',
        ]);

        $this->actingAs($this->asistente)->post(route('trabajadores.derechohabientes.store', $ajeno), $this->hijo())->assertForbidden();
        $this->patch(route('trabajadores.derechohabientes.baja', [$ajeno, $dh]), ['fecha_baja' => '2026-11-15', 'motivo_baja' => '08'])->assertForbidden();
        $this->delete(route('trabajadores.derechohabientes.destroy', [$this->trabajador, $dh]))->assertNotFound();

        $this->get(route('tregistro.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertInertia(fn (Assert $p) => $p->where('derechohabientes', ['altas' => 0, 'bajas' => 0]));
        $this->assertNull($dh->refresh()->fecha_baja);
    }
}
