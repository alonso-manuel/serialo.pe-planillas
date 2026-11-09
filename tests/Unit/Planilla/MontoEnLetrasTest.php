<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\MontoEnLetras;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MontoEnLetrasTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function casos(): array
    {
        return [
            'cero' => ['0.00', 'CERO CON 00/100 SOLES'],
            'uno' => ['1.00', 'UNO CON 00/100 SOLES'],
            'quince' => ['15.00', 'QUINCE CON 00/100 SOLES'],
            'veintiuno' => ['21.50', 'VEINTIUNO CON 50/100 SOLES'],
            'cuarenta y cinco' => ['45.05', 'CUARENTA Y CINCO CON 05/100 SOLES'],
            'cien' => ['100.00', 'CIEN CON 00/100 SOLES'],
            'ciento uno' => ['101.00', 'CIENTO UNO CON 00/100 SOLES'],
            'quinientos' => ['500.00', 'QUINIENTOS CON 00/100 SOLES'],
            'mil' => ['1000.00', 'MIL CON 00/100 SOLES'],
            'neto de prueba' => ['1205.00', 'MIL DOSCIENTOS CINCO CON 00/100 SOLES'],
            'rmv' => ['1130.00', 'MIL CIENTO TREINTA CON 00/100 SOLES'],
            'veintiún mil' => ['21000.00', 'VEINTIÚN MIL CON 00/100 SOLES'],
            'treinta y un mil' => ['31416.27', 'TREINTA Y UN MIL CUATROCIENTOS DIECISÉIS CON 27/100 SOLES'],
            'cien mil' => ['100000.00', 'CIEN MIL CON 00/100 SOLES'],
            'un millón' => ['1000000.00', 'UN MILLÓN CON 00/100 SOLES'],
            'millones' => ['2500001.99', 'DOS MILLONES QUINIENTOS MIL UNO CON 99/100 SOLES'],
            'redondeo' => ['10.005', 'DIEZ CON 01/100 SOLES'],
        ];
    }

    #[Test]
    #[DataProvider('casos')]
    public function convierte_montos_a_letras(string $monto, string $esperado): void
    {
        $this->assertSame($esperado, MontoEnLetras::soles($monto));
    }

    #[Test]
    public function rechaza_montos_negativos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MontoEnLetras::soles('-1.00');
    }
}
