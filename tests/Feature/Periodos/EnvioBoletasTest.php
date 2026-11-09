<?php

namespace Tests\Feature\Periodos;

use App\Enums\EstadoPeriodo;
use App\Jobs\EnviarBoletaJob;
use App\Mail\BoletaPagoMail;
use App\Models\Empresa;
use App\Models\EnvioBoleta;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\BoletaPago;
use Database\Seeders\ConceptoSeeder;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Envío de boletas por correo en cola, con constancia. Datos ficticios. */
class EnvioBoletasTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $asistente;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-30 09:00');
        $this->seed([ParametroSeeder::class, ConceptoSeeder::class]);
        $this->empresa = Empresa::factory()->create(['razon_social' => 'Demo Envíos S.A.C.']);
        $this->asistente = User::factory()->asistente()->create();
        $this->asistente->empresas()->attach($this->empresa);
        $this->periodo = Periodo::factory()->for($this->empresa)->create(['anio' => 2026, 'mes' => 11]);
    }

    private function enPlanilla(string $paterno, ?string $email): PlanillaLinea
    {
        $t = Trabajador::factory()->for($this->empresa)->create([
            'apellido_paterno' => $paterno, 'apellido_materno' => null, 'nombres' => 'Prueba', 'email' => $email, 'numero_documento' => (string) random_int(70000000, 79999999),
            'fecha_ingreso' => '2025-01-01', 'remuneracion_basica' => '1500.00', 'sistema_pension' => 'onp', 'afp_id' => null,
        ]);

        return $this->periodo->lineas()->create(['trabajador_id' => $t->id, 'dias_laborados' => 30]);
    }

    private function calcularYCerrar(): void
    {
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo));
        $this->periodo->refresh()->update(['estado' => EstadoPeriodo::Cerrado]);
    }

    #[Test]
    public function pone_en_cola_una_boleta_por_trabajador_con_correo_y_avisa_los_que_no_tienen(): void
    {
        Queue::fake();
        $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->enPlanilla('Beta', 'beta@ejemplo.test');
        $this->enPlanilla('Sincorreo', null);
        $this->calcularYCerrar();

        $this->post(route('periodos.boletas.correo', $this->periodo))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash('toast.message', 'Se pusieron en cola 2 boletas para enviar por correo. Sin correo en su ficha: Sincorreo, Prueba.');

        Queue::assertPushed(EnviarBoletaJob::class, 2);
        $this->assertSame(2, EnvioBoleta::query()->where('estado', 'pendiente')->where('user_id', $this->asistente->id)->count());
    }

    #[Test]
    public function el_job_envia_el_correo_con_el_pdf_protegido_y_deja_constancia(): void
    {
        Mail::fake();
        $linea = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->calcularYCerrar();
        $envio = EnvioBoleta::query()->create(['planilla_linea_id' => $linea->id, 'email' => 'alfa@ejemplo.test', 'user_id' => $this->asistente->id]);

        (new EnviarBoletaJob($envio->id))->handle(app(BoletaPago::class));

        Mail::assertSent(BoletaPagoMail::class, function (BoletaPagoMail $mail) {
            $adjuntos = $mail->attachments();

            return count($adjuntos) === 1
                && str_ends_with((string) $adjuntos[0]->as, 'Alfa Prueba.pdf')
                && $mail->hasTo('alfa@ejemplo.test')
                && $mail->periodo === 'Noviembre 2026'
                && $mail->empresa === 'Demo Envíos S.A.C.'
                && $mail->tipoDocumento === 'DNI';
        });

        $envio->refresh();
        $this->assertSame('enviado', $envio->estado);
        $this->assertSame('2026-11-30 09:00', $envio->enviado_en?->format('Y-m-d H:i'));
    }

    #[Test]
    public function el_pdf_del_correo_esta_cifrado(): void
    {
        $linea = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->calcularYCerrar();

        $linea->refresh();
        $pdf = app(BoletaPago::class)->pdfProtegido($this->periodo, $linea);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringNotContainsString('/Encrypt', app(BoletaPago::class)->pdf($this->periodo, $linea));
    }

    #[Test]
    public function si_el_correo_falla_queda_registrado_el_error(): void
    {
        $linea = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->calcularYCerrar();
        $envio = EnvioBoleta::query()->create(['planilla_linea_id' => $linea->id, 'email' => 'alfa@ejemplo.test']);

        $job = new EnviarBoletaJob($envio->id);
        $job->failed(new \RuntimeException('Conexión SMTP rechazada'));

        $envio->refresh();
        $this->assertSame('fallido', $envio->estado);
        $this->assertSame('Conexión SMTP rechazada', $envio->error);
    }

    #[Test]
    public function un_envio_general_no_repite_a_quien_ya_la_recibio_pero_el_reenvio_si(): void
    {
        Queue::fake();
        $linea = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->calcularYCerrar();
        EnvioBoleta::query()->create(['planilla_linea_id' => $linea->id, 'email' => 'alfa@ejemplo.test', 'estado' => 'enviado', 'enviado_en' => now()]);

        $this->post(route('periodos.boletas.correo', $this->periodo))
            ->assertInertiaFlash('toast.message', 'Todas las boletas ya fueron enviadas.');
        Queue::assertNothingPushed();

        $this->post(route('periodos.lineas.boleta.correo', [$this->periodo, $linea]))->assertRedirect();
        Queue::assertPushed(EnviarBoletaJob::class, 1);
    }

    #[Test]
    public function solo_se_envian_boletas_de_periodos_cerrados(): void
    {
        Queue::fake();
        $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->actingAs($this->asistente)->post(route('periodos.calcular', $this->periodo));

        $this->post(route('periodos.boletas.correo', $this->periodo))
            ->assertInertiaFlash('toast.type', 'error');

        Queue::assertNothingPushed();
        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page->where('envios.puede_enviar', false)->where('envios.con_correo', 1));
    }

    #[Test]
    public function el_periodo_muestra_el_estado_de_cada_envio(): void
    {
        $alfa = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->enPlanilla('Beta', null);
        $this->calcularYCerrar();
        EnvioBoleta::query()->create(['planilla_linea_id' => $alfa->id, 'email' => 'alfa@ejemplo.test', 'estado' => 'fallido', 'error' => 'Buzón lleno']);

        $this->get(route('periodos.show', $this->periodo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('envios.puede_enviar', true)
                ->where('envios.fallidos', 1)
                ->where('envios.sin_correo', [$this->periodo->lineas()->with('trabajador')->get()->firstWhere('id', '!=', $alfa->id)->trabajador->nombre_completo])
                ->where('envios.filas.0.estado', 'fallido')
                ->where('envios.filas.0.error', 'Buzón lleno'),
            );
    }

    #[Test]
    public function un_usuario_de_otra_empresa_no_puede_enviar(): void
    {
        Queue::fake();
        $linea = $this->enPlanilla('Alfa', 'alfa@ejemplo.test');
        $this->calcularYCerrar();
        $intruso = User::factory()->asistente()->create();
        $intruso->empresas()->attach(Empresa::factory()->create());

        $this->actingAs($intruso);
        $this->post(route('periodos.boletas.correo', $this->periodo))->assertForbidden();
        $this->post(route('periodos.lineas.boleta.correo', [$this->periodo, $linea]))->assertForbidden();
        Queue::assertNothingPushed();
    }
}
