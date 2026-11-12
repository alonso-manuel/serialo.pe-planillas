<?php

namespace Tests\Feature\Seguridad;

use App\Models\Empresa;
use App\Models\FirmaDigital;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Respaldo\Respaldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Páginas legales públicas y la página «Seguridad y cumplimiento» del administrador. Datos ficticios. */
class SeguridadCumplimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-12 10:00');
    }

    #[Test]
    public function los_terminos_y_la_politica_de_privacidad_son_publicos(): void
    {
        config(['planillas.legal.responsable' => 'Estudio Ficticio S.A.C.', 'planillas.legal.correo' => 'privacidad@estudio-ficticio.test']);

        $this->get(route('legal.terminos'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('legal/Terminos')->where('legal.version', '12/11/2026'));

        $this->get(route('legal.privacidad'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('legal/Privacidad')
                ->where('legal.responsable', 'Estudio Ficticio S.A.C.')
                ->where('legal.correo', 'privacidad@estudio-ficticio.test')
                ->where('legal.saas', false));
    }

    #[Test]
    public function el_administrador_ve_el_estado_de_seguridad_del_sistema(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Ficticio']);
        User::factory()->asistente()->create(['name' => 'Asistente Sin 2FA']);
        User::factory()->asistente()->create(['name' => 'Asistente Con 2FA', 'two_factor_confirmed_at' => now()]);
        User::factory()->asistente()->create(['name' => 'Inactivo', 'activo' => false]);

        $conFirma = Empresa::factory()->create(['razon_social' => 'Alfa S.A.C.']);
        $porVencer = Empresa::factory()->create(['razon_social' => 'Beta S.A.C.']);
        Empresa::factory()->create(['razon_social' => 'Gamma S.A.C.']);
        $this->firma($conFirma, '2027-11-01');
        $this->firma($porVencer, '2026-11-30');

        $cerrado = Periodo::factory()->for($conFirma)->create(['anio' => 2026, 'mes' => 10, 'estado' => 'cerrado']);
        $cerrado->registrarAuditoria('reabierto', ['motivo' => ['', 'Corrección de faltas']]);
        app(Respaldos::class)->crear();

        $this->actingAs($admin)->get(route('seguridad.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('seguridad/Index')
                ->where('cierres.cerrados', 1)
                ->where('cierres.reaperturas', 1)
                ->where('auditoria.ultimos_30', fn ($n) => $n >= 1)
                ->where('firmas', fn ($firmas) => collect($firmas)->pluck('estado', 'empresa')->all() === [
                    'Alfa S.A.C.' => 'vigente', 'Beta S.A.C.' => 'por_vencer', 'Gamma S.A.C.' => 'sin_certificado',
                ])
                ->where('respaldos.cantidad', 1)
                ->where('respaldos.hora', '03:00')
                ->where('accesos.activos', 3)
                ->where('accesos.sin_doble_factor', ['Admin Ficticio', 'Asistente Sin 2FA']));
    }

    #[Test]
    public function un_asistente_no_ve_la_pagina_de_seguridad(): void
    {
        $this->actingAs(User::factory()->asistente()->create())->get(route('seguridad.index'))->assertForbidden();
    }

    private function firma(Empresa $empresa, string $vence): void
    {
        FirmaDigital::query()->forceCreate([
            'empresa_id' => $empresa->id, 'titular' => 'TITULAR FICTICIO', 'documento' => 'DNI 00000000', 'emisor' => 'EMISOR FICTICIO',
            'numero_serie' => '01', 'huella' => str_repeat('A', 64), 'valido_desde' => '2026-01-01', 'valido_hasta' => $vence,
            'certificado' => base64_encode('ficticio'), 'clave' => 'ficticia',
        ]);
    }
}
