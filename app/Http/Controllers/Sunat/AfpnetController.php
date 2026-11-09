<?php

namespace App\Http\Controllers\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Sunat\ExportacionAfpnet;
use App\Services\Sunat\ResumenExportaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Planilla para AFPnet: TXT de una empresa o zip con todas las visibles del mes. */
class AfpnetController extends Controller
{
    public function __construct(
        private readonly ExportacionAfpnet $afpnet,
        private readonly ResumenExportaciones $resumen,
    ) {}

    public function index(Request $request): Response
    {
        [$anio, $mes] = $this->resumen->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('afpnet/Index', $this->resumen->datos($usuario, Exportacion::AFPNET, $anio, $mes));
    }

    public function periodo(Request $request, Periodo $periodo): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        Gate::authorize('view', $periodo);
        /** @var User $usuario */
        $usuario = $request->user();

        try {
            $archivo = $this->afpnet->descargar($periodo, $usuario);
        } catch (\DomainException $e) {
            return $this->volver($e->getMessage(), $periodo->anio, $periodo->mes);
        }

        return response($archivo['contenido'], 200, [
            'Content-Type' => 'text/plain; charset=windows-1252',
            'Content-Disposition' => 'attachment; filename="'.$archivo['nombre'].'"',
        ]);
    }

    public function todas(Request $request): BinaryFileResponse|RedirectResponse
    {
        [$anio, $mes] = $this->resumen->mes($request);
        /** @var User $usuario */
        $usuario = $request->user();

        $periodos = $this->resumen->periodosListos($usuario, $anio, $mes);
        if ($periodos->isEmpty()) {
            return $this->volver('Ninguna empresa tiene cerrado este mes: no hay nada que exportar.', $anio, $mes);
        }

        try {
            $zip = $this->afpnet->zipVarios($periodos, $usuario);
        } catch (\DomainException $e) {
            return $this->volver($e->getMessage(), $anio, $mes);
        }

        return response()->download($zip['ruta'], $this->afpnet->nombreZip($anio, $mes), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function volver(string $mensaje, int $anio, int $mes): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return to_route('afpnet.index', ['anio' => $anio, 'mes' => $mes]);
    }
}
