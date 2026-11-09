<?php

namespace App\Services\Panel;

use App\Domain\Planilla\PeriodoVacacional;
use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
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
    public function datos(User $usuario, int $anio, int $mes): array
    {
        $empresas = $this->empresaActiva->seleccionables($usuario)->get();
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
            'resumen' => [
                'empresas' => $filas->count(),
                'cerradas' => $cerradas,
                'pendientes' => $filas->count() - $cerradas,
                'neto' => (string) $neto->toScale(2),
                'trabajadores' => $filas->sum(fn (array $f) => $f['periodo']['trabajadores'] ?? 0),
            ],
            'empresas' => $filas->all(),
            'alertas' => $this->alertas($usuario, $empresas->all(), $periodos->all(), $anio, $mes),
        ];
    }

    /**
     * @param  array<int, Empresa>  $empresas
     * @param  array<int, Periodo>  $periodos  empresa_id => periodo del mes
     * @return list<array{tipo: string, nivel: string, mensaje: string, enlace: string|null}>
     */
    private function alertas(User $usuario, array $empresas, array $periodos, int $anio, int $mes): array
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

        // Vacaciones vencidas: solo de la empresa activa (calcularlas para todas haría lenta la pantalla).
        $activa = $this->empresaActiva->obtener($usuario);
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
