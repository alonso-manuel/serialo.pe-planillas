<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\Plame\GeneradorCuarta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Formato de los archivos .ps4 (estructura 7) y .4ta (estructura 20) del PLAME. Datos ficticios. */
class GeneradorCuartaTest extends TestCase
{
    #[Test]
    public function ps4_tiene_siete_campos(): void
    {
        $this->assertSame(
            "06|10456789012|PEREZ|LOPEZ|JUAN|1|0|\r\n",
            (new GeneradorCuarta)->ps4([['tipo_documento' => '06', 'numero_documento' => '10456789012', 'apellido_paterno' => 'PEREZ', 'apellido_materno' => 'LOPEZ', 'nombres' => 'JUAN', 'domiciliado' => true]]),
        );
    }

    #[Test]
    public function cuarta_tiene_once_campos(): void
    {
        $linea = (new GeneradorCuarta)->cuarta([[
            'tipo_documento' => '06', 'numero_documento' => '10456789012', 'tipo_comprobante' => 'R', 'serie' => 'E001',
            'numero' => '123', 'monto' => '2000.00', 'fecha_emision' => '05/11/2026', 'fecha_pago' => '20/11/2026', 'retiene' => true,
        ]]);

        $this->assertSame("06|10456789012|R|E001|123|2000.00|05/11/2026|20/11/2026|1|3||\r\n", $linea);
        $this->assertSame(11, substr_count(rtrim($linea), '|'));
    }
}
