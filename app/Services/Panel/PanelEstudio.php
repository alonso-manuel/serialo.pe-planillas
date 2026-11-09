<?php

namespace App\Services\Panel;

use App\Domain\Planilla\PeriodoVacacional;
use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Enums\TipoConcepto;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\EnvioBoleta;
use App\Models\Exportacion;
use App\Models\LiquidacionCts;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\TasaAfp;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\VacacionesTrabajador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Panel del estudio: avance del mes en cada empresa visible del usuario (periodo, exportaciones,
 * boletas) y alertas con enlace a donde se resuelven.
 */
class PanelEstudio
{
    public function __construct(
        private readonly EmpresaActiva $empresaActiva,
        private readonly VacacionesTrabajador $vacaciones,
    ) {}

    /** @return array<string, mixed> */
    public function datos(User $usuario, int $anio, int $mes, ?int $empresaId = null): array
    {
        $visibles = $this->empresaActiva->seleccionables($usuario)->get();
        // Filtro opcional por una empresa; solo entre las que el usuario puede ver.
        $empresas = $empresaId === null ? $visibles : $visibles->where('id', $empresaId)->values();
        abort_if($empresaId !== null && $empresas->isEmpty(), 403);
        $ids = $empresas->pluck('id');

        $periodos = Periodo::query()->whereIn('empresa_id', $ids)->where('anio', $anio)->where('mes', $mes)
            ->withCount(['lineas', 'lineas as calculadas_count' => fn ($q) => $q->whereNotNull('calculado_en')])
            ->withSum('lineas', 'neto_pagar')
            ->get()->keyBy('empresa_id');

        $exportaciones = Exportacion::query()->whereIn('empresa_id', $ids)->where('anio', $anio)->where('mes', $mes)
            ->orderBy('id')->get()->groupBy('tipo')->map(fn ($g) => $g->keyBy('empresa_id'));

        $periodoIds = $periodos->pluck('id');
        $enviadas = EnvioBoleta::query()->where('estado', EnvioBoleta::ENVIADO)
            ->join('planilla_lineas', 'planilla_lineas.id', '=', 'envios_boleta.planilla_linea_id')
            ->whereIn('planilla_lineas.periodo_id', $periodoIds)
            ->selectRaw('planilla_lineas.periodo_id, count(distinct envios_boleta.planilla_linea_id) as total')
            ->groupBy('planilla_lineas.periodo_id')->pluck('total', 'periodo_id');
        $conCorreo = PlanillaLinea::query()->whereIn('periodo_id', $periodoIds)
            ->whereHas('trabajador', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->selectRaw('periodo_id, count(*) as total')->groupBy('periodo_id')->pluck('total', 'periodo_id');

        $filas = $empresas->map(function (Empresa $e) use ($periodos, $exportaciones, $enviadas, $conCorreo) {
            /** @var Periodo|null $p */
            $p = $periodos->get($e->id);
            $plame = $exportaciones->get(Exportacion::PLAME)?->get($e->id);
            $afpnet = $exportaciones->get(Exportacion::AFPNET)?->get($e->id);
            $calculadas = (int) ($p?->getAttribute('calculadas_count') ?? 0);

            return [
                'id' => $e->id,
                'razon_social' => $e->razon_social,
                'ruc' => $e->ruc,
                'periodo' => $p === null ? null : [
                    'id' => $p->id,
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'trabajadores' => (int) $p->getAttribute('lineas_count'),
                    'neto' => $calculadas > 0 ? (string) BigDecimal::of((string) ($p->getAttribute('lineas_sum_neto_pagar') ?? '0'))->toScale(2) : null,
                    // Calculado antes y luego editado: volvió a «abierto» con cambios sin recalcular.
                    'desactualizado' => $p->estado === EstadoPeriodo::Abierto && $calculadas > 0,
                ],
                'plame' => $plame?->created_at->format('d/m/Y'),
                'afpnet' => $afpnet?->created_at->format('d/m/Y'),
                'boletas' => $p === null ? null : [
                    'enviadas' => (int) ($enviadas[$p->id] ?? 0),
                    'con_correo' => (int) ($conCorreo[$p->id] ?? 0),
                ],
            ];
        })->values();

        $cerradas = $filas->filter(fn (array $f) => ($f['periodo']['estado'] ?? null) === EstadoPeriodo::Cerrado->value)->count();
        $neto = $filas->reduce(fn (BigDecimal $s, array $f) => $s->plus($f['periodo']['neto'] ?? '0'), BigDecimal::zero());

        return [
            'anio' => $anio,
            'mes' => $mes,
            'etiqueta' => Periodo::MESES[$mes]." {$anio}",
            'empresa_id' => $empresaId,
            'opciones_empresas' => $visibles->map(fn (Empresa $e) => ['id' => $e->id, 'razon_social' => $e->razon_social])->values()->all(),
            'resumen' => [
                'empresas' => $filas->count(),
                'cerradas' => $cerradas,
                'pendientes' => $filas->count() - $cerradas,
                'neto' => (string) $neto->toScale(2),
                'trabajadores' => $filas->sum(fn (array $f) => $f['periodo']['trabajadores'] ?? 0),
            ],
            'empresas' => $filas->all(),
            'alertas' => $this->alertas($usuario, $empresas->all(), $periodos->all(), $anio, $mes, $empresaId === null ? null : $empresas->first()),
            'serie' => $this->serie($ids->all(), $anio, $mes),
            'composicion' => $this->composicion($periodoIds->all()),
            'avance' => [
                'con_periodo' => $filas->filter(fn (array $f) => $f['periodo'] !== null)->count(),
                'calculadas' => $filas->filter(fn (array $f) => $f['periodo'] !== null && $f['periodo']['neto'] !== null && ! $f['periodo']['desactualizado'])->count(),
                'cerradas' => $cerradas,
                'plame' => $filas->filter(fn (array $f) => $f['plame'] !== null)->count(),
                'afpnet' => $filas->filter(fn (array $f) => $f['afpnet'] !== null)->count(),
                'boletas' => $filas->filter(fn (array $f) => $f['boletas'] !== null && $f['boletas']['con_correo'] > 0 && $f['boletas']['enviadas'] >= $f['boletas']['con_correo'])->count(),
            ],
            'movimiento' => $this->movimiento($ids->all(), $anio, $mes),
        ];
    }

    /**
     * Últimos 12 meses hasta el elegido, sumando las líneas calculadas de todas las empresas visibles.
     * Un mes sin planilla calculada va con montos null (hueco en el gráfico, no cero).
     *
     * @param  array<int, int>  $empresaIds
     * @return list<array{anio: int, mes: int, etiqueta: string, costo: string|null, neto: string|null, aportes: string|null, trabajadores: int}>
     */
    private function serie(array $empresaIds, int $anio, int $mes): array
    {
        $fin = CarbonImmutable::create($anio, $mes, 1);
        $inicio = $fin->subMonths(11);

        $filas = PlanillaLinea::query()
            ->join('periodos', 'periodos.id', '=', 'planilla_lineas.periodo_id')
            ->whereIn('periodos.empresa_id', $empresaIds)
            ->whereNotNull('planilla_lineas.calculado_en')
            ->whereRaw('(periodos.anio * 100 + periodos.mes) between ? and ?', [
                $inicio->year * 100 + $inicio->month, $fin->year * 100 + $fin->month,
            ])
            ->groupBy('periodos.anio', 'periodos.mes')
            ->selectRaw('periodos.anio, periodos.mes, count(*) as trabajadores,
                sum(planilla_lineas.total_ingresos) as ingresos,
                sum(planilla_lineas.total_aportes_empleador) as aportes,
                sum(planilla_lineas.neto_pagar) as neto')
            ->toBase()->get()
            ->keyBy(fn (object $f) => ((int) $f->anio) * 100 + (int) $f->mes);

        $serie = [];
        for ($m = $inicio; $m <= $fin; $m = $m->addMonth()) {
            $f = $filas->get($m->year * 100 + $m->month);
            $aportes = $f === null ? null : $this->monto($f->aportes);

            $serie[] = [
                'anio' => $m->year,
                'mes' => $m->month,
                'etiqueta' => mb_substr(Periodo::MESES[$m->month], 0, 3).' '.substr((string) $m->year, 2),
                'costo' => $f === null ? null : (string) $this->monto($f->ingresos)->plus((string) $aportes),
                'neto' => $f === null ? null : (string) $this->monto($f->neto),
                'aportes' => $aportes === null ? null : (string) $aportes,
                'trabajadores' => $f === null ? 0 : (int) $f->trabajadores,
            ];
        }

        return $serie;
    }

    /**
     * Cómo se reparte el costo laboral del mes: lo que recibe el trabajador, lo que se le retiene
     * para su pensión, renta y otros descuentos, y lo que aporta el empleador.
     *
     * @param  array<int, int>  $periodoIds
     * @return array{neto: string, pensiones: string, descuentos: string, aportes: string, total: string}
     */
    private function composicion(array $periodoIds): array
    {
        $totales = PlanillaLinea::query()->whereIn('periodo_id', $periodoIds)->whereNotNull('calculado_en')
            ->selectRaw('sum(neto_pagar) as neto, sum(total_aportes_empleador) as aportes')
            ->toBase()->first();
        $porTipo = PlanillaLinea::query()->whereIn('planilla_lineas.periodo_id', $periodoIds)
            ->whereNotNull('planilla_lineas.calculado_en')
            ->join('planilla_conceptos', 'planilla_conceptos.planilla_linea_id', '=', 'planilla_lineas.id')
            ->join('conceptos', 'conceptos.id', '=', 'planilla_conceptos.concepto_id')
            ->whereIn('conceptos.tipo', [TipoConcepto::AporteTrabajador->value, TipoConcepto::DescuentoTrabajador->value])
            ->groupBy('conceptos.tipo')
            ->selectRaw('conceptos.tipo, sum(planilla_conceptos.monto_pagado) as total')
            ->toBase()->pluck('total', 'tipo');

        $neto = $this->monto($totales->neto ?? null);
        $pensiones = $this->monto($porTipo[TipoConcepto::AporteTrabajador->value] ?? null);
        $descuentos = $this->monto($porTipo[TipoConcepto::DescuentoTrabajador->value] ?? null);
        $aportes = $this->monto($totales->aportes ?? null);

        return [
            'neto' => (string) $neto,
            'pensiones' => (string) $pensiones,
            'descuentos' => (string) $descuentos,
            'aportes' => (string) $aportes,
            'total' => (string) $neto->plus($pensiones)->plus($descuentos)->plus($aportes),
        ];
    }

    /**
     * Altas y bajas del mes y trabajadores vigentes al cierre del mes, en las empresas visibles.
     *
     * @param  array<int, int>  $empresaIds
     * @return array{altas: int, bajas: int, vigentes: int}
     */
    private function movimiento(array $empresaIds, int $anio, int $mes): array
    {
        $inicio = CarbonImmutable::create($anio, $mes, 1);
        $fin = $inicio->endOfMonth();
        $base = fn () => Trabajador::query()->whereIn('empresa_id', $empresaIds);

        return [
            'altas' => $base()->whereBetween('fecha_ingreso', [$inicio->toDateString(), $fin->toDateString()])->count(),
            'bajas' => $base()->whereBetween('fecha_cese', [$inicio->toDateString(), $fin->toDateString()])->count(),
            'vigentes' => $base()->whereDate('fecha_ingreso', '<=', $fin->toDateString())
                ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>', $fin->toDateString()))
                ->count(),
        ];
    }

    /** Suma de la BD (decimal en MySQL; en SQLite puede venir como número) a monto con 2 decimales. */
    private function monto(mixed $valor): BigDecimal
    {
        return BigDecimal::of(is_numeric($valor) ? (string) $valor : '0')->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * @param  array<int, Empresa>  $empresas
     * @param  array<int, Periodo>  $periodos  empresa_id => periodo del mes
     * @return list<array{tipo: string, nivel: string, mensaje: string, enlace: string|null}>
     */
    private function alertas(User $usuario, array $empresas, array $periodos, int $anio, int $mes, ?Empresa $filtrada = null): array
    {
        $alertas = [];

        // Tasas AFP del mes (sin ellas no se calcula a los trabajadores en AFP).
        $afps = Afp::query()->where('activo', true)->count();
        $tasas = TasaAfp::query()->where('anio', $anio)->where('mes', $mes)->count();
        if ($afps > 0 && $tasas < $afps) {
            $alertas[] = [
                'tipo' => 'tasas_afp', 'nivel' => 'error',
                'mensaje' => 'Faltan las tasas AFP de '.mb_strtolower(Periodo::MESES[$mes])." {$anio}: sin ellas no se calcula a los trabajadores en AFP."
                    .($usuario->esAdmin() ? '' : ' Pide al administrador que las cargue.'),
                'enlace' => $usuario->esAdmin() ? route('tasas-afp.index') : null,
            ];
        }

        foreach ($empresas as $e) {
            $p = $periodos[$e->id] ?? null;

            if ($p !== null && $p->estado === EstadoPeriodo::Abierto && (int) $p->getAttribute('calculadas_count') > 0) {
                $alertas[] = [
                    'tipo' => 'desactualizado', 'nivel' => 'aviso',
                    'mensaje' => "{$e->razon_social}: hay cambios sin recalcular en la planilla.",
                    'enlace' => route('periodos.show', $p),
                ];
            }

            $tieneBeneficios = $e->regimen_laboral !== RegimenLaboral::Microempresa;

            if ($tieneBeneficios && in_array($mes, [7, 12], true) && ($p === null || $p->estado === EstadoPeriodo::Abierto)) {
                $alertas[] = [
                    'tipo' => 'gratificacion', 'nivel' => 'aviso',
                    'mensaje' => "{$e->razon_social}: este mes se paga la gratificación (hasta el 15). Calcula la planilla para incluirla.",
                    'enlace' => $p !== null ? route('periodos.show', $p) : route('periodos.index'),
                ];
            }

            if ($tieneBeneficios && in_array($mes, [5, 11], true)) {
                $cts = LiquidacionCts::query()->where('empresa_id', $e->id)->where('anio', $anio)->where('mes', $mes)->first();
                if ($cts === null || ! $cts->estaCerrada()) {
                    $alertas[] = [
                        'tipo' => 'cts', 'nivel' => 'aviso',
                        'mensaje' => "{$e->razon_social}: la CTS de ".mb_strtolower(Periodo::MESES[$mes]).' '.($cts === null ? 'aún no se calcula' : 'no está cerrada').' (depósito hasta el 15).',
                        'enlace' => route('cts.index', ['anio' => $anio, 'mes' => $mes]),
                    ];
                }
            }
        }

        // Vacaciones vencidas: solo de la empresa filtrada o, si no hay filtro, de la activa
        // (calcularlas para todas haría lenta la pantalla).
        $activa = $filtrada ?? $this->empresaActiva->obtener($usuario);
        if ($activa !== null) {
            $hoy = CarbonImmutable::today();
            $vencidos = Trabajador::query()->deEmpresa($activa->id)
                ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>', $hoy))
                ->get()
                ->filter(fn (Trabajador $t) => collect($this->vacaciones->periodos($t))->contains(fn (PeriodoVacacional $p) => $p->vencido))
                ->count();
            if ($vencidos > 0) {
                $alertas[] = [
                    'tipo' => 'vacaciones', 'nivel' => 'aviso',
                    'mensaje' => "{$activa->razon_social}: {$vencidos} ".($vencidos === 1 ? 'trabajador tiene' : 'trabajadores tienen').' vacaciones vencidas sin gozar.',
                    'enlace' => route('vacaciones.index'),
                ];
            }
        }

        return $alertas;
    }
}
