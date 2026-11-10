<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\CalculadoraQuinta;
use App\Domain\Planilla\DatosTrabajador;
use App\Domain\Planilla\EntradaQuinta;
use App\Domain\Planilla\Monto;
use App\Domain\Planilla\ParametrosGratificacion;
use App\Domain\Planilla\ParametrosQuinta;
use App\Domain\Planilla\ResultadoPlanilla;
use App\Domain\Planilla\ResultadoQuinta;
use App\Models\Concepto;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\RentaOtroEmpleador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Arma la entrada de la retención de quinta de cada trabajador de un periodo a partir de la base:
 * lo pagado y retenido en los meses anteriores del año en la misma empresa, los certificados de
 * otros empleadores y la proyección de las gratificaciones que faltan.
 */
class RetencionQuinta
{
    /** Tabla 22: retención de renta de quinta categoría. */
    public const CODIGO = '0605';

    /** Conceptos «ordinarios» (se proyectan o son gratificación); el resto de afectos son extraordinarios ⚠. */
    public const ORDINARIOS = [
        CalculadoraPlanilla::BASICO, CalculadoraPlanilla::ASIGNACION_FAMILIAR, CalcularPlanillaPeriodo::REMUNERACION_VACACIONAL,
        CalculadoraGratificacion::GRATIFICACION, CalculadoraGratificacion::BONIFICACION,
    ];

    /** @var array<string, bool> código => afecto a quinta */
    private array $afectos = [];

    /** @var array<int, array{renta: BigDecimal, retenciones: array<int, string>}> */
    private array $historial = [];

    /** @var array<int, array{renta: string, retencion: string}> */
    private array $otros = [];

    private ParametrosQuinta $parametros;

    private ParametrosGratificacion $gratificacion;

    private Periodo $periodo;

    public function __construct(
        private readonly CalculadoraQuinta $calculadora,
        private readonly ParametrosRepositorio $repositorio,
    ) {}

    /** Carga una sola vez lo que necesitan todas las líneas del periodo. */
    public function preparar(Periodo $periodo): void
    {
        $this->periodo = $periodo;
        $this->parametros = $this->repositorio->quinta($periodo);
        $this->gratificacion = $this->repositorio->gratificacion($periodo);
        $this->afectos = Concepto::query()->pluck('afecto_quinta', 'codigo_plame')->map(fn ($v) => (bool) $v)->all();

        $trabajadores = $periodo->lineas()->pluck('trabajador_id')->all();
        $this->historial = [];

        $filas = DB::table('planilla_conceptos as pc')
            ->join('planilla_lineas as pl', 'pl.id', '=', 'pc.planilla_linea_id')
            ->join('periodos as p', 'p.id', '=', 'pl.periodo_id')
            ->join('conceptos as c', 'c.id', '=', 'pc.concepto_id')
            ->where('p.empresa_id', $periodo->empresa_id)
            ->where('p.anio', $periodo->anio)
            ->where('p.mes', '<', $periodo->mes)
            ->whereIn('pl.trabajador_id', $trabajadores)
            ->where(fn ($q) => $q->where('c.afecto_quinta', true)->orWhere('c.codigo_plame', self::CODIGO))
            ->groupBy('pl.trabajador_id', 'p.mes', 'c.codigo_plame')
            ->selectRaw('pl.trabajador_id as trabajador, p.mes as mes, c.codigo_plame as codigo, SUM(pc.monto_pagado) as monto')
            ->get();

        foreach ($filas as $f) {
            $id = (int) $f->trabajador;
            $this->historial[$id] ??= ['renta' => BigDecimal::zero(), 'retenciones' => []];

            if ($f->codigo === self::CODIGO) {
                $mes = (int) $f->mes;
                $this->historial[$id]['retenciones'][$mes] = (string) BigDecimal::of($this->historial[$id]['retenciones'][$mes] ?? '0')->plus((string) $f->monto);
            } else {
                $this->historial[$id]['renta'] = $this->historial[$id]['renta']->plus((string) $f->monto);
            }
        }

        $this->otros = [];
        foreach (RentaOtroEmpleador::query()->where('anio', $periodo->anio)->whereIn('trabajador_id', $trabajadores)->get() as $r) {
            $actual = $this->otros[$r->trabajador_id] ?? ['renta' => '0', 'retencion' => '0'];
            $this->otros[$r->trabajador_id] = [
                'renta' => (string) BigDecimal::of($actual['renta'])->plus($r->renta),
                'retencion' => (string) BigDecimal::of($actual['retencion'])->plus($r->retencion),
            ];
        }
    }

    public function calcular(PlanillaLinea $linea, DatosTrabajador $datos, ResultadoPlanilla $resultado): ResultadoQuinta
    {
        $ordinaria = BigDecimal::zero();
        $extraordinaria = BigDecimal::zero();

        foreach ($resultado->ingresos as $codigo => $monto) {
            if (! ($this->afectos[$codigo] ?? false)) {
                continue;
            }

            if (in_array($codigo, self::ORDINARIOS, true)) {
                $ordinaria = $ordinaria->plus($monto);
            } else {
                $extraordinaria = $extraordinaria->plus($monto);
            }
        }

        // Remuneración mensual que se proyecta: básico completo + asignación familiar.
        $mensual = Monto::de($datos->remuneracionBasica)->plus($resultado->ingresos[CalculadoraPlanilla::ASIGNACION_FAMILIAR] ?? '0');
        $historial = $this->historial[$linea->trabajador_id] ?? ['renta' => BigDecimal::zero(), 'retenciones' => []];
        $otros = $this->otros[$linea->trabajador_id] ?? ['renta' => '0', 'retencion' => '0'];

        return $this->calculadora->calcular(new EntradaQuinta(
            mes: $this->periodo->mes,
            remuneracionOrdinaria: (string) $mensual,
            ordinariaDelMes: (string) $ordinaria,
            extraordinariaDelMes: (string) $extraordinaria,
            gratificacionesPorPercibir: $this->gratificacionesPorPercibir($linea, $mensual),
            rentaMesesAnteriores: (string) $historial['renta'],
            retencionesPorMes: $historial['retenciones'],
            rentaOtrosEmpleadores: $otros['renta'],
            retencionOtrosEmpleadores: $otros['retencion'],
            cese: $this->cesaEnElPeriodo($linea),
        ), $this->parametros);
    }

    /**
     * Gratificaciones de julio y diciembre que aún no se pagan, estimadas con la remuneración mensual,
     * los meses del semestre desde el ingreso, el factor del régimen y la bonificación extraordinaria ⚠.
     */
    private function gratificacionesPorPercibir(PlanillaLinea $linea, BigDecimal $mensual): string
    {
        $mes = $this->periodo->mes;
        $ingreso = $linea->trabajador->fecha_ingreso;
        $anio = $this->periodo->anio;

        // Primer mes completo trabajado en el año (1 si ingresó antes).
        $inicio = $ingreso->year < $anio ? 1 : ($ingreso->day === 1 ? $ingreso->month : $ingreso->month + 1);
        if ($ingreso->year > $anio) {
            return '0';
        }

        $total = BigDecimal::zero();
        $porGratificacion = $mensual->multipliedBy($this->gratificacion->factorRegimen)
            ->multipliedBy(BigDecimal::one()->plus($this->gratificacion->tasaBonificacion));

        foreach ([7 => [1, 6], 12 => [7, 12]] as $mesPago => [$desde, $hasta]) {
            if ($mes >= $mesPago) {
                continue; // ya se pagó (o se paga este mes y está en lo ordinario del mes)
            }
            $meses = max(0, min(6, $hasta - max($desde, $inicio) + 1));
            $total = $total->plus($porGratificacion->multipliedBy($meses)->dividedBy(6, 10, RoundingMode::HalfUp));
        }

        return (string) Monto::redondear($total);
    }

    private function cesaEnElPeriodo(PlanillaLinea $linea): bool
    {
        $cese = $linea->trabajador->fecha_cese;

        return $cese !== null && $cese->year === $this->periodo->anio && $cese->month === $this->periodo->mes;
    }
}
