<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Indemnización por despido arbitrario (D.S. 003-97-TR):
 *
 * - Indeterminado (art. 38, código 0501): remuneración diaria × días por año × (años + meses/12 + días/360);
 *   las fracciones de año se pagan por dozavos y treintavos.
 * - Plazo fijo (art. 76, código 0503): remuneración diaria × días por mes × meses que faltaban hasta el
 *   vencimiento (los días, en treintavos). En REMYPE se usa la fórmula por año ⚠.
 * - Tope en días de remuneración (12 sueldos en el régimen general). Nada dentro del periodo de prueba.
 *
 * Es remuneración diaria = mensual ÷ 30. No es remuneración: inafecta a aportes y a renta (LIR art. 18) ⚠.
 */
final class CalculadoraIndemnizacionDespido
{
    public const DESPIDO = '0501';

    public const PLAZO_FIJO = '0503';

    private const ESCALA = 10;

    public function calcular(EntradaIndemnizacion $e, ParametrosIndemnizacion $p): ResultadoIndemnizacion
    {
        $mensual = Monto::de($e->remuneracionMensual);
        $diaria = $mensual->dividedBy(30, self::ESCALA, RoundingMode::HalfUp);
        $plazoFijo = $e->finContrato !== null;
        $codigo = $plazoFijo ? self::PLAZO_FIJO : self::DESPIDO;
        $sinMonto = fn (string $aviso, array $t = [0, 0, 0]) => new ResultadoIndemnizacion($codigo, '0.00', Monto::texto($mensual), $t[0], $t[1], $t[2], false, $aviso);

        $servicio = $this->tiempo($e->fechaIngreso, $e->fechaCese);
        if ($servicio[0] === 0 && $servicio[1] < $p->mesesPrueba) {
            return $sinMonto("No corresponde indemnización: el cese ocurrió dentro del periodo de prueba ({$p->mesesPrueba} meses).", $servicio);
        }

        if ($plazoFijo && $p->diasPorMesPlazoFijo !== null) {
            if ($e->finContrato <= $e->fechaCese) {
                return $sinMonto('No corresponde indemnización: el contrato ya había vencido a la fecha de cese.');
            }

            // Lo que faltaba: del día siguiente al cese hasta el vencimiento, en meses y treintavos.
            $faltante = $this->tiempo($e->fechaCese->modify('+1 day'), $e->finContrato);
            [$anios, $meses, $dias] = $faltante;
            $periodos = BigDecimal::of($anios * 12 + $meses)->plus(BigDecimal::of($dias)->dividedBy(30, self::ESCALA, RoundingMode::HalfUp));
            $bruto = $diaria->multipliedBy($p->diasPorMesPlazoFijo)->multipliedBy($periodos);
        } else {
            [$anios, $meses, $dias] = $servicio;
            $periodos = BigDecimal::of($anios)
                ->plus(BigDecimal::of($meses)->dividedBy(12, self::ESCALA, RoundingMode::HalfUp))
                ->plus(BigDecimal::of($dias)->dividedBy(360, self::ESCALA, RoundingMode::HalfUp));
            $bruto = $diaria->multipliedBy($p->diasPorAnio)->multipliedBy($periodos);
        }

        $tope = $diaria->multipliedBy($p->topeDias);
        $topeAplicado = $bruto->isGreaterThan($tope);

        return new ResultadoIndemnizacion(
            codigo: $codigo,
            monto: Monto::texto($topeAplicado ? $tope : $bruto),
            remuneracionMensual: Monto::texto($mensual),
            anios: $anios,
            meses: $meses,
            dias: $dias,
            topeAplicado: $topeAplicado,
        );
    }

    /**
     * Años, meses y días entre dos fechas, ambas incluidas.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function tiempo(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $d = $desde->diff($hasta->modify('+1 day'));

        return [(int) $d->y, (int) $d->m, (int) $d->d];
    }
}
