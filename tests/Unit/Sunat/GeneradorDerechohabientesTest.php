<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\TRegistro\DerechohabienteTRegistro;
use App\Domain\Sunat\TRegistro\GeneradorDerechohabientes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Formato de las estructuras 13 (alta) y 24 (baja) de derechohabientes. Datos ficticios. */
class GeneradorDerechohabientesTest extends TestCase
{
    private function hijo(array $datos = []): DerechohabienteTRegistro
    {
        return new DerechohabienteTRegistro(...[
            'tipoDocumentoTitular' => '01', 'numeroDocumentoTitular' => '70000001',
            'tipoDocumento' => '01', 'numeroDocumento' => '80000001', 'paisEmisor' => '604', 'fechaNacimiento' => '10/05/2018',
            'apellidoPaterno' => 'PEREZ', 'apellidoMaterno' => 'RUIZ', 'nombres' => 'LUIS', 'sexo' => '1', 'vinculo' => '05',
            ...$datos,
        ]);
    }

    #[Test]
    public function alta_tiene_46_campos(): void
    {
        $linea = rtrim((new GeneradorDerechohabientes)->alta([$this->hijo()]), "\r\n");

        $this->assertSame('01|70000001|01|80000001|604|10/05/2018|PEREZ|RUIZ|LUIS|1|05||||'.str_repeat('|', 32), $linea);
        $this->assertSame(46, substr_count($linea, '|'));
    }

    #[Test]
    public function alta_de_gestante_y_concubina_lleva_documento_y_mes_de_concepcion(): void
    {
        $gestante = $this->hijo(['numeroDocumento' => '40000001', 'fechaNacimiento' => '01/02/1995', 'sexo' => '2', 'vinculo' => '04', 'documentoVinculo' => '01', 'mesConcepcion' => '092026']);
        $linea = rtrim((new GeneradorDerechohabientes)->alta([$gestante]), "\r\n");

        $this->assertStringStartsWith('01|70000001|01|40000001|604|01/02/1995|PEREZ|RUIZ|LUIS|2|04|01||092026|', $linea);
    }

    #[Test]
    public function baja_tiene_12_campos(): void
    {
        $baja = $this->hijo(['fechaBaja' => '10/05/2036', 'motivoBaja' => '07']);

        $this->assertSame("01|70000001|01|80000001|604|10/05/2018|PEREZ|RUIZ|LUIS|05|10/05/2036|07|\r\n", (new GeneradorDerechohabientes)->baja([$baja]));
    }
}
