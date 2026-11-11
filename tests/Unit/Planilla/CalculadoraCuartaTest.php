<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraCuarta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Retención de cuarta categoría: 8 % sobre recibos mayores a S/ 1,500 sin suspensión. ⚠ validar con el contador. */
class CalculadoraCuartaTest extends TestCase
{
    private function retencion(string $monto, bool $suspendida = false, string $tipo = 'R'): string
    {
        return (new CalculadoraCuarta)->retencion($monto, '0.08', '1500.00', $suspendida, $tipo);
    }

    #[Test]
    public function retiene_ocho_por_ciento_si_supera_el_minimo(): void
    {
        $this->assertSame('160.00', $this->retencion('2000.00'));
        $this->assertSame('120.08', $this->retencion('1501.00'));
        $this->assertSame('1840.00', (new CalculadoraCuarta)->neto('2000.00', '160.00'));
    }

    #[Test]
    public function no_retiene_hasta_el_minimo_ni_con_suspension_ni_en_otros_comprobantes(): void
    {
        $this->assertSame('0.00', $this->retencion('1500.00'));
        $this->assertSame('0.00', $this->retencion('5000.00', suspendida: true));
        $this->assertSame('0.00', $this->retencion('5000.00', tipo: 'N'));
    }
}
