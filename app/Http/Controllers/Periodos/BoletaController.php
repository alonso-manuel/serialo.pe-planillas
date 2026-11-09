<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Services\Planilla\BoletaPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Boletas de pago en PDF: de un trabajador, todas en un PDF (para imprimir) o en un zip. */
class BoletaController extends Controller
{
    public function __construct(private readonly BoletaPago $boletas) {}

    public function show(Periodo $periodo, PlanillaLinea $linea): Response|RedirectResponse
    {
        Gate::authorize('view', $periodo);

        return $this->generar(
            fn () => $this->pdf($this->boletas->pdf($periodo, $linea), $this->boletas->nombreArchivo($periodo, $linea->loadMissing('trabajador'))),
            to_route('periodos.lineas.show', [$periodo, $linea]),
        );
    }

    public function lote(Periodo $periodo): Response|RedirectResponse
    {
        Gate::authorize('view', $periodo);

        return $this->generar(
            fn () => $this->pdf($this->boletas->pdfLote($periodo), $this->boletas->nombreLote($periodo, 'pdf')),
            to_route('periodos.show', $periodo),
        );
    }

    public function zip(Periodo $periodo): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $periodo);

        return $this->generar(
            fn () => response()->download($this->boletas->zip($periodo), $this->boletas->nombreLote($periodo, 'zip'), [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(),
            to_route('periodos.show', $periodo),
        );
    }

    /**
     * @template T of Response
     *
     * @param  callable(): T  $accion
     * @return T|RedirectResponse
     */
    private function generar(callable $accion, RedirectResponse $siFalla): Response|RedirectResponse
    {
        try {
            return $accion();
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return $siFalla;
        }
    }

    private function pdf(string $contenido, string $nombre): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }
}
