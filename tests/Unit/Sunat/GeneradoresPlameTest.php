<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\Plame\GeneradorJor;
use App\Domain\Sunat\Plame\GeneradorRem;
use App\Domain\Sunat\Plame\NombreArchivoPlame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GeneradoresPlameTest extends TestCase
{
    #[Test]
    public function nombre_del_archivo(): void
    {
        $this->assertSame('060120261020000000001.rem', NombreArchivoPlame::para('20000000001', 2026, 10, 'rem'));
        $this->assertSame('060120260320000000001.jor', NombreArchivoPlame::para('20000000001', 2026, 3, '.jor'));
    }

    #[Test]
    public function archivo_rem_una_linea_por_concepto(): void
    {
        $contenido = (new GeneradorRem)->generar([[
            'tipo_documento' => '01',
            'numero_documento' => '12345678',
            'conceptos' => ['0121' => '1230.00', '0608' => '123.00'],
        ]]);

        $this->assertSame("01|12345678|0121|1230.00|1230.00|\r\n01|12345678|0608|123.00|123.00|\r\n", $contenido);
    }

    #[Test]
    public function archivo_jor(): void
    {
        $contenido = (new GeneradorJor)->generar([[
            'tipo_documento' => '01', 'numero_documento' => '12345678',
            'horas_ordinarias' => 192, 'minutos_ordinarios' => 0, 'horas_extra' => 0, 'minutos_extra' => 0,
        ]]);

        $this->assertSame("01|12345678|192|0|0|0|\r\n", $contenido);
    }
}
