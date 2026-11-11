<?php

namespace App\Http\Controllers\Trabajadores;

use App\Http\Controllers\Controller;
use App\Models\Prestamo;
use App\Models\Trabajador;
use App\Services\Planilla\PrestamosTrabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Préstamos al trabajador en cuotas: se descuentan solas en la planilla de cada mes (0706). */
class PrestamoController extends Controller
{
    public function __construct(private readonly PrestamosTrabajador $prestamos) {}

    public function store(Request $request, Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'monto' => ['required', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'numero_cuotas' => ['required', 'integer', 'between:1,60'],
            'mes_inicio' => ['required', 'date_format:Y-m'],
            'glosa' => ['nullable', 'string', 'max:150'],
        ], [], ['numero_cuotas' => 'número de cuotas', 'mes_inicio' => 'mes de la primera cuota', 'glosa' => 'detalle']);

        try {
            $p = $this->prestamos->registrar($trabajador, $datos['fecha'], (string) $datos['monto'], (int) $datos['numero_cuotas'], $datos['mes_inicio'], $datos['glosa'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['mes_inicio' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Préstamo registrado en {$p->numero_cuotas} cuotas: se descontarán en la planilla de cada mes."]);

        return back();
    }

    public function destroy(Trabajador $trabajador, Prestamo $prestamo): RedirectResponse
    {
        Gate::authorize('update', $trabajador);
        abort_unless($prestamo->trabajador_id === $trabajador->id, 404);

        try {
            $this->prestamos->eliminar($prestamo);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Préstamo eliminado.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
