<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Gratificación de julio y diciembre (Ley 27735) y bonificación extraordinaria (Ley 30334).
 *
 * Clase PURA. Reglas (ver docs/reglas-calculo.md, ⚠ a validar con el contador):
 * - Semestre: enero–junio (pago en julio) y julio–diciembre (pago en diciembre). Diciembre cuenta como
 *   mes completo si el trabajador está en planilla (se paga hasta el 15).
 * - Meses computables: meses calendario completos (ingreso el día 1 o antes).
 * - Remuneración computable = básico + asignación familiar + promedio de variables
 *   (suma del semestre / 6, solo si hubo variables en 3 meses o más).
 * - Gratificación = computable × meses / 6 − computable / 180 × días de falta, × factor del régimen.
 * - Bonificación = gratificación × tasa (9 % EsSalud).
 * - No están afectas a ONP, AFP ni EsSalud.
 */
final class CalculadoraGratificacion
{
    // Códigos de la tabla 22 del PLAME (⚠ verificar contra la tabla oficial vigente).
    public const GRATIFICACION = '0406';

    public const BONIFICACION = '0312';

    private const MESES_SEMESTRE = 6;

    private const DIAS_SEMESTRE = 180;

    private const MESES_MINIMOS_VARIABLES = 3;

    public function calcular(EntradaGratificacion $e, ParametrosGratificacion $p): ResultadoGratificacion
    {
        if (! in_array($e->mesPago, [7, 12], true)) {
            throw new \InvalidArgumentException('La gratificación se paga en julio o diciembre.');
        }

        // Julio paga enero–junio; diciembre paga julio–diciembre (incluye el propio mes de pago).
        [$primerMes, $ultimoMes] = $e->mesPago === 7 ? [1, 6] : [7, 12];
        $meses = 0;
        $faltas = 0;
        $mesesConVariables = 0;
        $sumaVariables = Monto::cero();
        $sinPlanilla = [];

        for ($mes = $primerMes; $mes <= $ultimoMes; $mes++) {
            $inicio = new DateTimeImmutable(sprintf('%04d-%02d-01', $e->anio, $mes));
            if ($e->fechaIngreso > $inicio) {
                continue; // mes incompleto: no cuenta
            }
            $meses++;

            if ($mes === $e->mesPago) {
                continue; // diciembre se paga a mitad de mes: se presume completo, sin faltas ni variables
            }

            $datos = $e->meses[$mes] ?? null;
            if ($datos === null) {
                $sinPlanilla[] = $mes;

                continue;
            }

            $faltas += $datos['faltas'];
            $variables = Monto::de($datos['variables']);
            if ($variables->isPositive()) {
                $mesesConVariables++;
                $sumaVariables = $sumaVariables->plus($variables);
            }
        }

        $promedio = $mesesConVariables >= self::MESES_MINIMOS_VARIABLES
            ? Monto::redondear($sumaVariables->dividedBy(self::MESES_SEMESTRE, 10, RoundingMode::HalfUp))
            : Monto::cero();

        $asignacion = $e->asignacionFamiliar
            ? Monto::redondear(Monto::de($p->rmv)->multipliedBy($p->porcentajeAsignacionFamiliar))
            : Monto::cero();

        $computable = Monto::de($e->remuneracionBasica)->plus($asignacion)->plus($promedio);

        $bruto = $computable->multipliedBy($meses)->dividedBy(self::MESES_SEMESTRE, 10, RoundingMode::HalfUp)
            ->minus($computable->multipliedBy($faltas)->dividedBy(self::DIAS_SEMESTRE, 10, RoundingMode::HalfUp));

        $gratificacion = Monto::redondear(
            BigDecimal::max($bruto, BigDecimal::zero())->multipliedBy($p->factorRegimen)
        );
        $bonificacion = Monto::redondear($gratificacion->multipliedBy($p->tasaBonificacion));

        return new ResultadoGratificacion(
            mesesComputables: $meses,
            diasFalta: $faltas,
            mesesConVariables: $mesesConVariables,
            promedioVariables: Monto::texto($promedio),
            remuneracionComputable: Monto::texto($computable),
            gratificacion: Monto::texto($gratificacion),
            bonificacion: Monto::texto($bonificacion),
            mesesSinPlanilla: $sinPlanilla,
        );
    }
}
