<?php

namespace Tests\Feature\Multitenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ConEstudios;
use Tests\TestCase;

/**
 * Caddy (on-demand TLS) pregunta antes de emitir el certificado de un subdominio: solo el panel y los estudios que
 * existen, y solo desde la red interna de Docker. Datos ficticios.
 */
class PermisoCertificadoTest extends TestCase
{
    use ConEstudios;
    use RefreshDatabase;

    private function preguntar(string $dominio, string $ip = '172.18.0.9'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('http://web/caddy/permitido?domain='.$dominio);
    }

    #[Test]
    public function permite_el_panel_y_los_estudios_que_existen(): void
    {
        $this->activarModoSaas();
        config(['planillas.dominio_panel' => 'admin.localhost']);
        $this->crearEstudio('garcia', 'admin@garcia.test');

        $this->preguntar('admin.localhost')->assertOk();
        $this->preguntar('garcia.localhost')->assertOk();
        $this->preguntar('GARCIA.localhost')->assertOk();

        $this->preguntar('inventado.localhost')->assertNotFound();
        $this->preguntar('garcia.otro-dominio.pe')->assertNotFound();
        $this->preguntar('a.garcia.localhost')->assertNotFound();
        $this->preguntar('')->assertNotFound();
    }

    #[Test]
    public function no_responde_fuera_de_la_red_interna(): void
    {
        $this->activarModoSaas();
        config(['planillas.dominio_panel' => 'admin.localhost']);

        $this->preguntar('admin.localhost', '190.0.0.7')->assertForbidden();
    }

    #[Test]
    public function no_existe_en_una_instalacion_unica(): void
    {
        $this->preguntar('cualquiera.localhost')->assertNotFound();
    }
}
