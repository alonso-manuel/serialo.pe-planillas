<?php

namespace Tests\Unit\Afp;

use App\Domain\Afp\GeneradorAfpnet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Formato exacto del archivo de texto de AFPnet (posiciones de la guía oficial, act. 20.08.26). Datos ficticios. */
class GeneradorAfpnetTest extends TestCase
{
    private function trabajador(array $cambios = []): array
    {
        return [
            'cuspp' => '123456ABCDE7', 'tipo_documento' => '0', 'numero_documento' => '70000001',
            'apellido_paterno' => 'Quispe', 'apellido_materno' => 'Mamani', 'nombres' => 'Rosa Elena',
            'inicia' => false, 'cesa' => false, 'excepcion' => null, 'remuneracion' => '1500.00', 'afp' => 'IN',
            ...$cambios,
        ];
    }

    #[Test]
    public function cada_campo_va_en_su_posicion(): void
    {
        $linea = rtrim((new GeneradorAfpnet)->generar([$this->trabajador()]), "\r\n");

        $this->assertSame(141, strlen($linea));
        $this->assertSame('00001', substr($linea, 0, 5));                 // 1–5 secuencia
        $this->assertSame('123456ABCDE7', substr($linea, 5, 12));         // 6–17 CUSPP
        $this->assertSame('0', substr($linea, 17, 1));                    // 18 tipo doc
        $this->assertSame(str_pad('70000001', 20), substr($linea, 18, 20)); // 19–38 documento
        $this->assertSame(str_pad('QUISPE', 20), substr($linea, 38, 20)); // 39–58 paterno
        $this->assertSame(str_pad('MAMANI', 20), substr($linea, 58, 20)); // 59–78 materno
        $this->assertSame(str_pad('ROSA ELENA', 20), substr($linea, 78, 20)); // 79–98 nombres
        $this->assertSame('SNN', substr($linea, 98, 3));                  // 99–101 RL, inicio, cese
        $this->assertSame(' ', substr($linea, 101, 1));                   // 102 excepción en blanco = aporta
        $this->assertSame('000150000', substr($linea, 102, 9));           // 103–111 remuneración asegurable
        $this->assertSame(str_repeat('000000000', 3), substr($linea, 111, 27)); // 112–138 aportes voluntarios
        $this->assertSame('N', substr($linea, 138, 1));                   // 139 tipo de trabajo
        $this->assertSame('IN', substr($linea, 139, 2));                  // 140–141 AFP
    }

    #[Test]
    public function montos_sin_punto_como_en_el_ejemplo_de_la_guia(): void
    {
        $linea = (new GeneradorAfpnet)->generar([$this->trabajador(['remuneracion' => '600.2'])]);

        $this->assertSame('000060020', substr($linea, 102, 9));
    }

    #[Test]
    public function nombres_en_mayusculas_sin_tildes_con_enie_y_recortados(): void
    {
        $contenido = (new GeneradorAfpnet)->generar([$this->trabajador([
            'apellido_paterno' => 'Núñez', 'apellido_materno' => null, 'nombres' => 'María José del Rosario Ángeles',
        ])]);

        // Windows-1252: la Ñ ocupa un solo byte (0xD1), así las posiciones no se corren.
        $this->assertSame(141 + 2, strlen($contenido));
        $this->assertSame("NU\xD1EZ".str_repeat(' ', 15), substr($contenido, 38, 20));
        $this->assertSame(str_repeat(' ', 20), substr($contenido, 58, 20));
        $this->assertSame('MARIA JOSE DEL ROSAR', substr($contenido, 78, 20));
    }

    #[Test]
    public function inicio_cese_excepcion_y_afp_desconocida(): void
    {
        $linea = (new GeneradorAfpnet)->generar([$this->trabajador([
            'inicia' => true, 'cesa' => true, 'excepcion' => 'U', 'remuneracion' => '0', 'afp' => null, 'cuspp' => null,
        ])]);

        $this->assertSame(str_repeat(' ', 12), substr($linea, 5, 12));
        $this->assertSame('SSSU', substr($linea, 98, 4));
        $this->assertSame('000000000', substr($linea, 102, 9));
        $this->assertSame('  ', substr($linea, 139, 2));
    }

    #[Test]
    public function secuencia_correlativa_y_fin_de_linea_crlf(): void
    {
        $contenido = (new GeneradorAfpnet)->generar([$this->trabajador(), $this->trabajador(['numero_documento' => '70000002'])]);

        $lineas = explode("\r\n", rtrim($contenido, "\r\n"));
        $this->assertCount(2, $lineas);
        $this->assertSame('00002', substr($lineas[1], 0, 5));
        $this->assertStringEndsWith("\r\n", $contenido);
    }

    #[Test]
    public function rechaza_montos_que_no_caben(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GeneradorAfpnet)->generar([$this->trabajador(['remuneracion' => '10000000.00'])]);
    }
}
