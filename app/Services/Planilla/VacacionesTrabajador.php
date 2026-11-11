<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\CalculadoraVacaciones;
use App\Domain\Planilla\PeriodoVacacional;
use App\Enums\ClaveParametro as C;
use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\VacacionGoce;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vacaciones de un trabajador: periodos y saldos (CalculadoraVacaciones), registro de goces y su efecto
 * en las planillas abiertas (días de vacaciones de cada línea; el cálculo paga 0118 remuneración vacacional).
 */
class VacacionesTrabajador
{
    private const VARIABLES = CalculadoraPlanilla::VARIABLES; // horas extra y comisiones

    private const SUBSIDIO_MATERNIDAD = '22';

    private const TOPE_SUBSIDIO_ENFERMEDAD = 60;

    public function __construct(private readonly CalculadoraVacaciones $calculadora) {}

    /** @return list<PeriodoVacacional> */
    public function periodos(Trabajador $trabajador, ?CarbonImmutable $corte = null): array
    {
        /** @var Empresa $empresa */
        $empresa = $trabajador->empresa;
        $corte ??= CarbonImmutable::today();
        if ($trabajador->fecha_cese !== null && $trabajador->fecha_cese->lt($corte)) {
            $corte = $trabajador->fecha_cese->addDay();
        }

        $goces = array_values(VacacionGoce::query()->where('trabajador_id', $trabajador->id)->get()
            ->map(fn (VacacionGoce $g) => ['inicio' => $g->fecha_inicio->toDateTimeImmutable(), 'dias' => $g->dias])
            ->all());

        return $this->calculadora->periodos(
            $trabajador->fecha_ingreso->toDateTimeImmutable(),
            $corte->toDateTimeImmutable(),
            fn (DateTimeImmutable $fecha) => $this->diasDerecho($empresa, $fecha),
            $goces,
            $this->noEfectivos($trabajador),
        );
    }

    /** Saldo disponible: días de periodos cumplidos aún no gozados. */
    public function saldo(Trabajador $trabajador): int
    {
        return array_sum(array_map(fn (PeriodoVacacional $p) => $p->cumplido ? max(0, $p->saldo) : 0, $this->periodos($trabajador)));
    }

    public function registrar(Trabajador $trabajador, CarbonImmutable $inicio, int $dias, ?string $observacion, User $usuario): VacacionGoce
    {
        $fin = CarbonImmutable::instance(CalculadoraVacaciones::fin($inicio->toDateTimeImmutable(), $dias));

        if ($inicio->lt($trabajador->fecha_ingreso)) {
            throw ValidationException::withMessages(['fecha_inicio' => 'Las vacaciones no pueden empezar antes del ingreso.']);
        }
        if ($trabajador->fecha_cese !== null && $fin->gt($trabajador->fecha_cese)) {
            throw ValidationException::withMessages(['dias' => 'Las vacaciones terminan después del cese del trabajador.']);
        }
        $saldo = $this->saldo($trabajador);
        if ($dias > $saldo) {
            throw ValidationException::withMessages(['dias' => "Solo tiene {$saldo} días de vacaciones pendientes."]);
        }
        $cruce = VacacionGoce::query()->where('trabajador_id', $trabajador->id)
            ->whereDate('fecha_inicio', '<=', $fin)->whereDate('fecha_fin', '>=', $inicio)->exists();
        if ($cruce) {
            throw ValidationException::withMessages(['fecha_inicio' => 'Se cruza con otras vacaciones ya registradas.']);
        }
        $this->exigirMesesAbiertos($trabajador, $inicio, $dias);

        return DB::transaction(function () use ($trabajador, $inicio, $dias, $fin, $observacion, $usuario) {
            $goce = VacacionGoce::query()->create([
                'empresa_id' => $trabajador->empresa_id,
                'trabajador_id' => $trabajador->id,
                'fecha_inicio' => $inicio->toDateString(),
                'dias' => $dias,
                'fecha_fin' => $fin->toDateString(),
                'observacion' => $observacion,
                'user_id' => $usuario->id,
            ]);
            $this->actualizarPlanillas($trabajador, $inicio, $dias);

            return $goce;
        });
    }

    public function eliminar(VacacionGoce $goce): void
    {
        $trabajador = $goce->trabajador;
        $this->exigirMesesAbiertos($trabajador, $goce->fecha_inicio, $goce->dias);

        DB::transaction(function () use ($goce, $trabajador) {
            $inicio = $goce->fecha_inicio;
            $dias = $goce->dias;
            $goce->delete();
            $this->actualizarPlanillas($trabajador, $inicio, $dias);
        });
    }

    /** Días de vacaciones del trabajador que caen en un mes (tope 30). */
    public function diasEnMes(Trabajador $trabajador, int $anio, int $mes): int
    {
        $inicioMes = CarbonImmutable::create($anio, $mes, 1);
        $goces = VacacionGoce::query()->where('trabajador_id', $trabajador->id)
            ->whereDate('fecha_inicio', '<=', $inicioMes->endOfMonth())
            ->whereDate('fecha_fin', '>=', $inicioMes)->get();

        $dias = 0;
        foreach ($goces as $g) {
            foreach (CalculadoraVacaciones::reparto($g->fecha_inicio->toDateTimeImmutable(), $g->dias) as [$a, $m, $d]) {
                if ($a === $anio && $m === $mes) {
                    $dias += $d;
                }
            }
        }

        return min($dias, CalculadoraVacaciones::DIAS_MES);
    }

    /** Pone en la línea los días de vacaciones del mes y recalcula los días laborados. */
    public function aplicarALinea(PlanillaLinea $linea, Periodo $periodo): void
    {
        $diasMes = app(NovedadesPlanilla::class)->diasDelMes($linea, $periodo);
        $otros = $linea->dias_no_laborados + $linea->dias_subsidiados + $linea->dias_licencia;
        $vacaciones = min($this->diasEnMes($linea->trabajador, $periodo->anio, $periodo->mes), max(0, $diasMes - $otros));

        $linea->update([
            'dias_vacaciones' => $vacaciones,
            'dias_laborados' => max(0, $diasMes - $otros - $vacaciones),
        ]);
    }

    /** Promedio de horas extra de los 6 meses anteriores (si las hubo en 3 o más) para la remuneración vacacional. */
    public function promedioVariables(PlanillaLinea $linea, Periodo $periodo): string
    {
        $desde = CarbonImmutable::create($periodo->anio, $periodo->mes, 1)->subMonths(6);
        $hasta = CarbonImmutable::create($periodo->anio, $periodo->mes, 1)->subMonth();

        $montos = PlanillaLinea::query()
            ->where('trabajador_id', $linea->trabajador_id)
            ->whereNotNull('calculado_en')
            ->whereHas('periodo', fn ($q) => $q->where('empresa_id', $periodo->empresa_id)
                ->whereRaw('(anio * 12 + mes) between ? and ?', [$desde->year * 12 + $desde->month, $hasta->year * 12 + $hasta->month]))
            ->with(['conceptos' => fn ($q) => $q->whereHas('concepto', fn ($c) => $c->whereIn('codigo_plame', self::VARIABLES))])
            ->get()
            ->map(fn (PlanillaLinea $l) => $l->conceptos->reduce(fn (BigDecimal $s, PlanillaConcepto $c) => $s->plus($c->monto_pagado), BigDecimal::zero()))
            ->filter(fn (BigDecimal $m) => $m->isPositive());

        if ($montos->count() < 3) {
            return '0';
        }

        return (string) $montos->reduce(fn (BigDecimal $s, BigDecimal $m) => $s->plus($m), BigDecimal::zero())
            ->dividedBy(6, 2, RoundingMode::HalfUp);
    }

    /** Días de vacaciones por año según el régimen de la empresa, vigentes en una fecha. */
    public function diasDerecho(Empresa $empresa, DateTimeImmutable $fecha): int
    {
        $clave = match ($empresa->regimen_laboral) {
            RegimenLaboral::General, RegimenLaboral::Agrario => C::DiasVacacionesGeneral, // agrario: 30 días (Ley 31110, art. 3 g)
            RegimenLaboral::PequenaEmpresa => C::DiasVacacionesPequena,
            RegimenLaboral::Microempresa => C::DiasVacacionesMicro,
        };

        return BigDecimal::of(Parametro::vigente($clave, $fecha->format('Y-m-d')))->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * Días no efectivos por año de servicios según las planillas: faltas, licencias sin goce y subsidio
     * por enfermedad que pase de 60 días (D.Leg. 713, art. 12).
     *
     * @return array<int, int> número de periodo => días
     */
    private function noEfectivos(Trabajador $trabajador): array
    {
        $ingreso = $trabajador->fecha_ingreso;
        $porPeriodo = [];
        $subsidio = [];

        $lineas = PlanillaLinea::query()->where('trabajador_id', $trabajador->id)->with('periodo:id,anio,mes')->get();
        foreach ($lineas as $l) {
            $mes = CarbonImmutable::create($l->periodo->anio, $l->periodo->mes, 1)->endOfMonth();
            $numero = (int) floor($ingreso->diffInMonths($mes) / 12) + 1;
            $porPeriodo[$numero] = ($porPeriodo[$numero] ?? 0) + $l->dias_no_laborados + $l->dias_licencia;
            if ($l->tipo_subsidio !== self::SUBSIDIO_MATERNIDAD) {
                $subsidio[$numero] = ($subsidio[$numero] ?? 0) + $l->dias_subsidiados;
            }
        }
        foreach ($subsidio as $numero => $dias) {
            $porPeriodo[$numero] = ($porPeriodo[$numero] ?? 0) + max(0, $dias - self::TOPE_SUBSIDIO_ENFERMEDAD);
        }

        return $porPeriodo;
    }

    /** No se registran ni borran vacaciones en meses cuya planilla ya está cerrada. */
    private function exigirMesesAbiertos(Trabajador $trabajador, CarbonImmutable $inicio, int $dias): void
    {
        foreach (CalculadoraVacaciones::reparto($inicio->toDateTimeImmutable(), $dias) as [$anio, $mes]) {
            $cerrado = Periodo::query()->where('empresa_id', $trabajador->empresa_id)
                ->where('anio', $anio)->where('mes', $mes)->where('estado', EstadoPeriodo::Cerrado)->exists();
            if ($cerrado) {
                throw ValidationException::withMessages([
                    'fecha_inicio' => sprintf('La planilla de %s %d está cerrada: no se pueden cambiar sus vacaciones.', mb_strtolower(Periodo::MESES[$mes]), $anio),
                ]);
            }
        }
    }

    /** Actualiza las líneas de los periodos abiertos afectados; si estaban calculados, vuelven a «abierto». */
    private function actualizarPlanillas(Trabajador $trabajador, CarbonImmutable $inicio, int $dias): void
    {
        foreach (CalculadoraVacaciones::reparto($inicio->toDateTimeImmutable(), $dias) as [$anio, $mes]) {
            $periodo = Periodo::query()->where('empresa_id', $trabajador->empresa_id)->where('anio', $anio)->where('mes', $mes)->first();
            $linea = $periodo?->lineas()->where('trabajador_id', $trabajador->id)->first();
            if ($periodo === null || $linea === null || $periodo->estaCerrado()) {
                continue;
            }
            $linea->setRelation('trabajador', $trabajador);
            $this->aplicarALinea($linea, $periodo);
            if ($periodo->estado === EstadoPeriodo::Calculado) {
                $periodo->update(['estado' => EstadoPeriodo::Abierto]);
            }
        }
    }
}
