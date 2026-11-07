<?php

namespace App\Http\Controllers\Empresas;

use App\Domain\Sunat\Ruc;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Rules\RucValido;
use App\Services\Ruc\ConsultaRuc;
use App\Services\Ruc\ConsultaRucNoDisponible;
use App\Services\Ruc\RucNoEncontrado;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Autocompletar el formulario de empresa con los datos de SUNAT. Responde JSON. */
class ConsultaRucController extends Controller
{
    public function show(string $ruc, ConsultaRuc $consulta): JsonResponse
    {
        Gate::authorize('create', Empresa::class);

        if (! Ruc::esValido($ruc)) {
            return response()->json(['message' => RucValido::MENSAJE], 422);
        }

        if (Empresa::withTrashed()->where('ruc', $ruc)->exists()) {
            return response()->json(['message' => 'Esta empresa ya está registrada en el sistema.'], 409);
        }

        try {
            return response()->json($consulta->consultar($ruc)->toArray());
        } catch (RucNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConsultaRucNoDisponible $e) {
            if ($e->getPrevious() !== null) {
                report($e->getPrevious());
            }

            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
