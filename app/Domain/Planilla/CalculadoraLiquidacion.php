<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Liquidación de beneficios sociales al cese. Clase PURA: reutiliza los motores de gratificación, CTS y
 * vacaciones con la fecha de cese. Reglas (docs/reglas-calculo.md, ⚠ a validar con el contador):
 *
 * - Gratificación trunca (Ley 27735, art. 7): meses calendario completos del semestre en curso hasta el cese,
 *   con su bonificación extraordinaria. Si cesa en julio antes del día de pago, además la gratificación completa
 *   de enero–junio, que no se cobró en la planilla.
 * - CTS trunca (D.S. 001-97-TR): desde el inicio del periodo en curso (mayo o noviembre) hasta el cese, con
 *   1/6 de la gratificación percibida en ese periodo (la cobrada en planilla o la pendiente de esta liquidación).
 *   El periodo anterior se deposita en mayo/noviembre aunque el trabajador cese después de su cierre.
 * - Vacaciones (D.Leg. 713): los días ganados y no gozados se pagan como remuneración vacacional; si el
 *   periodo venció sin gozarse, además una indemnización por los mismos días (art. 23). El año en curso se paga
 *   como vacaciones truncas: dozavos y treintavos, con al menos un mes de servicios (D.S. 012-92-TR, art. 22).
 *   Remuneración computable = básico + asignación familiar + promedio de variables (6 meses previos, en 3 o más).
 */
final class CalculadoraLiquidacion
{
    // Códigos de la tabla 22 del PLAME (⚠ verificar contra la tabla oficial vigente).
    public const GRATIFICACION_TRUNCA = '0407';

    public const BONIFICACION_TRUNCA = '0313';

    public const CTS = '0904';

    public const VACACIONES_NO_GOZADAS = '0118';

    public const INDEMNIZACION_VACACIONAL = '0911';

    public const VACACIONES_TRUNCAS = '0114';

    /** Los únicos conceptos de la liquidación afectos a pensión y EsSalud (son remuneración). */
    public const AFECTOS = [self::VACACIONES_NO_GOZADAS, self::VACACIONES_TRUNCAS];

    private const MESES_PROMEDIO = 6;

    private const MESES_MINIMOS_VARIABLES = 3;

    public function __construct(
        private readonly CalculadoraGratificacion $gratificaciones = new CalculadoraGratificacion,
        private readonly CalculadoraCts $cts = new CalculadoraCts,
    ) {}

    public function calcular(EntradaLiquidacion $e, ParametrosLiquidacion $p): ResultadoLiquidacion
    {
        $cese = $e->fechaCese;
        $anio = (int) $cese->format('Y');
        $mes = (int) $cese->format('n');
        $avisos = [];

        // 1. Gratificaciones.
        $pendiente = null;
        if ($mes === 7 && (int) $cese->format('j') < CalculadoraGratificacion::DIA_PAGO) {
            $pendiente = $this->gratificacion($e, $p, $anio, 7, null);
            $avisos = [...$avisos, ...$this->avisoSinPlanilla($pendiente, $anio)];
        }
        $trunca = $this->gratificacion($e, $p, $anio, $mes <= 6 ? 7 : 12, $cese);
        if ($trunca !== null) {
            $avisos = [...$avisos, ...$this->avisoSinPlanilla($trunca, $anio)];
        }

        // 2. CTS trunca del periodo en curso.
        [$anioDeposito, $mesDeposito] = match (true) {
            $mes >= 11 => [$anio + 1, 5],
            $mes <= 4 => [$anio, 5],
            default => [$anio, 11],
        };
        // Sexto: la gratificación del periodo de la CTS. Si cesó en julio o diciembre antes del día de pago,
        // la cobra en esta liquidación (pendiente o trunca) y esa es la percibida ⚠.
        $antesDelPago = (int) $cese->format('j') < CalculadoraGratificacion::DIA_PAGO;
        $gratiCts = match (true) {
            $pendiente !== null && $mesDeposito === 11 => $pendiente->gratificacion,
            $mes === 12 && $antesDelPago && $trunca !== null => $trunca->gratificacion,
            default => $e->gratificacionPercibida ?? '0',
        };
        $cts = $this->cts->calcular(new EntradaCts(
            anio: $anioDeposito,
            mesDeposito: $mesDeposito,
            fechaIngreso: $e->fechaIngreso,
            remuneracionBasica: $e->remuneracionBasica,
            asignacionFamiliar: $e->asignacionFamiliar,
            gratificacion: $gratiCts,
            meses: $e->meses,
            fechaCese: $cese,
        ), $p->cts());
        if ($cts->corresponde() && $cts->mesesSinPlanilla !== []) {
            $avisos[] = 'CTS: sin planilla en el sistema '.implode(', ', $cts->mesesSinPlanilla).' (se asumieron completos).';
        }

        // 3. Vacaciones.
        $promedio = $this->promedioVariables($e);
        $asignacion = $e->asignacionFamiliar
            ? Monto::redondear(Monto::de($p->rmv)->multipliedBy($p->porcentajeAsignacionFamiliar))
            : Monto::cero();
        $computable = Monto::de($e->remuneracionBasica)->plus($asignacion)->plus($promedio);

        $noGozados = 0;
        $vencidos = 0;
        [$truncasMeses, $truncasDias] = [0, 0];
        foreach ($e->periodosVacacionales as $periodo) {
            if ($periodo->cumplido) {
                $saldo = max(0, $periodo->saldo);
                $noGozados += $saldo;
                $vencidos += $periodo->vencido ? $saldo : 0;

                continue;
            }
            [$truncasMeses, $truncasDias] = CalculadoraVacaciones::tiempoTrunco($periodo->desde, $cese);
            if ($periodo->saldo < 0) {
                $avisos[] = 'Vacaciones: gozó '.abs($periodo->saldo).' días por adelantado en el año en curso; revisa si corresponde descontarlos ⚠.';
            }
        }
        if ($truncasMeses === 0) {
            [$truncasMeses, $truncasDias] = [0, 0]; // menos de un mes de servicios: no hay récord trunco
        }

        $porDia = fn (int $dias) => Monto::texto(Monto::redondear(
            $computable->multipliedBy($dias)->dividedBy(CalculadoraVacaciones::DIAS_MES, 10, RoundingMode::HalfUp)
        ));
        $vacNoGozadas = $porDia($noGozados);
        $indemnizacion = $porDia($vencidos);
        $vacTruncas = CalculadoraVacaciones::montoTrunco(Monto::texto($computable), $p->diasVacaciones, $truncasMeses, $truncasDias);

        // 4. Conceptos a pagar, en orden.
        $conceptos = array_filter([
            CalculadoraGratificacion::GRATIFICACION => $pendiente?->gratificacion,
            CalculadoraGratificacion::BONIFICACION => $pendiente?->bonificacion,
            self::GRATIFICACION_TRUNCA => $trunca?->gratificacion,
            self::BONIFICACION_TRUNCA => $trunca?->bonificacion,
            self::CTS => $cts->monto,
            self::VACACIONES_NO_GOZADAS => $vacNoGozadas,
            self::INDEMNIZACION_VACACIONAL => $indemnizacion,
            self::VACACIONES_TRUNCAS => $vacTruncas,
        ], fn (?string $monto) => $monto !== null && BigDecimal::of($monto)->isPositive());

        $total = array_reduce($conceptos, fn (BigDecimal $s, string $m) => $s->plus($m), BigDecimal::zero());

        return new ResultadoLiquidacion(
            gratificacionPendiente: $pendiente,
            gratificacionTrunca: $trunca,
            cts: $cts->corresponde() ? $cts : null,
            remuneracionComputableVacaciones: Monto::texto($computable),
            promedioVariables: Monto::texto($promedio),
            vacacionesTruncasMeses: $truncasMeses,
            vacacionesTruncasDias: $truncasDias,
            vacacionesTruncas: $vacTruncas,
            vacacionesNoGozadasDias: $noGozados,
            vacacionesNoGozadas: $vacNoGozadas,
            indemnizacionVacacionalDias: $vencidos,
            indemnizacionVacacional: $indemnizacion,
            conceptos: $conceptos,
            total: Monto::texto(Monto::redondear($total)),
            avisos: array_values(array_unique($avisos)),
        );
    }

    /** Gratificación del semestre que se paga en `$mesPago`, trunca si hay cese. Null si no corresponde. */
    private function gratificacion(EntradaLiquidacion $e, ParametrosLiquidacion $p, int $anio, int $mesPago, ?DateTimeImmutable $cese): ?ResultadoGratificacion
    {
        $meses = [];
        foreach ($e->meses as $clave => $datos) {
            [$a, $m] = array_map('intval', explode('-', $clave));
            if ($a === $anio) {
                $meses[$m] = ['faltas' => $datos['faltas'] + $datos['licencias'], 'variables' => $datos['variables']];
            }
        }

        $r = $this->gratificaciones->calcular(new EntradaGratificacion(
            anio: $anio,
            mesPago: $mesPago,
            fechaIngreso: $e->fechaIngreso,
            remuneracionBasica: $e->remuneracionBasica,
            asignacionFamiliar: $e->asignacionFamiliar,
            meses: $meses,
            fechaCese: $cese,
        ), $p->gratificacion());

        return $r->corresponde() ? $r : null;
    }

    /** @return list<string> */
    private function avisoSinPlanilla(ResultadoGratificacion $r, int $anio): array
    {
        return $r->mesesSinPlanilla === [] ? [] : [
            'Gratificación: sin planilla en el sistema '.implode(', ', array_map(fn (int $m) => sprintf('%04d-%02d', $anio, $m), $r->mesesSinPlanilla)).' (se asumieron completos).',
        ];
    }

    /** Promedio de variables de los 6 meses anteriores al cese, si las hubo en 3 meses o más. */
    private function promedioVariables(EntradaLiquidacion $e): BigDecimal
    {
        $primerDia = $e->fechaCese->modify('first day of this month');
        $suma = Monto::cero();
        $conVariables = 0;

        for ($i = 1; $i <= self::MESES_PROMEDIO; $i++) {
            $clave = $primerDia->modify("-{$i} months")->format('Y-m');
            $variables = Monto::de($e->meses[$clave]['variables'] ?? '0');
            if ($variables->isPositive()) {
                $conVariables++;
                $suma = $suma->plus($variables);
            }
        }

        return $conVariables >= self::MESES_MINIMOS_VARIABLES
            ? Monto::redondear($suma->dividedBy(self::MESES_PROMEDIO, 10, RoundingMode::HalfUp))
            : Monto::cero();
    }
}
