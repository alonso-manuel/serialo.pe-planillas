<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraVacaciones;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Vacaciones (D.Leg. 713). Casos de fórmula: ⚠ pendientes de validar con el contador. */
class CalculadoraVacacionesTest extends TestCase
{
    private function d(string $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha);
    }

    #[Test]
    public function reparte_los_dias_entre_meses_con_tope_de_30(): void
    {
        // 25/11 al 24/12: 6 días en noviembre y 24 en diciembre.
        $this->assertSame([[2026, 11, 6], [2026, 12, 24]], CalculadoraVacaciones::reparto($this->d('2026-11-25'), 30));
        // Diciembre completo (31 días) cuenta como 30.
        $this->assertSame([[2026, 12, 30]], CalculadoraVacaciones::reparto($this->d('2026-12-01'), 31));
        // Febrero completo (28 días) también es un mes comercial de 30.
        $this->assertSame([[2027, 2, 30]], CalculadoraVacaciones::reparto($this->d('2027-02-01'), 28));
        $this->assertSame('2026-12-24', CalculadoraVacaciones::fin($this->d('2026-11-25'), 30)->format('Y-m-d'));
    }

    #[Test]
    public function genera_un_periodo_por_cada_anio_cumplido(): void
    {
        $p = (new CalculadoraVacaciones)->periodos($this->d('2024-03-15'), $this->d('2026-11-20'), fn () => 30);

        $this->assertCount(3, $p);
        $this->assertSame('2024-03-15', $p[0]->desde->format('Y-m-d'));
        $this->assertSame('2025-03-14', $p[0]->hasta->format('Y-m-d'));
        $this->assertTrue($p[0]->cumplido);
        $this->assertSame(30, $p[0]->derecho);
        $this->assertSame('2026-03-14', $p[0]->vence->format('Y-m-d'));
        $this->assertTrue($p[0]->vencido);            // no gozó y pasó el año siguiente
        $this->assertFalse($p[1]->vencido);           // vence en marzo 2027
        $this->assertFalse($p[2]->cumplido);          // año en curso: sin derecho todavía
        $this->assertSame(0, $p[2]->derecho);
    }

    #[Test]
    public function los_goces_se_imputan_primero_al_periodo_mas_antiguo(): void
    {
        $p = (new CalculadoraVacaciones)->periodos($this->d('2024-01-01'), $this->d('2026-06-30'), fn () => 30, [
            ['inicio' => $this->d('2026-02-01'), 'dias' => 20],
            ['inicio' => $this->d('2026-04-01'), 'dias' => 15],
        ]);

        $this->assertSame(30, $p[0]->gozados);   // 20 + 10
        $this->assertSame(0, $p[0]->saldo);
        $this->assertSame(5, $p[1]->gozados);
        $this->assertSame(25, $p[1]->saldo);
    }

    #[Test]
    public function el_exceso_es_adelanto_sobre_el_periodo_en_curso(): void
    {
        $p = (new CalculadoraVacaciones)->periodos($this->d('2025-01-01'), $this->d('2026-03-31'), fn () => 30, [
            ['inicio' => $this->d('2026-02-01'), 'dias' => 35],
        ]);

        $this->assertSame(0, $p[0]->saldo);
        $this->assertSame(5, $p[1]->gozados);
        $this->assertSame(-5, $p[1]->saldo);
    }

    #[Test]
    public function remype_tiene_15_dias_segun_el_parametro(): void
    {
        $p = (new CalculadoraVacaciones)->periodos($this->d('2025-01-01'), $this->d('2026-02-01'), fn () => 15);

        $this->assertSame(15, $p[0]->derecho);
    }

    #[Test]
    public function avisa_cuando_los_dias_no_efectivos_ponen_en_riesgo_el_record(): void
    {
        $p = (new CalculadoraVacaciones)->periodos($this->d('2025-01-01'), $this->d('2026-02-01'), fn () => 30, [], [1 => 45]);

        $this->assertSame(45, $p[0]->diasNoEfectivos);
        $this->assertTrue($p[0]->avisoRecord);
    }

    #[Test]
    public function remuneracion_vacacional(): void
    {
        $this->assertSame('500.00', CalculadoraVacaciones::remuneracion('1500.00', '0', 10));
        $this->assertSame('1530.00', CalculadoraVacaciones::remuneracion('1500.00', '30.00', 30));
    }
}
