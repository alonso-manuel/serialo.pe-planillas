<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\DatosTrabajador;
use App\Domain\Planilla\EntradaPlanilla;
use App\Domain\Planilla\IngresoAdicional;
use App\Enums\EstadoPeriodo;
use App\Enums\TipoConcepto;
use App\Models\Concepto;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Calcula y guarda la planilla de todos los trabajadores de un periodo.
 * Une la base de datos (modelos) con el motor puro (CalculadoraPlanilla).
 */
class CalcularPlanillaPeriodo
{
    public function __construct(
        private readonly CalculadoraPlanilla $calculadora,
        private readonly ParametrosRepositorio $parametros,
        private readonly HistorialTrabajador $historial,
        private readonly VerificadorCalculo $verificador,
    ) {}

    /**
     * Calcula todo el periodo o nada: si falta algún dato, lanza ProblemasDeCalculo con la lista completa.
     *
     * @return array{trabajadores: int, neto: string}
     *
     * @throws ProblemasDeCalculo
     */
    public function ejecutar(Periodo $periodo): array
    {
        if ($periodo->estaCerrado()) {
            throw new \DomainException('El periodo está cerrado. Reábrelo para recalcular.');
        }

        $problemas = $this->verificador->problemas($periodo);
        if ($problemas !== []) {
            throw new ProblemasDeCalculo($problemas);
        }

        $parametros = $this->parametros->delPeriodo($periodo);
        $conceptos = Concepto::query()->pluck('id', 'codigo_plame');

        DB::transaction(function () use ($periodo, $parametros, $conceptos) {
            $periodo->load('lineas.trabajador', 'lineas.novedades.concepto');

            foreach ($periodo->lineas as $linea) {
                $resultado = $this->calculadora->calcular(
                    $this->datosTrabajador($linea, $periodo),
                    $this->entrada($linea),
                    $parametros,
                );

                $linea->conceptos()->delete();
                foreach ($resultado->conceptos() as $codigo => $monto) {
                    $linea->conceptos()->create([
                        'concepto_id' => $conceptos[$codigo] ?? throw new \RuntimeException("Concepto {$codigo} no existe."),
                        'monto_devengado' => $monto,
                        'monto_pagado' => $monto,
                    ]);
                }

                $linea->update([
                    'total_ingresos' => $resultado->totalIngresos,
                    'total_descuentos' => $resultado->totalDescuentos,
                    'total_aportes_empleador' => $resultado->totalAportesEmpleador,
                    'neto_pagar' => $resultado->netoPagar,
                    'calculado_en' => now(),
                ]);
            }

            $periodo->update(['estado' => EstadoPeriodo::Calculado]);
        });

        $neto = $periodo->lineas()->pluck('neto_pagar')
            ->reduce(fn (BigDecimal $suma, $n) => $suma->plus((string) $n), BigDecimal::zero());

        return [
            'trabajadores' => $periodo->lineas()->count(),
            'neto' => (string) $neto->toScale(2),
        ];
    }

    private function datosTrabajador(PlanillaLinea $linea, Periodo $periodo): DatosTrabajador
    {
        // Valores vigentes al cierre del mes (regla 3: un periodo antiguo se recalcula igual).
        $t = $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia());

        return new DatosTrabajador(
            remuneracionBasica: (string) $t->remuneracion_basica,
            asignacionFamiliar: $t->asignacion_familiar,
            sistemaPension: $t->sistema_pension,
            seguroSalud: $t->seguro_salud,
            tasaAfp: $this->parametros->tasaAfp($t, $periodo),
        );
    }

    private function entrada(PlanillaLinea $linea): EntradaPlanilla
    {
        $ingresos = [];
        $descuentos = [];
        foreach ($linea->novedades as $novedad) {
            $c = $novedad->concepto;
            if ($c->tipo === TipoConcepto::Ingreso) {
                $ingresos[$c->codigo_plame] = new IngresoAdicional((string) $novedad->monto, $c->afecto_afp || $c->afecto_onp, $c->afecto_essalud);
            } elseif ($c->tipo === TipoConcepto::DescuentoTrabajador) {
                $descuentos[$c->codigo_plame] = (string) $novedad->monto;
            }
        }

        return new EntradaPlanilla(
            diasLaborados: $linea->dias_laborados,
            horasExtra25: (string) $linea->horas_extra_25,
            horasExtra35: (string) $linea->horas_extra_35,
            ingresosAdicionales: $ingresos,
            descuentos: $descuentos,
        );
    }
}
