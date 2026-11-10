<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\EntradaGratificacion;
use App\Domain\Planilla\ResultadoGratificacion;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Gratificación de julio/diciembre de cada trabajador de un periodo.
 * Reúne del semestre (periodos anteriores de la misma empresa) las faltas y los conceptos variables,
 * y entrega el cálculo al motor puro CalculadoraGratificacion.
 */
class GratificacionPeriodo
{
    /** Conceptos variables que entran al promedio (⚠ hoy solo horas extra; ver reglas-calculo.md). */
    private const VARIABLES = [CalculadoraPlanilla::HORA_EXTRA_25, CalculadoraPlanilla::HORA_EXTRA_35];

    /** Quien cesa antes de este día del mes de pago no recibe gratificación en la planilla (va a la liquidación). */
    private const DIA_PAGO = CalculadoraGratificacion::DIA_PAGO;

    public function __construct(
        private readonly CalculadoraGratificacion $calculadora,
        private readonly ParametrosRepositorio $parametros,
        private readonly HistorialTrabajador $historial,
    ) {}

    public function aplica(Periodo $periodo): bool
    {
        return in_array($periodo->mes, [7, 12], true);
    }

    /**
     * Resultado por línea del periodo. `null` = no le corresponde en esta planilla (cesó antes del día de pago).
     *
     * @return array<int, ResultadoGratificacion|null> planilla_linea_id => resultado
     */
    public function calcular(Periodo $periodo): array
    {
        if (! $this->aplica($periodo)) {
            return [];
        }

        $periodo->loadMissing(['empresa', 'lineas.trabajador']);
        $parametros = $this->parametros->gratificacion($periodo);
        $semestre = $this->semestre($periodo);
        $diaPago = CarbonImmutable::create($periodo->anio, $periodo->mes, self::DIA_PAGO)->startOfDay();

        $resultados = [];
        foreach ($periodo->lineas as $linea) {
            $t = $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia());

            if ($t->fecha_cese !== null && $t->fecha_cese->lt($diaPago)) {
                $resultados[$linea->id] = null;

                continue;
            }

            $resultados[$linea->id] = $this->calculadora->calcular(
                new EntradaGratificacion(
                    anio: $periodo->anio,
                    mesPago: $periodo->mes,
                    fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
                    remuneracionBasica: (string) $t->remuneracion_basica,
                    asignacionFamiliar: $t->asignacion_familiar,
                    meses: $semestre[$linea->trabajador_id] ?? [],
                ),
                $parametros,
            );
        }

        return $resultados;
    }

    /**
     * Faltas y variables de cada trabajador en los meses del semestre que tienen planilla (antes del mes de pago).
     *
     * @return array<int, array<int, array{faltas: int, variables: string}>> trabajador_id => mes => datos
     */
    private function semestre(Periodo $periodo): array
    {
        $desde = $periodo->mes === 7 ? 1 : 7;

        $lineas = PlanillaLinea::query()
            ->whereHas('periodo', fn ($q) => $q
                ->where('empresa_id', $periodo->empresa_id)
                ->where('anio', $periodo->anio)
                ->whereBetween('mes', [$desde, $periodo->mes - 1]))
            ->with(['periodo:id,mes', 'conceptos' => fn ($q) => $q->whereHas(
                'concepto', fn ($c) => $c->whereIn('codigo_plame', self::VARIABLES),
            )])
            ->get();

        $datos = [];
        foreach ($lineas as $l) {
            $variables = $l->calculado_en === null ? BigDecimal::zero() : $l->conceptos
                ->reduce(fn (BigDecimal $s, PlanillaConcepto $c) => $s->plus($c->monto_pagado), BigDecimal::zero());

            $datos[$l->trabajador_id][$l->periodo->mes] = [
                // Faltas injustificadas y licencias sin goce (suspensión perfecta): no son tiempo computable ⚠.
                'faltas' => $l->dias_no_laborados + $l->dias_licencia,
                'variables' => (string) $variables,
            ];
        }

        return $datos;
    }
}
