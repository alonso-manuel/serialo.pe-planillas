<?php

namespace App\Http\Controllers\Conceptos;

use App\Http\Controllers\Controller;
use App\Models\Concepto;
use App\Services\Conceptos\GestionConceptos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EstadoConceptoController extends Controller
{
    public function update(Concepto $concepto, GestionConceptos $gestion): RedirectResponse
    {
        Gate::authorize('update', $concepto);

        try {
            $activo = $gestion->alternarEstado($concepto);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $activo
                    ? "Concepto {$concepto->codigo_plame} activado."
                    : "Concepto {$concepto->codigo_plame} desactivado: ya no aparecerá al registrar novedades.",
            ]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
