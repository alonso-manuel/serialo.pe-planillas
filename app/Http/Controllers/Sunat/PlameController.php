<?php

namespace App\Http\Controllers\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Sunat\ExportacionPlame;
use App\Services\Sunat\ResumenExportaciones;
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
        private readonly ResumenExportaciones $resumen,
    ) {}

    public function index(Request $request): Response
    {
        [$anio, $mes] = $this->resumen->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('plame/Index', $this->resumen->datos($usuario, Exportacion::PLAME, $anio, $mes));
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
        [$anio, $mes] = $this->resumen->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $periodos = $this->resumen->periodosListos($usuario, $anio, $mes);
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
}
