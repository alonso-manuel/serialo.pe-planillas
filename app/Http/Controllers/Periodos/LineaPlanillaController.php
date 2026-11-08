<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Periodos\GuardarLineaRequest;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Services\Planilla\NovedadesPlanilla;
use Illuminate\Http\JsonResponse;

/** Guardado por fila de la grilla de novedades (responde JSON; la grilla se queda en pantalla). */
class LineaPlanillaController extends Controller
{
    public function update(GuardarLineaRequest $request, Periodo $periodo, PlanillaLinea $linea, NovedadesPlanilla $novedades): JsonResponse
    {
        $linea = $novedades->guardarLinea($periodo, $linea, $request->datos());

        return response()->json([
            ...$novedades->fila($linea, $periodo),
            'estado_periodo' => $periodo->refresh()->estado->value,
        ]);
    }
}
