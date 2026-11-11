<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraIndemnizacionDespido;
use App\Domain\Planilla\CalculadoraLiquidacion;
use App\Domain\Planilla\EntradaGratificacion;
use App\Domain\Planilla\EntradaIndemnizacion;
use App\Domain\Planilla\EntradaLiquidacion;
use App\Domain\Planilla\ParametrosLiquidacion;
use App\Domain\Planilla\ResultadoGratificacion;
use App\Domain\Planilla\ResultadoLiquidacion;
use App\Enums\EstadoPeriodo;
use App\Enums\TipoContrato;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liquidación de beneficios sociales (BD ⇄ CalculadoraLiquidacion). Sus conceptos se pagan en la planilla del
 * mes del cese, así salen en la boleta, el PLAME y los aportes. Cerrada = inmutable (solo el admin la reabre).
 */
class LiquidacionesBeneficios
{
    public function __construct(
        private readonly CalculadoraLiquidacion $calculadora,
        private readonly CalculadoraGratificacion $gratificacion,
        private readonly ParametrosRepositorio $parametros,
        private readonly HistorialTrabajador $historial,
        private readonly VacacionesTrabajador $vacaciones,
        private readonly LiquidacionesCts $cts,
        private readonly CalculadoraIndemnizacionDespido $indemnizacion,
    ) {}

    /**
     * @param  bool|null  $despidoArbitrario  null = conservar lo elegido en el cálculo anterior (o no)
     * @param  string|null  $finContrato  vencimiento del contrato a plazo fijo (Y-m-d), para el 0503
     */
    public function calcular(Trabajador $trabajador, ?bool $despidoArbitrario = null, ?string $finContrato = null): Liquidacion
    {
        if ($trabajador->fecha_cese === null) {
            throw new \DomainException('Registra primero el cese del trabajador.');
        }

        /** @var Empresa $empresa */
        $empresa = $trabajador->empresa;
        $cese = $trabajador->fecha_cese;

        $existente = Liquidacion::query()->where('trabajador_id', $trabajador->id)->whereDate('fecha_cese', $cese)->first();
        if ($existente?->estaCerrada()) {
            throw new \DomainException('La liquidación está cerrada. Reábrela para recalcular.');
        }

        $periodoCese = $this->periodoDelCese($empresa, $cese);
        if ($periodoCese?->estaCerrado()) {
            throw new \DomainException("La planilla de {$periodoCese->etiqueta()} está cerrada: reábrela para incluir la liquidación.");
        }

        $t = $this->historial->vistaAl($trabajador, $cese);
        $avisos = [];

        // Planillas de los últimos doce meses hasta el cese (faltas, licencias, subsidios y horas extra).
        $meses = [];
        for ($i = 12; $i >= 0; $i--) {
            $m = $cese->startOfMonth()->subMonths($i);
            $meses[] = [$m->year, $m->month];
        }
        $datosMeses = $this->cts->datosMeses($empresa, $meses)[$trabajador->id] ?? [];

        [$gratiPercibida, $avisoGrati] = $this->gratificacionPercibida($empresa, $t, $cese);
        if ($avisoGrati !== null) {
            $avisos[] = $avisoGrati;
        }

        $resultado = $this->calculadora->calcular(new EntradaLiquidacion(
            fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
            fechaCese: $cese->toDateTimeImmutable(),
            remuneracionBasica: (string) $t->remuneracion_basica,
            asignacionFamiliar: $t->asignacion_familiar,
            meses: $datosMeses,
            gratificacionPercibida: $gratiPercibida,
            periodosVacacionales: $this->vacaciones->periodos($trabajador),
        ), $this->parametrosPara($empresa, $cese));

        $avisos = [...$avisos, ...$resultado->avisos];

        // Indemnización por despido arbitrario (art. 38 / art. 76): solo si se indicó; se conserva al recalcular.
        $despido = $despidoArbitrario ?? (bool) $existente?->despido_arbitrario;
        $plazoFijo = $t->tipo_contrato === TipoContrato::PlazoFijo;
        $fin = $plazoFijo ? ($finContrato ?? $existente?->fin_contrato?->toDateString()) : null;
        $indemnizacion = null;
        if ($despido) {
            if ($plazoFijo && $fin === null) {
                throw new \DomainException('Indica la fecha de vencimiento del contrato a plazo fijo para calcular la indemnización.');
            }
            $indemnizacion = $this->indemnizacion->calcular(new EntradaIndemnizacion(
                fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
                fechaCese: $cese->toDateTimeImmutable(),
                remuneracionMensual: $resultado->remuneracionComputableVacaciones,
                finContrato: $fin === null ? null : new \DateTimeImmutable($fin),
            ), $this->parametros->indemnizacion($empresa, $cese->toDateString()));
            if ($indemnizacion->aviso !== null) {
                $avisos[] = $indemnizacion->aviso;
            }
        }
        if ($periodoCese === null) {
            $avisos[] = 'No hay planilla de '.Periodo::MESES[$cese->month]." {$cese->year} en el sistema: la liquidación no se incluirá en una boleta ni en el PLAME.";
        }
        if ($t->entidad_cts === null && $resultado->cts !== null) {
            $avisos[] = 'Falta la entidad CTS en la ficha (para la carta de liberación de la CTS).';
        }

        $montos = $resultado->conceptos;
        $total = BigDecimal::of($resultado->total);
        if ($indemnizacion !== null && BigDecimal::of($indemnizacion->monto)->isPositive()) {
            $montos[$indemnizacion->codigo] = $indemnizacion->monto;
            $total = $total->plus($indemnizacion->monto);
        }
        $codigo = fn (string $columna) => $montos[Liquidacion::CONCEPTOS[$columna]] ?? '0.00';

        $detalle = $this->detalle($resultado);
        if ($indemnizacion !== null) {
            $detalle['indemnizacion'] = $indemnizacion->detalle();
        }

        return DB::transaction(function () use ($trabajador, $empresa, $cese, $codigo, $avisos, $periodoCese, $despido, $fin, $total, $detalle, $existente) {
            // La existente se ubicó con whereDate: la fecha guardada puede traer hora según el motor de BD.
            $liquidacion = $existente ?? new Liquidacion(['trabajador_id' => $trabajador->id, 'fecha_cese' => $cese->toDateString()]);
            $liquidacion->fill(
                [
                    'empresa_id' => $empresa->id,
                    'estado' => Liquidacion::BORRADOR,
                    'despido_arbitrario' => $despido,
                    'fin_contrato' => $fin,
                    ...array_combine(array_keys(Liquidacion::CONCEPTOS), array_map($codigo, array_keys(Liquidacion::CONCEPTOS))),
                    'total' => (string) $total->toScale(2),
                    'detalle' => $detalle,
                    'avisos' => $avisos === [] ? null : array_values(array_unique($avisos)),
                    'calculado_en' => now(),
                ],
            )->save();

            // La planilla del mes del cese debe recalcularse para incluirla.
            if ($periodoCese?->estado === EstadoPeriodo::Calculado) {
                $periodoCese->update(['estado' => EstadoPeriodo::Abierto]);
            }

            return $liquidacion;
        });
    }

    public function cerrar(Liquidacion $liquidacion, User $usuario): void
    {
        if ($liquidacion->estaCerrada()) {
            throw new \DomainException('La liquidación ya está cerrada.');
        }

        DB::transaction(function () use ($liquidacion, $usuario) {
            Auditoria::sinRegistrar(fn () => $liquidacion->update([
                'estado' => Liquidacion::CERRADA, 'cerrado_por' => $usuario->id, 'cerrado_en' => now(),
            ]));
            $liquidacion->registrarAuditoria('cerrado', ['estado' => [Liquidacion::BORRADOR, Liquidacion::CERRADA]]);
        });
    }

    public function reabrir(Liquidacion $liquidacion, string $motivo): void
    {
        if (! $liquidacion->estaCerrada()) {
            throw new \DomainException('La liquidación no está cerrada.');
        }

        DB::transaction(function () use ($liquidacion, $motivo) {
            Auditoria::sinRegistrar(fn () => $liquidacion->update([
                'estado' => Liquidacion::BORRADOR, 'cerrado_por' => null, 'cerrado_en' => null,
            ]));
            $liquidacion->registrarAuditoria('reabierto', [
                'estado' => [Liquidacion::CERRADA, Liquidacion::BORRADOR],
                'motivo' => [null, $motivo],
            ]);
        });
    }

    /**
     * Liquidaciones que se pagan en este periodo (cese dentro del mes), por trabajador.
     *
     * @return Collection<int, Liquidacion>
     */
    public function delPeriodo(Periodo $periodo): Collection
    {
        return Liquidacion::query()->deEmpresa($periodo->empresa_id)
            ->whereYear('fecha_cese', $periodo->anio)->whereMonth('fecha_cese', $periodo->mes)
            ->get()->keyBy('trabajador_id');
    }

    public function parametrosPara(Empresa $empresa, CarbonImmutable $cese): ParametrosLiquidacion
    {
        $fecha = $cese->toDateString();
        $grati = $this->parametros->gratificacionPara($empresa, $fecha);
        $cts = $this->parametros->cts($empresa, $fecha);

        return new ParametrosLiquidacion(
            rmv: $grati->rmv,
            porcentajeAsignacionFamiliar: $grati->porcentajeAsignacionFamiliar,
            factorGratificacion: $grati->factorRegimen,
            factorCts: $cts->factorRegimen,
            tasaBonificacion: $grati->tasaBonificacion,
            diasVacaciones: $this->vacaciones->diasDerecho($empresa, $cese->toDateTimeImmutable()),
        );
    }

    private function periodoDelCese(Empresa $empresa, CarbonImmutable $cese): ?Periodo
    {
        return Periodo::query()->where('empresa_id', $empresa->id)
            ->where('anio', $cese->year)->where('mes', $cese->month)->first();
    }

    /**
     * Gratificación cobrada dentro del periodo de la CTS trunca (para su sexto): julio en mayo–octubre,
     * diciembre en noviembre–abril. Si ese mes ya pasó y no hay planilla, se estima (y se avisa).
     *
     * @return array{0: string|null, 1: string|null} [monto, aviso]
     */
    private function gratificacionPercibida(Empresa $empresa, Trabajador $t, CarbonImmutable $cese): array
    {
        [$anio, $mes] = match (true) {
            $cese->month >= 7 && $cese->month <= 10 => [$cese->year, 7],
            $cese->month >= 12 => [$cese->year, 12],
            $cese->month <= 4 => [$cese->year - 1, 12],
            default => [null, null], // mayo, junio y noviembre: aún no hubo gratificación en el periodo
        };
        if ($anio === null || $mes === null) {
            return [null, null];
        }
        // Cesó en el mes de pago antes del día de pago: la cobra en esta liquidación, no en la planilla.
        if ($cese->year === $anio && $cese->month === $mes && $cese->day < CalculadoraGratificacion::DIA_PAGO) {
            return [null, null];
        }

        $linea = PlanillaLinea::query()->where('trabajador_id', $t->id)->whereNotNull('calculado_en')
            ->whereHas('periodo', fn ($q) => $q->where('empresa_id', $empresa->id)->where('anio', $anio)->where('mes', $mes))
            ->with(['conceptos' => fn ($q) => $q->whereHas('concepto', fn ($c) => $c->where('codigo_plame', CalculadoraGratificacion::GRATIFICACION))])
            ->first();
        if ($linea !== null) {
            return [(string) ($linea->conceptos->first()->monto_pagado ?? '0.00'), null];
        }

        if ($t->fecha_ingreso->gt(CarbonImmutable::create($anio, $mes, 1)->endOfMonth())) {
            return [null, null];
        }

        $estimada = $this->gratificacion->calcular(new EntradaGratificacion(
            anio: $anio,
            mesPago: $mes,
            fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
            remuneracionBasica: (string) $t->remuneracion_basica,
            asignacionFamiliar: $t->asignacion_familiar,
        ), $this->parametros->gratificacionPara($empresa, CarbonImmutable::create($anio, $mes, 1)->endOfMonth()->toDateString()))->gratificacion;

        return BigDecimal::of($estimada)->isPositive()
            ? [$estimada, 'CTS: gratificación de '.Periodo::MESES[$mes]." {$anio} estimada para el sexto (no hay planilla calculada de ese mes)."]
            : [null, null];
    }

    /** @return array<string, mixed> */
    private function detalle(ResultadoLiquidacion $r): array
    {
        $grati = fn (?ResultadoGratificacion $g) => $g === null ? null : [
            'meses' => $g->mesesComputables,
            'faltas' => $g->diasFalta,
            'promedio_variables' => $g->promedioVariables,
            'computable' => $g->remuneracionComputable,
            'gratificacion' => $g->gratificacion,
            'bonificacion' => $g->bonificacion,
        ];

        return [
            'gratificacion_pendiente' => $grati($r->gratificacionPendiente),
            'gratificacion_trunca' => $grati($r->gratificacionTrunca),
            'cts' => $r->cts === null ? null : [
                'desde' => $r->cts->computoDesde->format('Y-m-d'),
                'hasta' => $r->cts->computoHasta->format('Y-m-d'),
                'meses' => $r->cts->meses,
                'dias' => $r->cts->dias,
                'dias_descontados' => $r->cts->diasDescontados,
                'basico' => $r->cts->remuneracionBasica,
                'asignacion' => $r->cts->asignacionFamiliar,
                'sexto_gratificacion' => $r->cts->sextoGratificacion,
                'promedio_variables' => $r->cts->promedioVariables,
                'computable' => $r->cts->remuneracionComputable,
                'monto' => $r->cts->monto,
            ],
            'vacaciones' => [
                'computable' => $r->remuneracionComputableVacaciones,
                'promedio_variables' => $r->promedioVariables,
                'no_gozadas_dias' => $r->vacacionesNoGozadasDias,
                'indemnizacion_dias' => $r->indemnizacionVacacionalDias,
                'truncas_meses' => $r->vacacionesTruncasMeses,
                'truncas_dias' => $r->vacacionesTruncasDias,
            ],
        ];
    }
}
