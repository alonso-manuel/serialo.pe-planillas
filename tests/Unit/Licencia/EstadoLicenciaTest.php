<?php

namespace Tests\Unit\Licencia;

use App\Domain\Licencia\ClaveLicencia;
use App\Domain\Licencia\EstadoLicencia;
use App\Domain\Licencia\Recurso;
use App\Domain\Licencia\TipoEstadoLicencia;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EstadoLicenciaTest extends TestCase
{
    private string $privada;

    private string $publica;

    /** Límites de la prueba: vienen de la configuración, no del código. */
    private const PRUEBA = ['dias' => 15, 'max_empresas' => 1, 'max_usuarios' => 2, 'max_trabajadores' => 50];

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->privada = base64_encode(sodium_crypto_sign_secretkey($par));
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
    }

    private function clave(): string
    {
        return ClaveLicencia::firmar([
            'cliente' => 'Empresa Ejemplo S.A.C.', 'plan' => 'Empresa',
            'max_empresas' => 1, 'max_usuarios' => 2, 'max_trabajadores' => 50,
            'dominio' => 'planillas.ejemplo.pe', 'emitida' => '2026-11-09', 'actualizaciones_hasta' => '2027-11-09',
        ], $this->privada);
    }

    private function evaluar(?string $clave, ?string $host = 'planillas.ejemplo.pe', ?string $instalado = '2026-11-01', string $hoy = '2026-11-09'): EstadoLicencia
    {
        return EstadoLicencia::evaluar(
            clave: $clave,
            clavePublica: $this->publica,
            host: $host,
            instaladoEn: $instalado === null ? null : new DateTimeImmutable($instalado),
            hoy: new DateTimeImmutable($hoy),
            prueba: self::PRUEBA,
        );
    }

    #[Test]
    public function con_licencia_valida_se_trabaja_normal_con_sus_limites(): void
    {
        $estado = $this->evaluar($this->clave());

        $this->assertSame(TipoEstadoLicencia::Valida, $estado->tipo);
        $this->assertFalse($estado->soloLectura());
        $this->assertSame(50, $estado->limite(Recurso::Trabajadores));
    }

    #[Test]
    public function al_vencer_el_plan_anual_sigue_funcionando(): void
    {
        $estado = $this->evaluar($this->clave(), hoy: '2029-01-01');

        $this->assertSame(TipoEstadoLicencia::Valida, $estado->tipo);
        $this->assertFalse($estado->soloLectura());
        $this->assertFalse($estado->licencia?->conActualizaciones(new DateTimeImmutable('2029-01-01')));
    }

    #[Test]
    public function sin_licencia_hay_15_dias_de_prueba_con_limites_de_prueba(): void
    {
        $estado = $this->evaluar(null, instalado: '2026-11-01', hoy: '2026-11-09');

        $this->assertSame(TipoEstadoLicencia::Prueba, $estado->tipo);
        $this->assertFalse($estado->soloLectura());
        $this->assertSame(7, $estado->diasPrueba); // 1 → 9 nov: van 8 días de 15
        $this->assertSame(1, $estado->limite(Recurso::Empresas));
        $this->assertSame(2, $estado->limite(Recurso::Usuarios));
    }

    #[Test]
    public function el_ultimo_dia_de_prueba_aun_se_puede_trabajar(): void
    {
        $this->assertSame(1, $this->evaluar(null, instalado: '2026-11-01', hoy: '2026-11-15')->diasPrueba);
        $vencida = $this->evaluar(null, instalado: '2026-11-01', hoy: '2026-11-16');

        $this->assertSame(TipoEstadoLicencia::SinLicencia, $vencida->tipo);
        $this->assertTrue($vencida->soloLectura());
        $this->assertStringContainsString('prueba', $vencida->mensaje);
    }

    #[Test]
    public function sin_fecha_de_instalacion_no_hay_prueba(): void
    {
        $this->assertTrue($this->evaluar(null, instalado: null)->soloLectura());
    }

    #[Test]
    public function una_clave_invalida_deja_el_sistema_en_solo_lectura(): void
    {
        $estado = $this->evaluar('SERIALO-basura.basura');

        $this->assertSame(TipoEstadoLicencia::Invalida, $estado->tipo);
        $this->assertTrue($estado->soloLectura());
    }

    #[Test]
    public function en_otro_dominio_queda_en_solo_lectura(): void
    {
        $estado = $this->evaluar($this->clave(), host: 'copia.otro-hosting.com');

        $this->assertSame(TipoEstadoLicencia::OtroDominio, $estado->tipo);
        $this->assertTrue($estado->soloLectura());
        $this->assertStringContainsString('planillas.ejemplo.pe', $estado->mensaje);
    }

    #[Test]
    public function sin_host_como_en_la_cola_no_revisa_el_dominio(): void
    {
        $this->assertSame(TipoEstadoLicencia::Valida, $this->evaluar($this->clave(), host: null)->tipo);
    }

    #[Test]
    public function omitida_no_tiene_limites_ni_bloquea(): void
    {
        $estado = EstadoLicencia::omitida();

        $this->assertFalse($estado->soloLectura());
        $this->assertNull($estado->limite(Recurso::Empresas));
    }
}
