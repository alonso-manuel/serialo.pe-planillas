<?php

namespace App\Http\Controllers\Trabajadores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trabajadores\RegistrarCambioRequest;
use App\Models\Trabajador;
use App\Models\TrabajadorHistorial;
use App\Models\User;
use App\Services\Trabajadores\HistorialTrabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Cambios con vigencia (sueldo, cargo, pensión...) de un trabajador. */
class CambioTrabajadorController extends Controller
{
    public function __construct(private readonly HistorialTrabajador $historial) {}

    public function store(RegistrarCambioRequest $request, Trabajador $trabajador): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $grupo = $request->string('grupo')->toString();
        $mes = $request->string('vigente_desde')->toString();

        $this->historial->registrar($trabajador, $grupo, $mes, $request->nuevos(), $usuario);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => HistorialTrabajador::GRUPOS[$grupo][0].' actualizado desde '
                .substr($mes, 5, 2).'/'.substr($mes, 0, 4).'. Recalcula los periodos abiertos desde ese mes.',
        ]);

        return back();
    }

    public function destroy(Trabajador $trabajador, TrabajadorHistorial $historial): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        try {
            $this->historial->anular($historial);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambio anulado; se restauró el valor anterior.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
