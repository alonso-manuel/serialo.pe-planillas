<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraQuinta;
use App\Domain\Planilla\EntradaQuinta;
use App\Domain\Planilla\ParametrosQuinta;
use App\Domain\Planilla\ResultadoQuinta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Retención de renta de quinta categoría (art. 40 del Reglamento de la LIR).
 * UIT S/ 5,350 · deducción 7 UIT · tramos 8/14/17/20/30 % hasta 5/20/35/45 UIT.
 * Casos calculados a mano: ⚠ pendientes de validar con el contador. Al validar uno, anotar su origen.
 */
class CalculadoraQuintaTest extends TestCase
{
    private function parametros(): ParametrosQuinta
    {
        return new ParametrosQuinta('5350.00', '7', [['5', '0.08'], ['20', '0.14'], ['35', '0.17'], ['45', '0.20'], [null, '0.30']]);
    }

    /** @param  array<int, string>  $retenciones */
    private function calcular(int $mes, string $ordinaria, string $percibido, array $retenciones, string $gratisPorPercibir, string $extra = '0', string $otrosRenta = '0', string $otrosRetencion = '0', bool $cese = false, ?string $proyectar = null): ResultadoQuinta
    {
        return (new CalculadoraQuinta)->calcular(new EntradaQuinta(
            mes: $mes,
            remuneracionOrdinaria: $proyectar ?? $ordinaria,
            ordinariaDelMes: $ordinaria,
            extraordinariaDelMes: $extra,
            gratificacionesPorPercibir: $gratisPorPercibir,
            rentaMesesAnteriores: $percibido,
            retencionesPorMes: $retenciones,
            rentaOtrosEmpleadores: $otrosRenta,
            retencionOtrosEmpleadores: $otrosRetencion,
            cese: $cese,
        ), $this->parametros());
    }

    /**
     * Sueldo fijo de S/ 5,000 todo el año, régimen general con EsSalud (gratificación 5,000 + 9 % = 5,450).
     * Renta anual 70,900 − 37,450 = 33,450 → 26,750 × 8 % + 6,700 × 14 % = 3,078 → 256.50 por mes.
     *
     * @return array<int, ResultadoQuinta>
     */
    private function anioCompleto(?int $mesBono = null, string $bono = '0'): array
    {
        $resultados = [];
        $retenciones = [];
        $percibido = '0';

        for ($mes = 1; $mes <= 12; $mes++) {
            $ordinaria = in_array($mes, [7, 12], true) ? '10450.00' : '5000.00'; // julio y diciembre: sueldo + gratificación
            $porPercibir = match (true) {
                $mes < 7 => '10900.00',
                $mes < 12 => '5450.00',
                default => '0',
            };
            $extra = $mes === $mesBono ? $bono : '0';

            $r = $this->calcular($mes, $ordinaria, $percibido, $retenciones, $porPercibir, $extra, proyectar: '5000.00');
            $resultados[$mes] = $r;
            $retenciones[$mes] = $r->retencion;
            $percibido = bcadd($percibido, bcadd($ordinaria, $extra, 2), 2);
        }

        return $resultados;
    }

    #[Test]
    public function sueldo_bajo_no_retiene(): void
    {
        // 1,500 × 12 + 2 × 1,635 = 21,270 < 37,450
        $r = $this->calcular(1, '1500.00', '0', [], '3270.00');

        $this->assertSame('21270.00', $r->rentaProyectada);
        $this->assertSame('0.00', $r->rentaNeta);
        $this->assertSame('0.00', $r->impuestoAnual);
        $this->assertSame('0.00', $r->retencion);
    }

    #[Test]
    public function sueldo_fijo_retiene_lo_mismo_cada_mes_y_el_anio_suma_el_impuesto(): void
    {
        $anio = $this->anioCompleto();

        $this->assertSame('70900.00', $anio[1]->rentaProyectada);
        $this->assertSame('33450.00', $anio[1]->rentaNeta);
        $this->assertSame('3078.00', $anio[1]->impuestoAnual);

        foreach ($anio as $mes => $r) {
            $this->assertSame('256.50', $r->retencion, "Mes {$mes}");
            $this->assertSame('70900.00', $r->rentaProyectada, "Mes {$mes}");
        }
        $this->assertSame('3078.00', $this->suma($anio));
        $this->assertSame(12, $anio[1]->divisor);
        $this->assertSame(9, $anio[4]->divisor);
        $this->assertSame(8, $anio[6]->divisor);
        $this->assertSame(5, $anio[8]->divisor);
        $this->assertSame(4, $anio[10]->divisor);
        $this->assertSame(1, $anio[12]->divisor);
    }

    #[Test]
    public function un_bono_retiene_completo_en_su_mes_la_diferencia_de_impuesto(): void
    {
        $anio = $this->anioCompleto(mesBono: 6, bono: '2000.00');

        // Sin el bono 3,078; con él 35,450 de renta neta → 2,140 + 8,700 × 14 % = 3,358. Diferencia 280.
        $this->assertSame('256.50', $anio[6]->retencionOrdinaria);
        $this->assertSame('280.00', $anio[6]->retencionExtraordinaria);
        $this->assertSame('536.50', $anio[6]->retencion);
        $this->assertSame('3358.00', $this->suma($anio), 'En diciembre el año cierra con el impuesto exacto');
    }

    #[Test]
    public function ingreso_en_mayo_con_certificado_del_empleador_anterior(): void
    {
        // 16,000 de otro empleador + 4,000 de mayo + 7 × 4,000 + gratificaciones por percibir (julio 2/6 y diciembre)
        $r = $this->calcular(5, '4000.00', '0', [], '5813.33', otrosRenta: '16000.00', otrosRetencion: '300.00');

        $this->assertSame('53813.33', $r->rentaProyectada);
        $this->assertSame('16363.33', $r->rentaNeta);
        $this->assertSame('1309.07', $r->impuestoAnual);
        $this->assertSame('126.13', $r->retencion); // (1,309.0664 − 300) ÷ 8
    }

    #[Test]
    public function en_el_mes_del_cese_no_se_proyecta_y_no_se_retiene_de_mas(): void
    {
        $retenciones = array_fill(1, 8, '256.50'); // 2,052 retenidos de enero a agosto
        $r = $this->calcular(9, '5000.00', '45450.00', $retenciones, '5450.00', extra: '2000.00', cese: true);

        // 45,450 + 5,000 + 2,000 = 52,450 − 37,450 = 15,000 × 8 % = 1,200 < 2,052 retenidos
        $this->assertSame('52450.00', $r->rentaProyectada);
        $this->assertSame('1200.00', $r->impuestoAnual);
        $this->assertSame('0.00', $r->retencion);
        $this->assertSame('852.00', $r->saldoAFavor);
        $this->assertSame(1, $r->divisor);
    }

    #[Test]
    public function la_retencion_nunca_es_negativa_y_usa_todos_los_tramos(): void
    {
        // 60,000 mensuales: 60,000 × 12 + 2 × 65,400 = 850,800 − 37,450 = 813,350 de renta neta
        $r = $this->calcular(1, '60000.00', '0', [], '130800.00');

        // 26,750×8 % + 80,250×14 % + 80,250×17 % + 53,500×20 % + 572,600×30 % = 2,140 + 11,235 + 13,642.50 + 10,700 + 171,780
        $this->assertSame('209497.50', $r->impuestoAnual);
        $this->assertSame('17458.13', $r->retencion);
    }

    /** @param  array<int, ResultadoQuinta>  $anio */
    private function suma(array $anio): string
    {
        return array_reduce($anio, fn (string $s, ResultadoQuinta $r) => bcadd($s, $r->retencion, 2), '0.00');
    }
}
