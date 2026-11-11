<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Services\Planilla\PagosPlanilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Archivo genérico de pagos de la planilla (Excel o CSV) para subirlo con la plantilla del banco. */
class PagoController extends Controller
{
    public function __construct(private readonly PagosPlanilla $pagos) {}

    public function descargar(Periodo $periodo, string $formato): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $periodo);
        abort_unless(in_array($formato, ['xlsx', 'csv'], true), 404);

        try {
            $ruta = $formato === 'xlsx' ? $this->pagos->xlsx($periodo) : $this->pagos->csv($periodo);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $tipo = $formato === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';

        return response()->download($ruta, 'pagos-'.Str::slug($periodo->etiqueta()).".{$formato}", ['Content-Type' => $tipo])->deleteFileAfterSend();
    }
}
