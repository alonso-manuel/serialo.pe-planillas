<?php

namespace Tests\Unit\Licencia;

use App\Domain\Licencia\ClaveLicencia;
use App\Domain\Licencia\ClaveLicenciaInvalida;
use App\Domain\Licencia\Recurso;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClaveLicenciaTest extends TestCase
{
    private string $privada;

    private string $publica;

    protected function setUp(): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->privada = base64_encode(sodium_crypto_sign_secretkey($par));
        $this->publica = base64_encode(sodium_crypto_sign_publickey($par));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): array
    {
        return [
            'cliente' => 'Estudio Contable Ejemplo S.A.C.',
            'plan' => 'Estudio',
            'max_empresas' => 40,
            'max_usuarios' => 3,
            'max_trabajadores' => null,
            'dominio' => 'planillas.ejemplo.pe',
            'emitida' => '2026-11-09',
            'actualizaciones_hasta' => '2027-11-09',
            ...$cambios,
        ];
    }

    #[Test]
    public function una_clave_firmada_se_lee_con_sus_limites(): void
    {
        $clave = ClaveLicencia::firmar($this->datos(), $this->privada);

        $this->assertStringStartsWith('SERIALO-', $clave);
        $licencia = ClaveLicencia::leer($clave, $this->publica);

        $this->assertSame('Estudio Contable Ejemplo S.A.C.', $licencia->cliente);
        $this->assertSame('Estudio', $licencia->plan);
        $this->assertSame(40, $licencia->limite(Recurso::Empresas));
        $this->assertSame(3, $licencia->limite(Recurso::Usuarios));
        $this->assertNull($licencia->limite(Recurso::Trabajadores)); // ilimitado
        $this->assertSame('2027-11-09', $licencia->actualizacionesHasta);
    }

    #[Test]
    public function tolera_espacios_y_saltos_de_linea_al_pegarla(): void
    {
        $clave = ClaveLicencia::firmar($this->datos(), $this->privada);
        $pegada = "  \n".chunk_split($clave, 60, "\n").'  ';

        $this->assertSame('Estudio', ClaveLicencia::leer($pegada, $this->publica)->plan);
    }

    #[Test]
    public function rechaza_datos_alterados(): void
    {
        $clave = ClaveLicencia::firmar($this->datos(), $this->privada);
        [$datos, $firma] = explode('.', substr($clave, 8));
        $json = (string) base64_decode(strtr($datos, '-_', '+/'));
        $alterado = str_replace('"max_empresas":40', '"max_empresas":400', $json);
        $falsa = 'SERIALO-'.rtrim(strtr(base64_encode($alterado), '+/', '-_'), '=').'.'.$firma;

        $this->expectException(ClaveLicenciaInvalida::class);
        $this->expectExceptionMessage('firma');
        ClaveLicencia::leer($falsa, $this->publica);
    }

    #[Test]
    public function rechaza_una_clave_firmada_por_otro(): void
    {
        $otra = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
        $clave = ClaveLicencia::firmar($this->datos(), $otra);

        $this->expectException(ClaveLicenciaInvalida::class);
        ClaveLicencia::leer($clave, $this->publica);
    }

    #[Test]
    public function rechaza_textos_que_no_son_una_clave(): void
    {
        foreach (['', 'hola', 'SERIALO-', 'SERIALO-abc', 'SERIALO-a.b.c', 'OTRO-abc.def'] as $texto) {
            try {
                ClaveLicencia::leer($texto, $this->publica);
                $this->fail("Aceptó «{$texto}»");
            } catch (ClaveLicenciaInvalida) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function rechaza_datos_firmados_pero_incompletos(): void
    {
        foreach ([['plan' => ''], ['dominio' => ''], ['max_empresas' => 0], ['max_usuarios' => '3'], ['emitida' => '09/11/2026']] as $cambio) {
            $clave = ClaveLicencia::firmar($this->datos($cambio), $this->privada);
            try {
                ClaveLicencia::leer($clave, $this->publica);
                $this->fail('Aceptó '.json_encode($cambio));
            } catch (ClaveLicenciaInvalida) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function el_dominio_acepta_su_variante_www_y_el_puerto(): void
    {
        $licencia = ClaveLicencia::leer(ClaveLicencia::firmar($this->datos(), $this->privada), $this->publica);

        $this->assertTrue($licencia->permiteDominio('planillas.ejemplo.pe'));
        $this->assertTrue($licencia->permiteDominio('PLANILLAS.Ejemplo.pe'));
        $this->assertTrue($licencia->permiteDominio('www.planillas.ejemplo.pe'));
        $this->assertTrue($licencia->permiteDominio('planillas.ejemplo.pe:8443'));
        $this->assertFalse($licencia->permiteDominio('otro.ejemplo.pe'));
        $this->assertFalse($licencia->permiteDominio('planillas.ejemplo.pe.malicioso.com'));
        $this->assertFalse($licencia->permiteDominio('ejemplo.pe'));

        // Emitida para www.dominio: también vale sin www.
        $conWww = ClaveLicencia::leer(ClaveLicencia::firmar($this->datos(['dominio' => 'www.estudio.pe']), $this->privada), $this->publica);
        $this->assertTrue($conWww->permiteDominio('estudio.pe'));
    }

    #[Test]
    public function sabe_si_aun_tiene_actualizaciones(): void
    {
        $licencia = ClaveLicencia::leer(ClaveLicencia::firmar($this->datos(), $this->privada), $this->publica);

        $this->assertTrue($licencia->conActualizaciones(new \DateTimeImmutable('2027-11-09')));
        $this->assertFalse($licencia->conActualizaciones(new \DateTimeImmutable('2027-11-10')));
    }
}
