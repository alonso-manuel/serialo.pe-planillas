<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraIndemnizacionDespido;
use App\Domain\Planilla\EntradaIndemnizacion;
use App\Domain\Planilla\ParametrosIndemnizacion;
use App\Domain\Planilla\ResultadoIndemnizacion;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Indemnización por despido arbitrario: art. 38 (indeterminado, código 0501) y art. 76 (plazo fijo, 0503)
 * del D.S. 003-97-TR; REMYPE (20 / 10 remuneraciones diarias por año, topes 120 / 90).
 * Casos calculados a mano: ⚠ pendientes de validar con el contador. Al validar uno, anotar su origen.
 */
class CalculadoraIndemnizacionDespidoTest extends TestCase
{
    private const GENERAL = [45, 360, 45];   // 1.5 remuneraciones (45 días) por año o por mes faltante; tope 12 (360 días)

    private const PEQUENA = [20, 120, null];

    private const MICRO = [10, 90, null];

    /** @param  array{0: int, 1: int, 2: int|null}  $regimen */
    private function calcular(string $ingreso, string $cese, string $remuneracion, array $regimen = self::GENERAL, ?string $finContrato = null): ResultadoIndemnizacion
    {
        return (new CalculadoraIndemnizacionDespido)->calcular(
            new EntradaIndemnizacion(
                new DateTimeImmutable($ingreso),
                new DateTimeImmutable($cese),
                $remuneracion,
                $finContrato === null ? null : new DateTimeImmutable($finContrato),
            ),
            new ParametrosIndemnizacion(diasPorAnio: $regimen[0], topeDias: $regimen[1], diasPorMesPlazoFijo: $regimen[2], mesesPrueba: 3),
        );
    }

    #[Test]
    public function indeterminado_paga_un_sueldo_y_medio_por_anio_con_dozavos_y_treintavos(): void
    {
        // 6 años, 7 meses y 15 días · S/ 100 diarios × 45 × (6 + 7/12 + 15/360) = 4,500 × 6.625
        $r = $this->calcular('2020-03-01', '2026-10-15', '3000.00');

        $this->assertSame(CalculadoraIndemnizacionDespido::DESPIDO, $r->codigo);
        $this->assertSame([6, 7, 15], [$r->anios, $r->meses, $r->dias]);
        $this->assertSame('29812.50', $r->monto);
        $this->assertFalse($r->topeAplicado);
    }

    #[Test]
    public function tiene_tope_de_doce_remuneraciones(): void
    {
        $r = $this->calcular('2010-01-01', '2026-12-31', '3000.00'); // 17 años → 76,500 > 36,000

        $this->assertSame('36000.00', $r->monto);
        $this->assertTrue($r->topeAplicado);
    }

    #[Test]
    public function dentro_del_periodo_de_prueba_no_corresponde(): void
    {
        $r = $this->calcular('2026-08-01', '2026-10-15', '3000.00'); // 2 meses y 15 días

        $this->assertSame('0.00', $r->monto);
        $this->assertNotNull($r->aviso);
    }

    #[Test]
    public function plazo_fijo_paga_un_sueldo_y_medio_por_mes_que_faltaba(): void
    {
        // Faltaban julio a diciembre (6 meses) · 1.5 × 2,000 × 6
        $r = $this->calcular('2026-01-01', '2026-06-30', '2000.00', finContrato: '2026-12-31');
        $this->assertSame(CalculadoraIndemnizacionDespido::PLAZO_FIJO, $r->codigo);
        $this->assertSame([0, 6, 0], [$r->anios, $r->meses, $r->dias]);
        $this->assertSame('18000.00', $r->monto);

        // Faltaban 4 meses y 15 días · 3,000 × 4.5
        $this->assertSame('13500.00', $this->calcular('2026-01-01', '2026-06-30', '2000.00', finContrato: '2026-11-15')->monto);

        // Tope de 12 remuneraciones: faltaban 18 meses → 54,000 > 24,000
        $this->assertSame('24000.00', $this->calcular('2026-01-01', '2026-06-30', '2000.00', finContrato: '2027-12-31')->monto);
    }

    #[Test]
    public function plazo_fijo_ya_vencido_no_paga(): void
    {
        $r = $this->calcular('2026-01-01', '2026-06-30', '2000.00', finContrato: '2026-06-30');

        $this->assertSame('0.00', $r->monto);
        $this->assertNotNull($r->aviso);
    }

    #[Test]
    public function remype_paga_remuneraciones_diarias_por_anio_con_su_tope(): void
    {
        // 7 años · S/ 50 diarios
        $this->assertSame('6000.00', $this->calcular('2020-01-01', '2026-12-31', '1500.00', self::PEQUENA)->monto); // 7,000 → tope 120 días
        $this->assertSame('3500.00', $this->calcular('2020-01-01', '2026-12-31', '1500.00', self::MICRO)->monto);   // 10 × 7 = 70 días
    }
}
