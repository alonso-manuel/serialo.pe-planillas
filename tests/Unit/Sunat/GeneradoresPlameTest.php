<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\Plame\GeneradorJor;
use App\Domain\Sunat\Plame\GeneradorRem;
use App\Domain\Sunat\Plame\GeneradorSnl;
use App\Domain\Sunat\Plame\GeneradorToc;
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

    #[Test]
    public function archivo_snl_una_linea_por_tipo_de_suspension_con_dias(): void
    {
        $contenido = (new GeneradorSnl)->generar([
            ['tipo_documento' => '01', 'numero_documento' => '12345678', 'suspensiones' => ['07' => 2, '21' => 5]],
            ['tipo_documento' => '04', 'numero_documento' => '000123456', 'suspensiones' => ['07' => 0, '22' => 30]],
            ['tipo_documento' => '01', 'numero_documento' => '87654321', 'suspensiones' => ['07' => 0]],
        ]);

        $this->assertSame("01|12345678|07|2|\r\n01|12345678|21|5|\r\n04|000123456|22|30|\r\n", $contenido);
    }

    #[Test]
    public function archivo_snl_vacio_si_nadie_tiene_suspensiones(): void
    {
        $this->assertSame('', (new GeneradorSnl)->generar([['tipo_documento' => '01', 'numero_documento' => '12345678', 'suspensiones' => []]]));
    }

    #[Test]
    public function archivo_toc_con_valores_por_defecto(): void
    {
        $contenido = (new GeneradorToc)->generar([
            ['tipo_documento' => '01', 'numero_documento' => '12345678'],
            ['tipo_documento' => '07', 'numero_documento' => 'AB1234567', 'mas_vida' => true, 'domiciliado' => false],
        ]);

        $this->assertSame("01|12345678|0|0||1|\r\n07|AB1234567|0|1||2|\r\n", $contenido);
    }
}
