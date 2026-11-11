<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Retención mensual de renta de quinta categoría (art. 40 del Reglamento de la LIR, D.S. 122-94-EF).
 *
 * 1. Renta anual = percibida en meses anteriores + otros empleadores + lo ordinario del mes
 *    + remuneración ordinaria × meses que faltan + gratificaciones por percibir.
 * 2. Renta neta = renta anual − 7 UIT. Impuesto anual por tramos acumulativos.
 * 3. Retención ordinaria: ene–mar ÷ 12; abr (− retenido ene–mar) ÷ 9; may–jul (− ene–abr) ÷ 8;
 *    ago (− ene–jul) ÷ 5; set–nov (− ene–ago) ÷ 4; dic (− ene–nov) completo.
 * 4. Lo extraordinario del mes retiene completa la diferencia de impuesto que genera.
 * 5. Diciembre y mes del cese (sin proyección) cierran el año: impuesto − todo lo retenido, nunca negativo ⚠.
 *
 * Los cortes, tasas, UIT y deducción son parámetros con vigencia (regla 3).
 */
final class CalculadoraQuinta
{
    private const ESCALA = 10;

    public function calcular(EntradaQuinta $e, ParametrosQuinta $p): ResultadoQuinta
    {
        $uit = Monto::de($p->uit);
        $deduccion = $uit->multipliedBy($p->deduccionUit);
        $final = $e->mes === 12 || $e->cese;

        $base = Monto::de($e->rentaMesesAnteriores)
            ->plus($e->rentaOtrosEmpleadores)
            ->plus($e->ordinariaDelMes);
        if (! $e->cese) {
            $base = $base
                ->plus(Monto::de($e->remuneracionOrdinaria)->multipliedBy(12 - $e->mes))
                ->plus($e->gratificacionesPorPercibir);
        }
        $conExtra = $base->plus($e->extraordinariaDelMes);

        $impuestoSinExtra = $this->impuesto($base->minus($deduccion), $uit, $p->tramos);
        $impuestoConExtra = $this->impuesto($conExtra->minus($deduccion), $uit, $p->tramos);

        [$divisor, $hastaMes] = $final ? [1, $e->mes - 1] : $this->divisor($e->mes);
        $retenidas = $this->retenciones($e, $hastaMes);

        if ($final) {
            $total = $this->noNegativo($impuestoConExtra->minus($retenidas));
            $ordinaria = $this->noNegativo($impuestoSinExtra->minus($retenidas));
            $extraordinaria = $total->minus($ordinaria);
            $saldo = $this->noNegativo($retenidas->minus($impuestoConExtra));
        } else {
            $ordinaria = $this->noNegativo($impuestoSinExtra->minus($retenidas)->dividedBy($divisor, self::ESCALA, RoundingMode::HalfUp));
            $extraordinaria = $this->noNegativo($impuestoConExtra->minus($impuestoSinExtra));
            $total = $ordinaria->plus($extraordinaria);
            $saldo = BigDecimal::zero();
        }

        // Redondeo por partes; el total es la suma de las partes ya redondeadas (regla 1).
        $ordinariaR = Monto::redondear($ordinaria);
        $extraR = $final ? Monto::redondear($total)->minus($ordinariaR) : Monto::redondear($extraordinaria);

        return new ResultadoQuinta(
            rentaProyectada: Monto::texto($conExtra),
            deduccion: Monto::texto($deduccion),
            rentaNeta: Monto::texto($this->noNegativo($conExtra->minus($deduccion))),
            impuestoAnual: Monto::texto($impuestoConExtra),
            retencionesConsideradas: Monto::texto($retenidas),
            divisor: $divisor,
            retencionOrdinaria: (string) $ordinariaR,
            retencionExtraordinaria: (string) $extraR,
            retencion: (string) $ordinariaR->plus($extraR),
            saldoAFavor: Monto::texto($saldo),
        );
    }

    /**
     * Impuesto anual sobre una renta bruta del ejercicio (para el certificado de rentas y retenciones):
     * renta bruta − deducción en UIT, por tramos. Devuelve [renta neta, impuesto] con 2 decimales.
     *
     * @return array{0: string, 1: string}
     */
    public function impuestoDelEjercicio(string $rentaBruta, ParametrosQuinta $p): array
    {
        $uit = Monto::de($p->uit);
        $neta = $this->noNegativo(Monto::de($rentaBruta)->minus($uit->multipliedBy($p->deduccionUit)));

        return [Monto::texto($neta), Monto::texto($this->impuesto($neta, $uit, $p->tramos))];
    }

    /**
     * Impuesto anual sobre la renta neta, por tramos acumulativos.
     *
     * @param  list<array{0: string|null, 1: string}>  $tramos
     */
    private function impuesto(BigDecimal $rentaNeta, BigDecimal $uit, array $tramos): BigDecimal
    {
        $impuesto = BigDecimal::zero();
        $desde = BigDecimal::zero();

        foreach ($tramos as [$hastaUit, $tasa]) {
            if ($rentaNeta->isLessThanOrEqualTo($desde)) {
                break;
            }

            $hasta = $hastaUit === null ? $rentaNeta : BigDecimal::min($rentaNeta, $uit->multipliedBy($hastaUit));
            $impuesto = $impuesto->plus($hasta->minus($desde)->multipliedBy($tasa));
            $desde = $hasta;
        }

        return $impuesto;
    }

    /** @return array{0: int, 1: int} [divisor, hasta qué mes se restan las retenciones (0 = ninguna)] */
    private function divisor(int $mes): array
    {
        return match (true) {
            $mes <= 3 => [12, 0],
            $mes === 4 => [9, 3],
            $mes <= 7 => [8, 4],
            $mes === 8 => [5, 7],
            default => [4, 8],
        };
    }

    private function retenciones(EntradaQuinta $e, int $hastaMes): BigDecimal
    {
        if ($hastaMes === 0) {
            return BigDecimal::zero();
        }

        $total = Monto::de($e->retencionOtrosEmpleadores);
        foreach ($e->retencionesPorMes as $mes => $monto) {
            if ($mes <= $hastaMes) {
                $total = $total->plus($monto);
            }
        }

        return $total;
    }

    private function noNegativo(BigDecimal $valor): BigDecimal
    {
        return $valor->isNegative() ? BigDecimal::zero() : $valor;
    }
}
