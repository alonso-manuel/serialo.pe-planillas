<?php

namespace App\Http\Controllers\Beneficios;

use App\Enums\MotivoCese;
use App\Enums\TipoContrato;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\DocumentosLiquidacion;
use App\Services\Planilla\LiquidacionesBeneficios;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/** Liquidaciones de beneficios sociales de la empresa activa: trabajadores cesados, cálculo, cierre y documentos. */
class LiquidacionController extends Controller
{
    /** Etiquetas de cada columna de monto, en el orden del detalle. */
    private const CONCEPTOS = [
        'gratificacion_pendiente' => 'Gratificación del semestre',
        'bonificacion_pendiente' => 'Bonificación extraordinaria',
        'gratificacion_trunca' => 'Gratificación trunca',
        'bonificacion_trunca' => 'Bonificación extraordinaria (trunca)',
        'cts' => 'CTS trunca',
        'vacaciones_no_gozadas' => 'Vacaciones no gozadas',
        'indemnizacion_vacacional' => 'Indemnización vacacional',
        'vacaciones_truncas' => 'Vacaciones truncas',
        'indemnizacion_despido' => 'Indemnización por despido arbitrario',
        'indemnizacion_plazo_fijo' => 'Indemnización por resolución del plazo fijo',
    ];

    public function __construct(
        private readonly LiquidacionesBeneficios $liquidaciones,
        private readonly DocumentosLiquidacion $documentos,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresa($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $cesados = Trabajador::query()->deEmpresa($empresa->id)->whereNotNull('fecha_cese')
            ->orderByDesc('fecha_cese')->get();
        $liquidaciones = Liquidacion::query()->deEmpresa($empresa->id)->with('cerradoPor:id,name')->get()
            ->keyBy(fn (Liquidacion $l) => $l->trabajador_id.'|'.$l->fecha_cese->toDateString());

        return Inertia::render('liquidaciones/Index', [
            'empresa' => $empresa->razon_social,
            'filas' => $cesados->map(function (Trabajador $t) use ($liquidaciones, $usuario) {
                /** @var Liquidacion|null $l */
                $l = $liquidaciones->get($t->id.'|'.$t->fecha_cese?->toDateString());

                return [
                    'trabajador_id' => $t->id,
                    'nombre' => $t->nombre_completo,
                    'documento' => "{$t->tipo_documento->abreviatura()} {$t->numero_documento}",
                    'cargo' => $t->cargo,
                    'ingreso' => $t->fecha_ingreso->format('d/m/Y'),
                    'cese' => $t->fecha_cese?->format('d/m/Y'),
                    'motivo' => $t->motivo_cese?->etiqueta(),
                    'es_despido' => $t->motivo_cese === MotivoCese::Despido,
                    'plazo_fijo' => $t->tipo_contrato === TipoContrato::PlazoFijo,
                    'puede_calcular' => $l === null ? $usuario->can('update', $t) : $usuario->can('update', $l),
                    'liquidacion' => $l === null ? null : [
                        'id' => $l->id,
                        'estado' => $l->estado,
                        'total' => (string) $l->total,
                        'despido_arbitrario' => $l->despido_arbitrario,
                        'fin_contrato' => $l->fin_contrato?->toDateString(),
                        'conceptos' => collect(self::CONCEPTOS)
                            ->map(fn (string $etiqueta, string $columna) => ['etiqueta' => $etiqueta, 'monto' => (string) $l->getAttribute($columna)])
                            ->filter(fn (array $c) => $c['monto'] !== '0.00')->values()->all(),
                        'avisos' => $l->avisos ?? [],
                        'calculado_en' => $l->calculado_en?->format('d/m/Y H:i'),
                        'cerrado_por' => $l->cerradoPor?->name,
                        'cerrado_en' => $l->cerrado_en?->format('d/m/Y H:i'),
                        'puede_cerrar' => $usuario->can('cerrar', $l),
                        'puede_reabrir' => $usuario->can('reabrir', $l),
                    ],
                ];
            })->all(),
        ]);
    }

    public function calcular(Request $request, Trabajador $trabajador): RedirectResponse
    {
        $this->deLaEmpresaActiva($request, $trabajador);
        Gate::authorize('update', $trabajador);

        $datos = $request->validate([
            'despido_arbitrario' => ['sometimes', 'boolean'],
            'fin_contrato' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['despido_arbitrario' => 'despido arbitrario', 'fin_contrato' => 'vencimiento del contrato']);

        try {
            $liquidacion = $this->liquidaciones->calcular(
                $trabajador,
                array_key_exists('despido_arbitrario', $datos) ? (bool) $datos['despido_arbitrario'] : null,
                $datos['fin_contrato'] ?? null,
            );
            Inertia::flash('toast', ['type' => 'success', 'message' => "Liquidación de {$trabajador->nombre_completo} calculada: S/ ".Formato::monto((string) $liquidacion->total).'.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('liquidaciones.index');
    }

    public function cerrar(Request $request, Liquidacion $liquidacion): RedirectResponse
    {
        $this->deLaEmpresaActiva($request, $liquidacion);
        Gate::authorize('cerrar', $liquidacion);
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->accion(fn () => $this->liquidaciones->cerrar($liquidacion, $usuario), 'Liquidación cerrada.');
    }

    public function reabrir(Request $request, Liquidacion $liquidacion): RedirectResponse
    {
        $this->deLaEmpresaActiva($request, $liquidacion);
        Gate::authorize('reabrir', $liquidacion);
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:500']],
            ['motivo.required' => 'Indica por qué se reabre la liquidación.', 'motivo.min' => 'Describe el motivo con un poco más de detalle.'],
        );

        return $this->accion(fn () => $this->liquidaciones->reabrir($liquidacion, trim($datos['motivo'])), 'Liquidación reabierta.');
    }

    public function hoja(Request $request, Liquidacion $liquidacion): RespuestaHttp
    {
        $this->deLaEmpresaActiva($request, $liquidacion);
        Gate::authorize('view', $liquidacion);
        $liquidacion->loadMissing('trabajador');
        /** @var Trabajador $t */
        $t = $liquidacion->trabajador;

        return $this->pdf($this->documentos->hoja($liquidacion), $this->documentos->nombre('liquidacion', $t));
    }

    public function cartaCts(Request $request, Liquidacion $liquidacion): RespuestaHttp
    {
        $this->deLaEmpresaActiva($request, $liquidacion);
        Gate::authorize('view', $liquidacion);
        $liquidacion->loadMissing('trabajador');
        /** @var Trabajador $t */
        $t = $liquidacion->trabajador;

        return $this->pdf($this->documentos->cartaCts($liquidacion), $this->documentos->nombre('carta-cts', $t));
    }

    public function certificado(Request $request, Trabajador $trabajador): RespuestaHttp
    {
        $this->deLaEmpresaActiva($request, $trabajador);
        Gate::authorize('view', $trabajador);

        return $this->pdf($this->documentos->certificado($trabajador), $this->documentos->nombre('certificado-trabajo', $trabajador));
    }

    private function accion(callable $accion, string $mensaje): RedirectResponse
    {
        try {
            $accion();
            Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('liquidaciones.index');
    }

    private function pdf(string $contenido, string $nombre): RespuestaHttp
    {
        return response($contenido, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    /** Solo se trabaja con datos de la empresa activa (lo demás no existe para esta pantalla). */
    private function deLaEmpresaActiva(Request $request, Trabajador|Liquidacion $modelo): void
    {
        abort_unless($modelo->empresa_id === $this->empresa($request)->id, 404);
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
