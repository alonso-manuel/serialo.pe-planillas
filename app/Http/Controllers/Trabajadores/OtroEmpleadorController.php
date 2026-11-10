<?php

namespace App\Http\Controllers\Trabajadores;

use App\Http\Controllers\Controller;
use App\Models\RentaOtroEmpleador;
use App\Models\Trabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Certificados de rentas y retenciones de otros empleadores del año (quinta categoría):
 * se suman a la proyección y a lo ya retenido del trabajador. Auditado.
 */
class OtroEmpleadorController extends Controller
{
    public function store(Request $request, Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        $datos = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'ruc_empleador' => ['nullable', 'digits:11'],
            'empleador' => ['nullable', 'string', 'max:255'],
            'renta' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'retencion' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999', 'lte:renta'],
        ], [
            'retencion.lte' => 'La retención no puede ser mayor que la renta.',
        ], [
            'anio' => 'año', 'ruc_empleador' => 'RUC del empleador', 'empleador' => 'empleador',
            'renta' => 'renta percibida', 'retencion' => 'retención',
        ]);

        $trabajador->rentasOtrosEmpleadores()->create($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Renta de otro empleador registrada para {$datos['anio']}. Recalcula los periodos abiertos de ese año."]);

        return back();
    }

    public function destroy(Trabajador $trabajador, RentaOtroEmpleador $renta): RedirectResponse
    {
        Gate::authorize('update', $trabajador);
        abort_unless($renta->trabajador_id === $trabajador->id, 404);

        $renta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se quitó la renta de otro empleador. Recalcula los periodos abiertos de ese año.']);

        return back();
    }
}
