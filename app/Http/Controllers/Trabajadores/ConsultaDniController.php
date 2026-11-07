<?php

namespace App\Http\Controllers\Trabajadores;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Dni\ConsultaDni;
use App\Services\Dni\ConsultaDniNoDisponible;
use App\Services\Dni\DniNoEncontrado;
use App\Services\Empresas\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Autocompletar apellidos y nombres del trabajador por DNI. Responde JSON. */
class ConsultaDniController extends Controller
{
    public function show(Request $request, string $dni, ConsultaDni $consulta, EmpresaActiva $empresaActiva): JsonResponse
    {
        Gate::authorize('create', Trabajador::class);

        if (! TipoDocumento::Dni->esNumeroValido($dni)) {
            return response()->json(['message' => TipoDocumento::Dni->mensajeFormato()], 422);
        }

        /** @var User $usuario */
        $usuario = $request->user();
        $empresa = $empresaActiva->obtener($usuario);

        // Si ya está registrado, no se gasta una consulta del cupo mensual.
        if ($empresa !== null && Trabajador::withTrashed()
            ->deEmpresa($empresa->id)
            ->where('tipo_documento', TipoDocumento::Dni)
            ->where('numero_documento', $dni)
            ->exists()) {
            return response()->json(['message' => 'Ya existe un trabajador con ese DNI en esta empresa.'], 409);
        }

        try {
            return response()->json($consulta->consultar($dni)->toArray());
        } catch (DniNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConsultaDniNoDisponible $e) {
            if ($e->getPrevious() !== null) {
                report($e->getPrevious());
            }

            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
