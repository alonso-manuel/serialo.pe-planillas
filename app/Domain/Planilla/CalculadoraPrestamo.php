<?php

namespace App\Domain\Planilla;

use Brick\Math\RoundingMode;

/**
 * Cronograma de un préstamo al trabajador en cuotas mensuales iguales (sin intereses): cada cuota es el
 * monto ÷ cuotas redondeado a 2 decimales y la última ajusta la diferencia, de modo que la suma es exacta.
 */
final class CalculadoraPrestamo
{
    /**
     * @return list<array{numero: int, anio: int, mes: int, monto: string}>
     */
    public function cronograma(string $monto, int $cuotas, int $anioInicio, int $mesInicio): array
    {
        if ($cuotas < 1) {
            throw new \InvalidArgumentException('El préstamo necesita al menos una cuota.');
        }

        $total = Monto::de($monto);
        $cuota = $total->dividedBy($cuotas, 2, RoundingMode::HalfUp);
        $acumulado = Monto::cero();
        $lista = [];

        for ($i = 0; $i < $cuotas; $i++) {
            $indice = ($mesInicio - 1) + $i;
            $importe = $i === $cuotas - 1 ? $total->minus($acumulado) : $cuota;
            $acumulado = $acumulado->plus($importe);

            $lista[] = [
                'numero' => $i + 1,
                'anio' => $anioInicio + intdiv($indice, 12),
                'mes' => ($indice % 12) + 1,
                'monto' => Monto::texto($importe),
            ];
        }

        return $lista;
    }
}
