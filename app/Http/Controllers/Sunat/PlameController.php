<?php

namespace App\Http\Controllers\Sunat;

use App\Enums\EstadoPeriodo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Sunat\ExportacionPlame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exportación al PLAME: una empresa o todas las visibles del mes, en zip. */
class PlameController extends Controller
{
    public function __construct(
        private readonly ExportacionPlame $plame,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        [$anio, $mes] = $this->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $empresas = $this->empresaActiva->seleccionables($usuario)->get();
        $periodos = Periodo::query()->whereIn('empresa_id', $empresas->pluck('id'))
            ->where('anio', $anio)->where('mes', $mes)
            ->withCount(['lineas' => fn ($q) => $q->whereNotNull('calculado_en')])
            ->get()->keyBy('empresa_id');
        $ultimas = Exportacion::query()->with('usuario:id,name')
            ->where('tipo', Exportacion::PLAME)->where('anio', $anio)->where('mes', $mes)
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->orderBy('id')->get()->keyBy('empresa_id');

        return Inertia::render('plame/Index', [
            'anio' => $anio,
            'mes' => $mes,
            'etiqueta' => Periodo::MESES[$mes]." {$anio}",
            'empresas' => $empresas->map(function (Empresa $e) use ($periodos, $ultimas) {
                /** @var Periodo|null $p */
                $p = $periodos->get($e->id);
                /** @var Exportacion|null $x */
                $x = $ultimas->get($e->id);

                return [
                    'id' => $e->id,
                    'razon_social' => $e->razon_social,
                    'ruc' => $e->ruc,
                    'periodo' => $p === null ? null : [
                        'id' => $p->id,
                        'estado' => $p->estado->value,
                        'estado_etiqueta' => $p->estado->etiqueta(),
                        'trabajadores' => (int) $p->getAttribute('lineas_count'),
                    ],
                    'listo' => $p !== null && $p->estado === EstadoPeriodo::Cerrado && (int) $p->getAttribute('lineas_count') > 0,
                    'ultima' => $x === null ? null : $this->exportacion($x),
                ];
            })->values()->all(),
            'historial' => Exportacion::query()->with(['usuario:id,name', 'empresa:id,razon_social,ruc'])
                ->where('tipo', Exportacion::PLAME)
                ->whereIn('empresa_id', $empresas->pluck('id'))
                ->latest('id')->limit(20)->get()
                ->map(fn (Exportacion $x) => [
                    ...$this->exportacion($x),
                    'empresa' => $x->empresa?->razon_social,
                    'mes' => sprintf('%02d/%d', $x->mes, $x->anio),
                ])->all(),
        ]);
    }

    public function periodo(Request $request, Periodo $periodo): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $periodo);
        /** @var User $usuario */
        $usuario = $request->user();
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;

        return $this->descargar(
            fn () => $this->plame->zipPeriodo($periodo, $usuario),
            $this->plame->nombreZip($periodo->anio, $periodo->mes, $empresa->ruc),
            $periodo->anio,
            $periodo->mes,
        );
    }

    public function todas(Request $request): BinaryFileResponse|RedirectResponse
    {
        [$anio, $mes] = $this->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $periodos = Periodo::query()->with('empresa')
            ->whereIn('empresa_id', $this->empresaActiva->seleccionables($usuario)->pluck('empresas.id'))
            ->where('anio', $anio)->where('mes', $mes)
            ->where('estado', EstadoPeriodo::Cerrado)
            ->whereHas('lineas', fn ($q) => $q->whereNotNull('calculado_en'))
            ->get();

        if ($periodos->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ninguna empresa tiene cerrado este mes: no hay nada que exportar.']);

            return to_route('plame.index', ['anio' => $anio, 'mes' => $mes]);
        }

        return $this->descargar(fn () => $this->plame->zipVarios($periodos, $usuario), $this->plame->nombreZip($anio, $mes), $anio, $mes);
    }

    /** @param  callable(): string  $generar  devuelve la ruta del zip temporal */
    private function descargar(callable $generar, string $nombre, int $anio, int $mes): BinaryFileResponse|RedirectResponse
    {
        try {
            $ruta = $generar();
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('plame.index', ['anio' => $anio, 'mes' => $mes]);
        }

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** @return array{int, int} año y mes pedidos; por defecto el mes anterior (el PLAME se declara al mes siguiente). */
    private function mes(Request $request): array
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $anterior = now()->startOfMonth()->subMonth();

        return [(int) ($datos['anio'] ?? $anterior->year), (int) ($datos['mes'] ?? $anterior->month)];
    }

    /** @return array{id: int, fecha: string, usuario: string|null, archivos: list<array{nombre: string, sha256: string, bytes: int}>} */
    private function exportacion(Exportacion $x): array
    {
        return [
            'id' => $x->id,
            'fecha' => $x->created_at->format('d/m/Y H:i'),
            'usuario' => $x->usuario?->name,
            'archivos' => $x->archivos,
        ];
    }
}
