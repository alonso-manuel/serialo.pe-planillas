<?php

namespace App\Domain\Planilla;

use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Vacaciones (D.Leg. 713 y D.S. 012-92-TR). Clase PURA.
 * - Cada año completo de servicios, contado desde el ingreso, genera un periodo con derecho a descanso
 *   (30 días en régimen general, 15 en REMYPE: lo indica quien llama según el parámetro vigente).
 * - El descanso debe gozarse dentro del año siguiente; si no, el periodo queda vencido (indemnización vacacional).
 * - Récord (art. 10): 260 días de labor efectiva (jornada de 6 días) o 210 (5 días). No son efectivos las faltas,
 *   las licencias sin goce ni el subsidio por enfermedad que pase de 60 días (art. 12). Sin conocer la jornada se avisa
 *   cuando los días no efectivos del año superan un margen de 40 días ⚠.
 * - Los goces se imputan a los periodos más antiguos con saldo (primero en vencer, primero en gozarse).
 * - En la planilla, los días se reparten por mes en días calendario con tope de 30 (mes comercial).
 */
final class CalculadoraVacaciones
{
    public const DIAS_MES = 30;

    /** Margen de días no efectivos por año antes de avisar un posible incumplimiento del récord. */
    public const MARGEN_RECORD = 40;

    /**
     * Días de un goce que caen en cada mes: [[año, mes, días], …].
     *
     * @return list<array{int, int, int}>
     */
    public static function reparto(DateTimeImmutable $inicio, int $dias): array
    {
        if ($dias < 1) {
            return [];
        }

        $fin = self::fin($inicio, $dias);
        $reparto = [];
        $cursor = $inicio->modify('first day of this month');

        while ($cursor <= $fin) {
            $primero = $cursor;
            $ultimo = $cursor->modify('last day of this month');
            $desde = max($inicio, $primero);
            $hasta = min($fin, $ultimo);
            $enMes = (int) $desde->diff($hasta)->days + 1;
            $mesCompleto = $desde == $primero && $hasta == $ultimo;

            $reparto[] = [(int) $cursor->format('Y'), (int) $cursor->format('n'), $mesCompleto ? self::DIAS_MES : min($enMes, self::DIAS_MES)];
            $cursor = $cursor->modify('+1 month');
        }

        return $reparto;
    }

    public static function fin(DateTimeImmutable $inicio, int $dias): DateTimeImmutable
    {
        return $inicio->modify('+'.($dias - 1).' days');
    }

    /**
     * Periodos vacacionales desde el ingreso hasta la fecha de corte (incluye el que está en curso).
     *
     * @param  callable(DateTimeImmutable): int  $diasDerecho  días de descanso según el régimen vigente al cumplir el año
     * @param  list<array{inicio: DateTimeImmutable, dias: int}>  $goces
     * @param  array<int, int>  $noEfectivos  número de periodo => días no efectivos registrados en ese año
     * @return list<PeriodoVacacional>
     */
    public function periodos(DateTimeImmutable $ingreso, DateTimeImmutable $corte, callable $diasDerecho, array $goces = [], array $noEfectivos = []): array
    {
        $periodos = [];
        for ($n = 1; ; $n++) {
            $desde = $ingreso->modify('+'.($n - 1).' years');
            if ($desde > $corte) {
                break;
            }
            $hasta = $ingreso->modify("+{$n} years")->modify('-1 day');
            $cumplido = $hasta < $corte;
            $periodos[] = [
                'numero' => $n,
                'desde' => $desde,
                'hasta' => $hasta,
                'cumplido' => $cumplido,
                'derecho' => $cumplido ? $diasDerecho($hasta) : 0,
            ];
        }

        // Goces en orden de fecha, imputados al periodo cumplido más antiguo con saldo.
        $gozados = array_fill(0, count($periodos), 0);
        usort($goces, fn (array $a, array $b) => $a['inicio'] <=> $b['inicio']);
        foreach ($goces as $goce) {
            $pendiente = $goce['dias'];
            foreach ($periodos as $i => $p) {
                $saldo = $p['derecho'] - $gozados[$i];
                if ($pendiente === 0 || ! $p['cumplido'] || $saldo <= 0) {
                    continue;
                }
                $imputar = min($saldo, $pendiente);
                $gozados[$i] += $imputar;
                $pendiente -= $imputar;
            }
            // Lo que exceda el derecho cumplido es adelanto de vacaciones: se imputa al periodo en curso.
            if ($pendiente > 0 && $periodos !== []) {
                $gozados[count($periodos) - 1] += $pendiente;
            }
        }

        return array_map(function (array $p, int $gozado) use ($corte, $noEfectivos) {
            $vence = $p['hasta']->modify('+1 year');
            $saldo = $p['derecho'] - $gozado;
            $noEfec = $noEfectivos[$p['numero']] ?? 0;

            return new PeriodoVacacional(
                numero: $p['numero'],
                desde: $p['desde'],
                hasta: $p['hasta'],
                cumplido: $p['cumplido'],
                derecho: $p['derecho'],
                gozados: $gozado,
                saldo: $saldo,
                vence: $vence,
                vencido: $p['cumplido'] && $saldo > 0 && $corte > $vence,
                diasNoEfectivos: $noEfec,
                avisoRecord: $p['cumplido'] && $noEfec > self::MARGEN_RECORD,
            );
        }, $periodos, $gozados);
    }

    /** Remuneración vacacional (0118) = (básico + promedio de variables) / 30 × días. */
    public static function remuneracion(string $basico, string $promedioVariables, int $dias): string
    {
        return Monto::texto(Monto::redondear(
            Monto::de($basico)->plus(Monto::de($promedioVariables))
                ->multipliedBy($dias)
                ->dividedBy(self::DIAS_MES, 10, RoundingMode::HalfUp)
        ));
    }
}
