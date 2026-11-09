<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\ProvisionesMensuales;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Provisiones mensuales del costo laboral (estimación). ⚠ Pendiente de validar con el contador. */
class ProvisionesMensualesTest extends TestCase
{
    #[Test]
    public function regimen_general(): void
    {
        $p = ProvisionesMensuales::calcular('1500.00', '0', '1', '0.09', '1', 30);

        $this->assertSame('250.00', $p['gratificacion']);  // 1500 / 6
        $this->assertSame('22.50', $p['bonificacion']);     // 9 %
        $this->assertSame('145.83', $p['cts']);             // (1500 + 250) / 12
        $this->assertSame('125.00', $p['vacaciones']);      // 1500 / 12
        $this->assertSame('543.33', $p['total']);
    }

    #[Test]
    public function con_asignacion_familiar(): void
    {
        $p = ProvisionesMensuales::calcular('1500.00', '113.00', '1', '0.09', '1', 30);

        $this->assertSame('268.83', $p['gratificacion']);   // 1613 / 6
        $this->assertSame('156.82', $p['cts']);             // (1613 + 268.83) / 12
        $this->assertSame('125.00', $p['vacaciones']);      // solo básico
    }

    #[Test]
    public function pequena_empresa_y_microempresa(): void
    {
        $pequena = ProvisionesMensuales::calcular('1500.00', '0', '0.5', '0.09', '0.5', 15);
        $this->assertSame('125.00', $pequena['gratificacion']);
        $this->assertSame('67.71', $pequena['cts']);        // (1500 + 125) × 0.5 / 12
        $this->assertSame('62.50', $pequena['vacaciones']); // 15 días

        $micro = ProvisionesMensuales::calcular('1500.00', '0', '0', '0.09', '0', 15);
        $this->assertSame('0.00', $micro['gratificacion']);
        $this->assertSame('0.00', $micro['cts']);
        $this->assertSame('62.50', $micro['total']);         // solo vacaciones
    }
}
