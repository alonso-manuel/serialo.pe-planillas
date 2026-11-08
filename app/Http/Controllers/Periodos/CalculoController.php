<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use App\Services\Planilla\CalculoMasivo;
use App\Services\Planilla\ProblemasDeCalculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Cálculo de planilla: un periodo (inmediato) o todas las empresas (en cola, con progreso). */
class CalculoController extends Controller
{
    public function periodo(Periodo $periodo, CalcularPlanillaPeriodo $calculo): RedirectResponse
    {
        Gate::authorize('update', $periodo);

        try {
            $resumen = $calculo->ejecutar($periodo);
        } catch (ProblemasDeCalculo $e) {
            return to_route('periodos.show', $periodo)->with('problemas_calculo', $e->problemas);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Planilla de {$periodo->etiqueta()} calculada: {$resumen['trabajadores']} trabajadores, neto S/ "
                .$this->formatoSoles($resumen['neto']).'.',
        ]);

        // Vuelve a donde se pidió (periodo o detalle de un trabajador).
        return back(fallback: route('periodos.show', $periodo));
    }

    public function iniciar(Request $request, CalculoMasivo $masivo): RedirectResponse
    {
        $datos = $request->validate(
            ['anio' => ['required', 'integer', 'between:2000,2100'], 'mes' => ['required', 'integer', 'between:1,12']],
            ['mes.between' => 'Elige un mes válido.'],
            ['anio' => 'año', 'mes' => 'mes'],
        );

        /** @var User $usuario */
        $usuario = $request->user();
        $id = $masivo->iniciar($usuario, (int) $datos['anio'], (int) $datos['mes']);

        return to_route('periodos.index', ['calculo' => $id]);
    }

    public function estado(Request $request, string $calculo, CalculoMasivo $masivo): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return response()->json($masivo->estado($calculo, $usuario) ?? abort(404));
    }

    /** "1305.5" → "1,305.50" (sin float). */
    private function formatoSoles(string $monto): string
    {
        [$entero, $decimales] = array_pad(explode('.', $monto), 2, '00');

        return number_format((int) $entero).'.'.str_pad(substr($decimales, 0, 2), 2, '0');
    }
}
