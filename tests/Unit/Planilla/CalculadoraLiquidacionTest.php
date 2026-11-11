<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraLiquidacion;
use App\Domain\Planilla\CalculadoraVacaciones;
use App\Domain\Planilla\EntradaLiquidacion;
use App\Domain\Planilla\ParametrosLiquidacion;
use App\Domain\Planilla\ResultadoLiquidacion;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Liquidación de beneficios sociales al cese. Casos de fórmula calculados a mano desde la norma
 * (D.S. 001-97-TR, Ley 27735, D.Leg. 713): ⚠ pendientes de validar con el contador.
 */
class CalculadoraLiquidacionTest extends TestCase
{
    private function liquidar(
        string $ingreso,
        string $cese,
        string $basico,
        bool $asignacion = false,
        ?string $gratiPercibida = null,
        string $regimen = 'general',
        array $meses = [],
        array $goces = [],
    ): ResultadoLiquidacion {
        [$factor, $diasVac] = match ($regimen) {
            'general' => ['1', 30],
            'pequena' => ['0.50', 15],
            'micro' => ['0', 15],
        };
        $ingresoFecha = new DateTimeImmutable($ingreso);
        $ceseFecha = new DateTimeImmutable($cese);

        $periodos = (new CalculadoraVacaciones)->periodos($ingresoFecha, $ceseFecha, fn () => $diasVac, $goces);

        return (new CalculadoraLiquidacion)->calcular(
            new EntradaLiquidacion($ingresoFecha, $ceseFecha, $basico, $asignacion, $meses, $gratiPercibida, $periodos),
            new ParametrosLiquidacion('1130.00', '0.10', $factor, $factor, '0.09', $diasVac),
        );
    }

    #[Test]
    public function regimen_general_con_todos_los_beneficios(): void
    {
        // Ingreso 01/03/2024, cese 20/09/2026, básico 3000 + asignación 113 = 3113. Gratificación de julio cobrada: 3113.
        $r = $this->liquidar('2024-03-01', '2026-09-20', '3000.00', true, '3113.00');

        // Gratificación trunca: julio y agosto → 3113 × 2/6 = 1037.67; bonificación 9 % = 93.39.
        $this->assertSame(2, $r->gratificacionTrunca?->mesesComputables);
        $this->assertNull($r->gratificacionPendiente);
        // CTS trunca: mayo–20/09 = 140 días; (3113 + 518.83) × 140 / 360 = 1412.38.
        $this->assertSame('518.83', $r->cts?->sextoGratificacion);
        $this->assertSame(140, ($r->cts?->meses ?? 0) * 30 + ($r->cts?->dias ?? 0));
        // Vacaciones: 2024-25 (vencido) y 2025-26 sin gozar = 60 días → 6226.00; indemnización por el vencido = 3113.00;
        // truncas del año en curso (01/03–20/09/2026: 6 meses 20 días) = 3113 × 200 / 360 = 1729.44.
        $this->assertSame([6, 20], [$r->vacacionesTruncasMeses, $r->vacacionesTruncasDias]);
        $this->assertSame(60, $r->vacacionesNoGozadasDias);
        $this->assertSame(30, $r->indemnizacionVacacionalDias);

        $this->assertSame([
            '0407' => '1037.67',
            '0313' => '93.39',
            '0904' => '1412.38',
            '0118' => '6226.00',
            '0504' => '3113.00',
            '0114' => '1729.44',
        ], $r->conceptos);
        $this->assertSame('13611.88', $r->total);
    }

    #[Test]
    public function pequena_empresa_mitad_de_gratificacion_y_cts_y_15_dias_de_vacaciones(): void
    {
        $r = $this->liquidar('2024-03-01', '2026-09-20', '2000.00', false, '1000.00', 'pequena');

        $this->assertSame([
            '0407' => '333.33',   // 2000 × 2/6 × 50 %
            '0313' => '30.00',
            '0904' => '421.30',   // (2000 + 166.67) × 140 / 360 × 50 %
            '0118' => '2000.00',  // 15 + 15 días sin gozar
            '0504' => '1000.00',  // 15 días del periodo vencido
            '0114' => '555.56',   // 2000 × 15/30 × 200 / 360
        ], $r->conceptos);
        $this->assertSame('4340.19', $r->total);
    }

    #[Test]
    public function microempresa_solo_vacaciones(): void
    {
        $r = $this->liquidar('2024-03-01', '2026-09-20', '1500.00', false, null, 'micro');

        $this->assertArrayNotHasKey('0407', $r->conceptos);
        $this->assertArrayNotHasKey('0904', $r->conceptos);
        $this->assertSame(['0118', '0504', '0114'], array_keys($r->conceptos));
    }

    #[Test]
    public function cese_en_julio_antes_del_pago_incluye_la_gratificacion_del_semestre(): void
    {
        // Sin planillas en el sistema: los meses se asumen completos y se avisa.
        $r = $this->liquidar('2024-03-01', '2026-07-10', '3000.00');

        $this->assertSame(6, $r->gratificacionPendiente?->mesesComputables);
        $this->assertNull($r->gratificacionTrunca); // julio aún no es un mes completo
        $this->assertSame('3000.00', $r->conceptos['0406']);
        $this->assertSame('270.00', $r->conceptos['0312']);
        // La CTS trunca toma 1/6 de esa gratificación: (3000 + 500) × 70 / 360 = 680.56.
        $this->assertSame('680.56', $r->conceptos['0904']);
        $this->assertSame('1083.33', $r->conceptos['0114']); // 01/03–10/07: 4 meses 10 días
        $this->assertArrayNotHasKey('0407', $r->conceptos);
        $this->assertNotEmpty($r->avisos);
    }

    #[Test]
    public function cese_en_diciembre_antes_del_pago_la_trunca_entra_al_sexto_de_la_cts(): void
    {
        $r = $this->liquidar('2024-03-01', '2026-12-10', '3000.00');

        $this->assertSame('2500.00', $r->conceptos['0407']); // julio–noviembre
        // CTS noviembre–10/12 = 40 días; (3000 + 2500/6) × 40 / 360 = 379.63.
        $this->assertSame('416.67', $r->cts?->sextoGratificacion);
        $this->assertSame('379.63', $r->conceptos['0904']);
    }

    #[Test]
    public function menos_de_un_mes_no_genera_vacaciones_truncas_ni_gratificacion(): void
    {
        $r = $this->liquidar('2026-03-01', '2026-03-20', '3000.00');

        $this->assertSame(['0904' => '166.67'], $r->conceptos); // 20 días de CTS (periodo noviembre–abril)
        $this->assertSame('166.67', $r->total);
    }

    #[Test]
    public function lo_gozado_reduce_las_vacaciones_pendientes(): void
    {
        // Gozó 30 días en 2025 (se imputan al periodo 2024-25): ya no hay vencido ni indemnización.
        $r = $this->liquidar('2024-03-01', '2026-09-20', '3000.00', goces: [['inicio' => new DateTimeImmutable('2025-08-01'), 'dias' => 30]]);

        $this->assertSame(30, $r->vacacionesNoGozadasDias);
        $this->assertSame('3000.00', $r->conceptos['0118']);
        $this->assertArrayNotHasKey('0504', $r->conceptos);
    }

    #[Test]
    public function horas_extra_en_tres_meses_entran_al_computable_de_vacaciones(): void
    {
        $meses = [];
        foreach (['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08'] as $i => $mes) {
            $meses[$mes] = ['faltas' => 0, 'licencias' => 0, 'subsidio_enfermedad' => 0, 'variables' => $i < 3 ? '120.00' : '0'];
        }
        // Promedio de los 6 meses anteriores al cese: 360 / 6 = 60.00 → computable 3060.
        $r = $this->liquidar('2024-03-01', '2026-09-20', '3000.00', meses: $meses);

        $this->assertSame('60.00', $r->promedioVariables);
        $this->assertSame('3060.00', $r->remuneracionComputableVacaciones);
    }
}
