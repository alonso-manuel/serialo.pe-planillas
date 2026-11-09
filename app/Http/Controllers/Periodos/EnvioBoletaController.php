<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Planilla\EnvioBoletas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Envío de boletas por correo (en cola), de todo el periodo o reenvío a un trabajador. */
class EnvioBoletaController extends Controller
{
    public function __construct(private readonly EnvioBoletas $envios) {}

    public function store(Request $request, Periodo $periodo): RedirectResponse
    {
        Gate::authorize('view', $periodo);

        return $this->enviar($request, $periodo, null);
    }

    public function reenviar(Request $request, Periodo $periodo, PlanillaLinea $linea): RedirectResponse
    {
        Gate::authorize('view', $periodo);

        return $this->enviar($request, $periodo, $linea);
    }

    private function enviar(Request $request, Periodo $periodo, ?PlanillaLinea $linea): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        try {
            $r = $this->envios->enviar($periodo, $usuario, $linea);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $mensaje = match (true) {
            $r['en_cola'] === 0 && $r['sin_correo'] === [] => 'Todas las boletas ya fueron enviadas.',
            $r['en_cola'] === 1 => 'Se puso en cola 1 boleta para enviar por correo.',
            default => "Se pusieron en cola {$r['en_cola']} boletas para enviar por correo.",
        };
        if ($r['sin_correo'] !== []) {
            $mensaje .= ' Sin correo en su ficha: '.implode(', ', $r['sin_correo']).'.';
        }

        Inertia::flash('toast', ['type' => $r['sin_correo'] === [] ? 'success' : 'warning', 'message' => $mensaje]);

        return back();
    }
}
