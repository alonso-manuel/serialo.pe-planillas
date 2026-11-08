<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Planilla\CierrePeriodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Cerrar y reabrir un periodo (solo admin, auditado). */
class CierrePeriodoController extends Controller
{
    public function cerrar(Request $request, Periodo $periodo, CierrePeriodo $cierre): RedirectResponse
    {
        Gate::authorize('view', $periodo);
        Gate::authorize('cerrar', $periodo);

        /** @var User $usuario */
        $usuario = $request->user();

        return $this->ejecutar(fn () => $cierre->cerrar($periodo, $usuario), "Periodo {$periodo->etiqueta()} cerrado.");
    }

    public function reabrir(Request $request, Periodo $periodo, CierrePeriodo $cierre): RedirectResponse
    {
        Gate::authorize('view', $periodo);
        Gate::authorize('reabrir', $periodo);

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:500']],
            ['motivo.required' => 'Indica por qué se reabre el periodo.', 'motivo.min' => 'Describe el motivo con un poco más de detalle.'],
        );

        return $this->ejecutar(fn () => $cierre->reabrir($periodo, trim($datos['motivo'])), "Periodo {$periodo->etiqueta()} reabierto.");
    }

    private function ejecutar(callable $accion, string $mensaje): RedirectResponse
    {
        try {
            $accion();
            Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
