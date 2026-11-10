<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraCts;
use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraVacaciones;
use App\Domain\Planilla\EntradaCts;
use App\Domain\Planilla\EntradaGratificacion;
use App\Domain\Planilla\ParametrosCts;
use App\Domain\Planilla\ParametrosGratificacion;
use App\Domain\Planilla\ResultadoCts;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Gratificación, CTS y vacaciones truncas al cese (liquidación de beneficios sociales).
 * Casos de fórmula ⚠ pendientes de validar con el contador.
 */
class BeneficiosTruncosTest extends TestCase
{
    private function grati(int $anio, int $mesPago, string $ingreso, ?string $cese, array $meses = []): string
    {
        return (new CalculadoraGratificacion)->calcular(
            new EntradaGratificacion($anio, $mesPago, new DateTimeImmutable($ingreso), '3000.00', false, $meses,
                fechaCese: $cese === null ? null : new DateTimeImmutable($cese)),
            new ParametrosGratificacion('1130.00', '0.10', '1', '0.09'),
        )->gratificacion;
    }

    #[Test]
    public function gratificacion_trunca_cuenta_solo_meses_completos_hasta_el_cese(): void
    {
        // Semestre julio–diciembre, cese el 20/09: julio y agosto completos → 3000 × 2/6.
        $this->assertSame('1000.00', $this->grati(2026, 12, '2024-03-01', '2026-09-20'));
        // Cese el último día del mes: ese mes cuenta → julio, agosto y setiembre.
        $this->assertSame('1500.00', $this->grati(2026, 12, '2024-03-01', '2026-09-30'));
        // Diciembre no se presume completo si cesa en diciembre antes del fin de mes.
        $this->assertSame('2500.00', $this->grati(2026, 12, '2024-03-01', '2026-12-10'));
        // Menos de un mes completo: no hay gratificación trunca.
        $this->assertSame('0.00', $this->grati(2026, 12, '2024-03-01', '2026-07-25'));
        // Ingreso a mitad del semestre (01/08) y cese el 31/10: agosto, setiembre, octubre.
        $this->assertSame('1500.00', $this->grati(2026, 12, '2026-08-01', '2026-10-31'));
    }

    #[Test]
    public function gratificacion_trunca_descuenta_faltas_hasta_el_cese(): void
    {
        // 2 meses (1000.00) − 3000 / 180 × 3 faltas (50.00). Las faltas de octubre (posterior al cese) no cuentan.
        $meses = [7 => ['faltas' => 3, 'variables' => '0'], 10 => ['faltas' => 9, 'variables' => '0']];
        $this->assertSame('950.00', $this->grati(2026, 12, '2024-03-01', '2026-09-20', $meses));
    }

    private function cts(int $anio, int $mesDeposito, string $ingreso, string $cese, string $grati = '0', array $meses = []): ResultadoCts
    {
        return (new CalculadoraCts)->calcular(
            new EntradaCts($anio, $mesDeposito, new DateTimeImmutable($ingreso), '3000.00', false, $grati, $meses,
                fechaCese: new DateTimeImmutable($cese)),
            new ParametrosCts('1130.00', '0.10', '1'),
        );
    }

    #[Test]
    public function cts_trunca_desde_el_inicio_del_periodo_hasta_el_cese(): void
    {
        // Periodo mayo–octubre, cese 20/09: 4 meses + 20 días = 140 días. 3000 × 140 / 360 = 1166.666…
        $r = $this->cts(2026, 11, '2024-03-01', '2026-09-20');
        $this->assertSame(4, $r->meses);
        $this->assertSame(20, $r->dias);
        $this->assertSame('1166.67', $r->monto);
        $this->assertSame('2026-09-20', $r->computoHasta->format('Y-m-d'));

        // Con 1/6 de la gratificación de julio (3000): (3000 + 500) × 140 / 360 = 1361.11
        $this->assertSame('1361.11', $this->cts(2026, 11, '2024-03-01', '2026-09-20', '3000.00')->monto);
    }

    #[Test]
    public function cts_trunca_cese_fin_de_mes_e_ingreso_dentro_del_periodo(): void
    {
        // Cese el último día de febrero: noviembre–febrero = 4 meses (febrero cuenta 30).
        $r = $this->cts(2027, 5, '2024-03-01', '2027-02-28');
        $this->assertSame(4, $r->meses);
        $this->assertSame(0, $r->dias);
        $this->assertSame('1000.00', $r->monto);

        // Ingreso 10/12/2026 y cese 15/01/2027: 21 días + 15 días = 36 días → 300.00
        $r = $this->cts(2027, 5, '2026-12-10', '2027-01-15');
        $this->assertSame(1, $r->meses);
        $this->assertSame(6, $r->dias);
        $this->assertSame('300.00', $r->monto);
    }

    #[Test]
    public function cts_trunca_no_cuenta_faltas_posteriores_al_cese(): void
    {
        $meses = ['2026-06' => ['faltas' => 2, 'licencias' => 0, 'subsidio_enfermedad' => 0, 'variables' => '0'],
            '2026-10' => ['faltas' => 5, 'licencias' => 0, 'subsidio_enfermedad' => 0, 'variables' => '0']];
        // 140 − 2 = 138 días → 3000 × 138 / 360 = 1150.00
        $this->assertSame('1150.00', $this->cts(2026, 11, '2024-03-01', '2026-09-20', meses: $meses)->monto);
    }

    #[Test]
    public function vacaciones_truncas_por_meses_y_dias_del_anio_en_curso(): void
    {
        // Año de servicios desde 01/03/2026, cese 20/09/2026: 6 meses y 20 días.
        $this->assertSame([6, 20], CalculadoraVacaciones::tiempoTrunco(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-09-20')));
        // Cese el último día del mes: mes completo.
        $this->assertSame([7, 0], CalculadoraVacaciones::tiempoTrunco(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-09-30')));
        // Aniversario a mitad de mes (15/03) y cese 14/04: un mes exacto.
        $this->assertSame([1, 0], CalculadoraVacaciones::tiempoTrunco(new DateTimeImmutable('2026-03-15'), new DateTimeImmutable('2026-04-14')));

        // 3000 × 30/30 × 200/360 = 1666.67 (régimen general); 15 días: la mitad.
        $this->assertSame('1666.67', CalculadoraVacaciones::montoTrunco('3000.00', 30, 6, 20));
        $this->assertSame('833.33', CalculadoraVacaciones::montoTrunco('3000.00', 15, 6, 20));
    }
}
