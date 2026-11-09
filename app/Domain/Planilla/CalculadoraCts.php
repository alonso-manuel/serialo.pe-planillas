<?php

namespace App\Domain\Planilla;

use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Compensación por tiempo de servicios (D.S. 001-97-TR y reglamento D.S. 004-97-TR). Clase PURA.
 * Reglas (ver docs/reglas-calculo.md, ⚠ a validar con el contador):
 * - Depósito de mayo: periodo noviembre (año anterior)–abril. Depósito de noviembre: mayo–octubre.
 * - Tiempo computable desde el inicio del periodo o el ingreso, en mes comercial de 30 días.
 * - Se descuentan las faltas injustificadas, las licencias sin goce y los días de subsidio por enfermedad
 *   que pasen de 60 en el periodo (el subsidio por maternidad se computa completo).
 * - Remuneración computable = básico + asignación familiar + 1/6 de la gratificación del periodo
 *   + promedio de variables (suma del periodo / 6, solo si hubo variables en 3 meses o más).
 * - Monto = computable / 360 × días computables (= /12 por mes + /360 por día), × factor del régimen.
 */
final class CalculadoraCts
{
    private const DIAS_MES = 30;

    private const DIAS_ANIO = 360;

    private const MESES_PERIODO = 6;

    private const MESES_MINIMOS_VARIABLES = 3;

    /** Días de subsidio por enfermedad computables por periodo. */
    private const TOPE_SUBSIDIO_ENFERMEDAD = 60;

    /** @return list<array{int, int}> año y mes de cada mes del periodo, en orden */
    public static function mesesDelPeriodo(int $anio, int $mesDeposito): array
    {
        $inicio = $mesDeposito === 5
            ? new DateTimeImmutable(sprintf('%04d-11-01', $anio - 1))
            : new DateTimeImmutable(sprintf('%04d-05-01', $anio));

        $meses = [];
        for ($i = 0; $i < self::MESES_PERIODO; $i++) {
            $m = $inicio->modify("+{$i} month");
            $meses[] = [(int) $m->format('Y'), (int) $m->format('n')];
        }

        return $meses;
    }

    public function calcular(EntradaCts $e, ParametrosCts $p): ResultadoCts
    {
        if (! in_array($e->mesDeposito, [5, 11], true)) {
            throw new \InvalidArgumentException('La CTS se deposita en mayo o noviembre.');
        }

        $meses = self::mesesDelPeriodo($e->anio, $e->mesDeposito);
        [$anioIni, $mesIni] = $meses[0];
        [$anioFin, $mesFin] = $meses[self::MESES_PERIODO - 1];
        $inicioPeriodo = new DateTimeImmutable(sprintf('%04d-%02d-01', $anioIni, $mesIni));
        $finPeriodo = (new DateTimeImmutable(sprintf('%04d-%02d-01', $anioFin, $mesFin)))->modify('last day of this month');

        if ($e->fechaIngreso > $finPeriodo) {
            return ResultadoCts::sinDerecho($inicioPeriodo, $finPeriodo);
        }

        // Tiempo bruto en días comerciales desde el inicio del periodo o el ingreso.
        $desde = max($inicioPeriodo, $e->fechaIngreso);
        $diasBrutos = 0;
        $faltas = 0;
        $subsidioEnfermedad = 0;
        $mesesConVariables = 0;
        $sumaVariables = Monto::cero();
        $sinPlanilla = [];

        foreach ($meses as [$anio, $mes]) {
            $inicioMes = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
            $finMes = $inicioMes->modify('last day of this month');
            if ($e->fechaIngreso > $finMes) {
                continue;
            }
            $diasBrutos += $e->fechaIngreso > $inicioMes
                ? self::DIAS_MES - min((int) $e->fechaIngreso->format('j'), self::DIAS_MES) + 1
                : self::DIAS_MES;

            $clave = sprintf('%04d-%02d', $anio, $mes);
            $datos = $e->meses[$clave] ?? null;
            if ($datos === null) {
                $sinPlanilla[] = $clave;

                continue;
            }

            $faltas += $datos['faltas'] + $datos['licencias'];
            $subsidioEnfermedad += $datos['subsidio_enfermedad'];
            $variables = Monto::de($datos['variables']);
            if ($variables->isPositive()) {
                $mesesConVariables++;
                $sumaVariables = $sumaVariables->plus($variables);
            }
        }

        $descontados = $faltas + max(0, $subsidioEnfermedad - self::TOPE_SUBSIDIO_ENFERMEDAD);
        $diasComputables = max(0, $diasBrutos - $descontados);

        $asignacion = $e->asignacionFamiliar
            ? Monto::redondear(Monto::de($p->rmv)->multipliedBy($p->porcentajeAsignacionFamiliar))
            : Monto::cero();
        $sexto = Monto::redondear(Monto::de($e->gratificacion)->dividedBy(self::MESES_PERIODO, 10, RoundingMode::HalfUp));
        $promedio = $mesesConVariables >= self::MESES_MINIMOS_VARIABLES
            ? Monto::redondear($sumaVariables->dividedBy(self::MESES_PERIODO, 10, RoundingMode::HalfUp))
            : Monto::cero();
        $computable = Monto::de($e->remuneracionBasica)->plus($asignacion)->plus($sexto)->plus($promedio);

        $monto = Monto::redondear(
            $computable->multipliedBy($diasComputables)
                ->dividedBy(self::DIAS_ANIO, 10, RoundingMode::HalfUp)
                ->multipliedBy($p->factorRegimen)
        );

        return new ResultadoCts(
            computoDesde: $desde,
            computoHasta: $finPeriodo,
            meses: intdiv($diasComputables, self::DIAS_MES),
            dias: $diasComputables % self::DIAS_MES,
            diasDescontados: min($descontados, $diasBrutos),
            remuneracionBasica: Monto::texto(Monto::de($e->remuneracionBasica)),
            asignacionFamiliar: Monto::texto($asignacion),
            sextoGratificacion: Monto::texto($sexto),
            promedioVariables: Monto::texto($promedio),
            remuneracionComputable: Monto::texto($computable),
            monto: Monto::texto($monto),
            mesesSinPlanilla: $sinPlanilla,
        );
    }
}
