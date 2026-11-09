<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Periodos\GuardarLineaRequest;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Planilla\DetalleLinea;
use App\Services\Planilla\NovedadesPlanilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Una línea (trabajador) del periodo: guardado por fila de la grilla y detalle del resultado. */
class LineaPlanillaController extends Controller
{
    public function show(Request $request, Periodo $periodo, PlanillaLinea $linea, DetalleLinea $detalle): Response
    {
        Gate::authorize('view', $periodo);

        /** @var User $usuario */
        $usuario = $request->user();

        // Orden de la grilla (alfabético) para navegar entre trabajadores.
        $orden = $periodo->lineas()
            ->with('trabajador:id,apellido_paterno,apellido_materno,nombres')
            ->get(['id', 'trabajador_id'])
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)
            ->pluck('id')
            ->values();
        $posicion = (int) $orden->search($linea->id);

        return Inertia::render('periodos/Linea', [
            'periodo' => [
                'id' => $periodo->id,
                'etiqueta' => $periodo->etiqueta(),
                'estado' => $periodo->estado->value,
                'estado_etiqueta' => $periodo->estado->etiqueta(),
                'puede_editar' => $usuario->can('update', $periodo),
                'empresa' => $periodo->empresa?->razon_social,
                'regimen' => $periodo->empresa?->regimen_laboral->etiqueta(),
            ],
            ...$detalle->datos($periodo, $linea),
            'anterior' => $posicion > 0 ? $orden[$posicion - 1] : null,
            'siguiente' => $orden[$posicion + 1] ?? null,
            'posicion' => $posicion + 1,
            'total' => $orden->count(),
        ]);
    }

    public function update(GuardarLineaRequest $request, Periodo $periodo, PlanillaLinea $linea, NovedadesPlanilla $novedades): JsonResponse
    {
        $linea = $novedades->guardarLinea($periodo, $linea, $request->datos());

        return response()->json([
            ...$novedades->fila($linea, $periodo),
            'estado_periodo' => $periodo->refresh()->estado->value,
        ]);
    }
}
