<?php

namespace App\Http\Controllers\Beneficios;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EntidadFinanciera;
use App\Models\LiquidacionCts;
use App\Models\LiquidacionCtsLinea;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\ConstanciaCts;
use App\Services\Planilla\LiquidacionesCts;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** CTS de la empresa activa: cálculo por depósito (mayo/noviembre), cierre y constancias. */
class CtsController extends Controller
{
    public function __construct(
        private readonly LiquidacionesCts $liquidaciones,
        private readonly ConstanciaCts $constancias,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresa($request);
        [$anio, $mes] = $this->deposito($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $liquidacion = LiquidacionCts::query()->with('cerradoPor:id,name')
            ->where('empresa_id', $empresa->id)->where('anio', $anio)->where('mes', $mes)->first();
        $entidades = EntidadFinanciera::query()->pluck('nombre', 'codigo');

        $lineas = $liquidacion?->lineas()->with('trabajador')->get()
            ->sortBy(fn (LiquidacionCtsLinea $l) => $l->trabajador->nombre_completo)->values() ?? collect();

        [$anioHoy, $mesHoy] = LiquidacionesCts::depositoVigente(CarbonImmutable::today());

        return Inertia::render('cts/Index', [
            'empresa' => $empresa->razon_social,
            'anio' => $anio,
            'mes' => $mes,
            'etiqueta' => ($mes === 5 ? 'Mayo' : 'Noviembre')." {$anio}",
            'periodo_computable' => $mes === 5 ? 'noviembre '.($anio - 1)." – abril {$anio}" : "mayo – octubre {$anio}",
            'depositos' => collect(range(0, 5))->map(function (int $i) use ($anioHoy, $mesHoy) {
                // Depósito vigente y los 5 anteriores.
                $indice = $anioHoy * 2 + ($mesHoy === 11 ? 1 : 0) - $i;
                $a = intdiv($indice, 2);
                $m = $indice % 2 === 1 ? 11 : 5;

                return ['anio' => $a, 'mes' => $m, 'etiqueta' => ($m === 5 ? 'Mayo' : 'Noviembre')." {$a}"];
            })->all(),
            'liquidacion' => $liquidacion === null ? null : [
                'id' => $liquidacion->id,
                'estado' => $liquidacion->estado,
                'calculado_en' => $liquidacion->calculado_en?->format('d/m/Y H:i'),
                'cerrado_por' => $liquidacion->cerradoPor?->name,
                'cerrado_en' => $liquidacion->cerrado_en?->format('d/m/Y H:i'),
                'puede_calcular' => $usuario->can('update', $liquidacion),
                'puede_cerrar' => $usuario->can('cerrar', $liquidacion),
                'puede_reabrir' => $usuario->can('reabrir', $liquidacion),
                'total' => (string) $lineas->reduce(fn (BigDecimal $s, LiquidacionCtsLinea $l) => $s->plus($l->monto), BigDecimal::zero())->toScale(2),
            ],
            'lineas' => $lineas->map(fn (LiquidacionCtsLinea $l) => [
                'id' => $l->id,
                'nombre' => $l->trabajador->nombre_completo,
                'documento' => "{$l->trabajador->tipo_documento->abreviatura()} {$l->trabajador->numero_documento}",
                'desde' => $l->computo_desde->format('d/m/Y'),
                'meses' => $l->meses,
                'dias' => $l->dias,
                'dias_descontados' => $l->dias_descontados,
                'computable' => $l->remuneracion_computable,
                'sexto_gratificacion' => $l->sexto_gratificacion,
                'monto' => $l->monto,
                'entidad' => $l->entidad_cts ? ($entidades[$l->entidad_cts] ?? $l->entidad_cts) : null,
                'cuenta' => $l->cuenta_cts,
                'avisos' => $l->avisos ?? [],
            ])->all(),
        ]);
    }

    public function calcular(Request $request): RedirectResponse
    {
        $empresa = $this->empresa($request);
        [$anio, $mes] = $this->deposito($request);

        $existente = LiquidacionCts::query()->where('empresa_id', $empresa->id)->where('anio', $anio)->where('mes', $mes)->first();
        if ($existente !== null) {
            Gate::authorize('update', $existente);
        }

        try {
            $liquidacion = $this->liquidaciones->calcular($empresa, $anio, $mes);
            Inertia::flash('toast', ['type' => 'success', 'message' => "CTS de {$liquidacion->etiqueta()} calculada para {$liquidacion->lineas()->count()} trabajadores."]);
        } catch (\DomainException|\RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('cts.index', ['anio' => $anio, 'mes' => $mes]);
    }

    public function cerrar(Request $request, LiquidacionCts $liquidacion): RedirectResponse
    {
        Gate::authorize('view', $liquidacion);
        Gate::authorize('cerrar', $liquidacion);
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->accion(fn () => $this->liquidaciones->cerrar($liquidacion, $usuario), "CTS de {$liquidacion->etiqueta()} cerrada.", $liquidacion);
    }

    public function reabrir(Request $request, LiquidacionCts $liquidacion): RedirectResponse
    {
        Gate::authorize('view', $liquidacion);
        Gate::authorize('reabrir', $liquidacion);
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:500']],
            ['motivo.required' => 'Indica por qué se reabre la CTS.', 'motivo.min' => 'Describe el motivo con un poco más de detalle.'],
        );

        return $this->accion(fn () => $this->liquidaciones->reabrir($liquidacion, trim($datos['motivo'])), "CTS de {$liquidacion->etiqueta()} reabierta.", $liquidacion);
    }

    public function constancia(LiquidacionCts $liquidacion, LiquidacionCtsLinea $linea): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('view', $liquidacion);
        $linea->loadMissing('trabajador');

        return $this->pdf($this->constancias->pdf($liquidacion, collect([$linea])), $this->constancias->nombreArchivo($liquidacion, $linea));
    }

    public function constanciasPdf(LiquidacionCts $liquidacion): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        Gate::authorize('view', $liquidacion);

        try {
            $lineas = $this->constancias->lineas($liquidacion);
        } catch (\DomainException $e) {
            return $this->volver($e->getMessage(), $liquidacion);
        }

        return $this->pdf($this->constancias->pdf($liquidacion, $lineas), $this->constancias->nombreLote($liquidacion, 'pdf'));
    }

    public function constanciasZip(LiquidacionCts $liquidacion): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $liquidacion);

        try {
            $ruta = $this->constancias->zip($liquidacion);
        } catch (\DomainException $e) {
            return $this->volver($e->getMessage(), $liquidacion);
        }

        return response()->download($ruta, $this->constancias->nombreLote($liquidacion, 'zip'), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function accion(callable $accion, string $mensaje, LiquidacionCts $liquidacion): RedirectResponse
    {
        try {
            $accion();
            Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('cts.index', ['anio' => $liquidacion->anio, 'mes' => $liquidacion->mes]);
    }

    private function volver(string $mensaje, LiquidacionCts $liquidacion): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return to_route('cts.index', ['anio' => $liquidacion->anio, 'mes' => $liquidacion->mes]);
    }

    private function pdf(string $contenido, string $nombre): \Symfony\Component\HttpFoundation\Response
    {
        return response($contenido, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    /** @return array{int, int} año y mes (5 u 11) del depósito; por defecto el vigente. */
    private function deposito(Request $request): array
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'in:5,11'],
        ], ['mes.in' => 'La CTS se deposita en mayo o noviembre.']);

        [$anio, $mes] = LiquidacionesCts::depositoVigente(CarbonImmutable::today());

        return [(int) ($datos['anio'] ?? $anio), (int) ($datos['mes'] ?? $mes)];
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
