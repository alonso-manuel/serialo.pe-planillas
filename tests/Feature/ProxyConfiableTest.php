<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Detrás del proxy del servidor (Docker): HTTPS e IP real solo si el proxy es de confianza (TRUSTED_PROXIES). */
class ProxyConfiableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_prueba-proxy', fn () => ['https' => request()->isSecure(), 'ip' => request()->ip()]);
    }

    /** @return array<string, string> */
    private function cabeceras(): array
    {
        return ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '190.0.0.7'];
    }

    #[Test]
    public function con_un_proxy_de_confianza_lee_https_y_la_ip_real(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12,10.0.0.0/8']);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])->withHeaders($this->cabeceras())
            ->get('/_prueba-proxy')->assertExactJson(['https' => true, 'ip' => '190.0.0.7']);
    }

    #[Test]
    public function sin_configurar_no_confia_en_las_cabeceras(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])->withHeaders($this->cabeceras())
            ->get('/_prueba-proxy')->assertExactJson(['https' => false, 'ip' => '172.18.0.5']);
    }
}
