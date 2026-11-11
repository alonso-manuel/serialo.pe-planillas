<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraPrestamo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Cronograma de préstamos al trabajador: cuotas iguales, la última ajusta los céntimos. */
class CalculadoraPrestamoTest extends TestCase
{
    #[Test]
    public function cuotas_iguales_y_la_ultima_ajusta_la_diferencia(): void
    {
        $c = (new CalculadoraPrestamo)->cronograma('1000.00', 3, 2026, 11);

        $this->assertSame(['333.33', '333.33', '333.34'], array_column($c, 'monto'));
        $this->assertSame([[2026, 11], [2026, 12], [2027, 1]], array_map(fn ($x) => [$x['anio'], $x['mes']], $c));
        $this->assertSame([1, 2, 3], array_column($c, 'numero'));
    }

    #[Test]
    public function una_sola_cuota_es_el_monto_completo(): void
    {
        $this->assertSame([['numero' => 1, 'anio' => 2026, 'mes' => 12, 'monto' => '500.00']], (new CalculadoraPrestamo)->cronograma('500', 1, 2026, 12));
    }
}
