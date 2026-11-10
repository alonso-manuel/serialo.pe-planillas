<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraCts;
use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\EntradaCts;
use App\Domain\Planilla\EntradaGratificacion;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\LiquidacionCts;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Liquidación de CTS de una empresa para un depósito (mayo o noviembre).
 * Reúne de las planillas del periodo computable las faltas, licencias, subsidios y variables,
 * y la gratificación del periodo (diciembre o julio); el cálculo lo hace CalculadoraCts.
 */
class LiquidacionesCts
{
    /** Subsidio por maternidad: se computa completo (los demás, hasta 60 días). */
    private const SUBSIDIO_MATERNIDAD = '22';

    private const VARIABLES = [CalculadoraPlanilla::HORA_EXTRA_25, CalculadoraPlanilla::HORA_EXTRA_35];

    public function __construct(
        private readonly CalculadoraCts $calculadora,
        private readonly CalculadoraGratificacion $gratificacion,
        private readonly ParametrosRepositorio $parametros,
        private readonly HistorialTrabajador $historial,
    ) {}

    /**
     * Depósito que corresponde a una fecha: mayo (hasta mayo), noviembre (hasta noviembre) o mayo del año siguiente.
     *
     * @return array{int, int} año y mes
     */
    public static function depositoVigente(CarbonImmutable $hoy): array
    {
        return match (true) {
            $hoy->month <= 5 => [$hoy->year, 5],
            $hoy->month <= 11 => [$hoy->year, 11],
            default => [$hoy->year + 1, 5],
        };
    }

    public function calcular(Empresa $empresa, int $anio, int $mes): LiquidacionCts
    {
        $liquidacion = LiquidacionCts::query()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'anio' => $anio, 'mes' => $mes],
            ['estado' => LiquidacionCts::BORRADOR],
        );
        if ($liquidacion->estaCerrada()) {
            throw new \DomainException("La CTS de {$liquidacion->etiqueta()} está cerrada. Reábrela para recalcular.");
        }

        $meses = CalculadoraCts::mesesDelPeriodo($anio, $mes);
        [$anioFin, $mesFin] = $meses[5];
        $fin = CarbonImmutable::create($anioFin, $mesFin, 1)->endOfMonth();
        [$anioIni, $mesIni] = $meses[0];
        $inicio = CarbonImmutable::create($anioIni, $mesIni, 1);

        $parametros = $this->parametros->cts($empresa, $fin->toDateString());
        $datosMeses = $this->datosMeses($empresa, $meses);
        [$anioGrati, $mesGrati] = $mes === 5 ? [$anio - 1, 12] : [$anio, 7];
        $gratificaciones = $this->gratificacionesPagadas($empresa, $anioGrati, $mesGrati);

        // Trabajadores con vínculo al cierre del periodo. Quien cesó antes cobra su CTS en la liquidación de beneficios.
        $trabajadores = Trabajador::query()->deEmpresa($empresa->id)
            ->whereDate('fecha_ingreso', '<=', $fin)
            ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>', $fin))
            ->get();

        DB::transaction(function () use ($liquidacion, $trabajadores, $anio, $mes, $fin, $inicio, $parametros, $datosMeses, $gratificaciones, $empresa, $anioGrati, $mesGrati) {
            Auditoria::sinRegistrar(fn () => $liquidacion->lineas()->delete());

            foreach ($trabajadores as $trabajador) {
                $t = $this->historial->vistaAl($trabajador, $fin);
                $avisos = [];

                $grati = $gratificaciones[$trabajador->id] ?? null;
                if ($grati === null) {
                    $grati = $this->estimarGratificacion($empresa, $t, $anioGrati, $mesGrati);
                    if (BigDecimal::of($grati)->isPositive()) {
                        $avisos[] = 'Gratificación de '.($mesGrati === 12 ? 'diciembre' : 'julio')." {$anioGrati} estimada: no hay planilla calculada de ese mes.";
                    }
                }

                $r = $this->calculadora->calcular(new EntradaCts(
                    anio: $anio,
                    mesDeposito: $mes,
                    fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
                    remuneracionBasica: (string) $t->remuneracion_basica,
                    asignacionFamiliar: $t->asignacion_familiar,
                    gratificacion: $grati,
                    meses: $datosMeses[$trabajador->id] ?? [],
                ), $parametros);

                if (! $r->corresponde()) {
                    continue;
                }
                if ($r->mesesSinPlanilla !== []) {
                    $avisos[] = 'Sin planilla en el sistema: '.implode(', ', $r->mesesSinPlanilla).' (se asumieron completos).';
                }
                if ($t->entidad_cts === null || $t->cuenta_cts === null) {
                    $avisos[] = 'Falta la entidad o la cuenta CTS en la ficha.';
                }

                $liquidacion->lineas()->create([
                    'trabajador_id' => $trabajador->id,
                    'computo_desde' => max($inicio, CarbonImmutable::instance($r->computoDesde))->toDateString(),
                    'computo_hasta' => $fin->toDateString(),
                    'meses' => $r->meses,
                    'dias' => $r->dias,
                    'dias_descontados' => $r->diasDescontados,
                    'remuneracion_basica' => $r->remuneracionBasica,
                    'asignacion_familiar' => $r->asignacionFamiliar,
                    'sexto_gratificacion' => $r->sextoGratificacion,
                    'promedio_variables' => $r->promedioVariables,
                    'remuneracion_computable' => $r->remuneracionComputable,
                    'monto' => $r->monto,
                    'entidad_cts' => $t->entidad_cts,
                    'cuenta_cts' => $t->cuenta_cts,
                    'avisos' => $avisos === [] ? null : $avisos,
                ]);
            }

            $liquidacion->update(['calculado_en' => now()]);
        });

        return $liquidacion->refresh();
    }

    public function cerrar(LiquidacionCts $liquidacion, User $usuario): void
    {
        if ($liquidacion->estaCerrada()) {
            throw new \DomainException('La CTS ya está cerrada.');
        }
        if ($liquidacion->calculado_en === null || ! $liquidacion->lineas()->exists()) {
            throw new \DomainException('Calcula la CTS antes de cerrarla.');
        }

        DB::transaction(function () use ($liquidacion, $usuario) {
            Auditoria::sinRegistrar(fn () => $liquidacion->update([
                'estado' => LiquidacionCts::CERRADA, 'cerrado_por' => $usuario->id, 'cerrado_en' => now(),
            ]));
            $liquidacion->registrarAuditoria('cerrado', ['estado' => [LiquidacionCts::BORRADOR, LiquidacionCts::CERRADA]]);
        });
    }

    public function reabrir(LiquidacionCts $liquidacion, string $motivo): void
    {
        if (! $liquidacion->estaCerrada()) {
            throw new \DomainException('La CTS no está cerrada.');
        }

        DB::transaction(function () use ($liquidacion, $motivo) {
            Auditoria::sinRegistrar(fn () => $liquidacion->update([
                'estado' => LiquidacionCts::BORRADOR, 'cerrado_por' => null, 'cerrado_en' => null,
            ]));
            $liquidacion->registrarAuditoria('reabierto', [
                'estado' => [LiquidacionCts::CERRADA, LiquidacionCts::BORRADOR],
                'motivo' => [null, $motivo],
            ]);
        });
    }

    /**
     * Faltas, licencias, subsidios por enfermedad y variables por trabajador y mes ("AAAA-MM").
     *
     * @param  list<array{int, int}>  $meses
     * @return array<int, array<string, array{faltas: int, licencias: int, subsidio_enfermedad: int, variables: string}>>
     */
    public function datosMeses(Empresa $empresa, array $meses): array
    {
        $lineas = PlanillaLinea::query()
            ->whereHas('periodo', function ($q) use ($empresa, $meses) {
                $q->where('empresa_id', $empresa->id)->where(function ($q) use ($meses) {
                    foreach ($meses as [$anio, $mes]) {
                        $q->orWhere(fn ($q) => $q->where('anio', $anio)->where('mes', $mes));
                    }
                });
            })
            ->with(['periodo:id,anio,mes', 'conceptos' => fn ($q) => $q->whereHas(
                'concepto', fn ($c) => $c->whereIn('codigo_plame', self::VARIABLES),
            )])
            ->get();

        $datos = [];
        foreach ($lineas as $l) {
            $variables = $l->calculado_en === null ? BigDecimal::zero() : $l->conceptos
                ->reduce(fn (BigDecimal $s, PlanillaConcepto $c) => $s->plus($c->monto_pagado), BigDecimal::zero());

            $datos[$l->trabajador_id][sprintf('%04d-%02d', $l->periodo->anio, $l->periodo->mes)] = [
                'faltas' => $l->dias_no_laborados,
                'licencias' => $l->dias_licencia,
                'subsidio_enfermedad' => $l->tipo_subsidio === self::SUBSIDIO_MATERNIDAD ? 0 : $l->dias_subsidiados,
                'variables' => (string) $variables,
            ];
        }

        return $datos;
    }

    /**
     * Gratificación pagada (0406) por trabajador en la planilla calculada del mes; null si no hay planilla.
     *
     * @return array<int, string> trabajador_id => monto
     */
    private function gratificacionesPagadas(Empresa $empresa, int $anio, int $mes): array
    {
        $lineas = PlanillaLinea::query()
            ->whereNotNull('calculado_en')
            ->whereHas('periodo', fn ($q) => $q->where('empresa_id', $empresa->id)->where('anio', $anio)->where('mes', $mes))
            ->with(['conceptos' => fn ($q) => $q->whereHas('concepto', fn ($c) => $c->where('codigo_plame', CalculadoraGratificacion::GRATIFICACION))])
            ->get();

        $montos = [];
        foreach ($lineas as $l) {
            $montos[$l->trabajador_id] = (string) ($l->conceptos->first()->monto_pagado ?? '0.00');
        }

        return $montos;
    }

    /** Si no hay planilla de diciembre/julio, se estima la gratificación con el motor (meses completos, sin faltas). */
    private function estimarGratificacion(Empresa $empresa, Trabajador $t, int $anio, int $mes): string
    {
        $fecha = CarbonImmutable::create($anio, $mes, 1)->endOfMonth();
        if ($t->fecha_ingreso->gt($fecha)) {
            return '0';
        }

        return $this->gratificacion->calcular(new EntradaGratificacion(
            anio: $anio,
            mesPago: $mes,
            fechaIngreso: $t->fecha_ingreso->toDateTimeImmutable(),
            remuneracionBasica: (string) $t->remuneracion_basica,
            asignacionFamiliar: $t->asignacion_familiar,
        ), $this->parametros->gratificacionPara($empresa, $fecha->toDateString()))->gratificacion;
    }
}
