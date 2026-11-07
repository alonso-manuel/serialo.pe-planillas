<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\Ruc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RUC: 11 dígitos, prefijo de tipo de contribuyente y dígito verificador módulo 11
 * (pesos 5,4,3,2,7,6,5,4,3,2). Números ficticios salvo 20601030013, que es el ejemplo
 * público de la documentación de un proveedor de consulta RUC (persona jurídica).
 */
class RucTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function validos(): array
    {
        return [
            'jurídica ficticia' => ['20000000001'],
            'jurídica ficticia 2' => ['20000000028'],
            'persona natural ficticia' => ['10000000006'],
            'ejemplo público de proveedor' => ['20601030013'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidos(): array
    {
        return [
            'dígito verificador incorrecto' => ['20000000002'],
            'prefijo inexistente' => ['30000000001'],
            'menos de 11 dígitos' => ['2000000001'],
            'más de 11 dígitos' => ['200000000011'],
            'con letras' => ['2000000000A'],
            'con espacios' => [' 20000000001'],
            'vacío' => [''],
        ];
    }

    #[Test]
    #[DataProvider('validos')]
    public function acepta_ruc_valido(string $ruc): void
    {
        $this->assertTrue(Ruc::esValido($ruc));
    }

    #[Test]
    #[DataProvider('invalidos')]
    public function rechaza_ruc_invalido(string $ruc): void
    {
        $this->assertFalse(Ruc::esValido($ruc));
    }

    #[Test]
    public function calcula_digito_verificador_incluido_los_casos_10_y_11(): void
    {
        $this->assertSame(1, Ruc::digitoVerificador('2000000000'));
        $this->assertSame(0, Ruc::digitoVerificador('2000000001')); // 11 - 1 = 10 → 0
        $this->assertSame(6, Ruc::digitoVerificador('1000000000'));
    }

    #[Test]
    public function completa_un_ruc_a_partir_de_sus_primeros_diez_digitos(): void
    {
        $this->assertSame('20000000028', Ruc::completar('2000000002'));
    }

    #[Test]
    public function identifica_persona_juridica(): void
    {
        $this->assertTrue(Ruc::esPersonaJuridica('20000000001'));
        $this->assertFalse(Ruc::esPersonaJuridica('10000000006'));
    }
}
