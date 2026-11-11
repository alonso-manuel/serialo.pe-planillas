<?php

namespace Tests\Unit\Sunat;

use App\Domain\Sunat\TRegistro\GeneradorTRegistro;
use App\Domain\Sunat\TRegistro\PrestadorTRegistro;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Formato exacto de los archivos de T-Registro (manual del PVS T-Registro). Datos ficticios. */
class GeneradorTRegistroTest extends TestCase
{
    private function prestador(array $datos = []): PrestadorTRegistro
    {
        return new PrestadorTRegistro(...[
            'tipoDocumento' => '01', 'numeroDocumento' => '70000001', 'paisEmisor' => '604',
            'fechaNacimiento' => '15/03/1990', 'apellidoPaterno' => 'PEREZ', 'apellidoMaterno' => 'GOMEZ', 'nombres' => 'ANA LUCIA',
            'sexo' => '2', 'telefono' => '987654321', 'correo' => 'ana.perez@correo-ficticio.pe',
            'regimenLaboral' => '01', 'situacionEducativa' => '13', 'ocupacion' => '251003', 'cuspp' => '',
            'tipoContrato' => '1', 'remuneracionBasica' => '3000.00', 'tipoPago' => '2', 'categoriaOcupacional' => '03',
            'tipoTrabajador' => '21', 'regimenSalud' => '00', 'regimenPensionario' => '02',
            'fechaIngreso' => '01/11/2026', 'ruc' => '20000000001', 'establecimiento' => '0001',
            ...$datos,
        ]);
    }

    #[Test]
    public function ide_tiene_41_campos_y_termina_en_palote(): void
    {
        $linea = rtrim((new GeneradorTRegistro)->ide([$this->prestador()]), "\r\n");

        $this->assertSame('01|70000001|604|15/03/1990|PEREZ|GOMEZ|ANA LUCIA|2|||987654321|ana.perez@correo-ficticio.pe|'.str_repeat('|', 29), $linea);
        $this->assertSame(41, substr_count($linea, '|'));
    }

    #[Test]
    public function tra_tiene_23_campos(): void
    {
        $linea = rtrim((new GeneradorTRegistro)->tra([$this->prestador()]), "\r\n");

        $this->assertSame('01|70000001|604|01|13|251003|0|||1||||0|1|3000.00|1|0|0|2|03|0||', $linea);
        $this->assertSame(23, substr_count($linea, '|'));
    }

    #[Test]
    public function per_da_de_alta_cuatro_registros_y_de_baja_el_periodo_laboral(): void
    {
        $alta = $this->prestador(['cuspp' => '123456ABCDE1', 'regimenPensionario' => '25']);
        $baja = $this->prestador(['numeroDocumento' => '70000002', 'fechaIngreso' => '01/01/2025', 'fechaCese' => '20/11/2026', 'motivoCese' => '01']);

        $this->assertSame(
            "01|70000001|604|1|1|01/11/2026||||\r\n"
            ."01|70000001|604|1|2|01/11/2026||21||\r\n"
            ."01|70000001|604|1|3|01/11/2026||00||\r\n"
            ."01|70000001|604|1|4|01/11/2026||25||\r\n"
            ."01|70000002|604|1|1|01/01/2025|20/11/2026|01||\r\n",
            (new GeneradorTRegistro)->per([$alta], [$baja]),
        );
    }

    #[Test]
    public function est_indica_ruc_y_codigo_de_establecimiento(): void
    {
        $this->assertSame("01|70000001|604|20000000001|0001|\r\n", (new GeneradorTRegistro)->est([$this->prestador()]));
    }

    #[Test]
    public function sin_registros_el_archivo_queda_vacio(): void
    {
        $this->assertSame('', (new GeneradorTRegistro)->per([], []));
    }
}
