<?php

namespace App\Http\Controllers\Trabajadores;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\CertificadoQuinta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Certificado de rentas y retenciones de quinta categoría: de un trabajador o de todos (zip) por ejercicio. */
class CertificadoQuintaController extends Controller
{
    public function __construct(
        private readonly CertificadoQuinta $certificados,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function show(Trabajador $trabajador, int $anio): Response
    {
        Gate::authorize('view', $trabajador);
        abort_unless($anio >= 2000 && $anio <= (int) now()->year, 404);

        return response($this->certificados->pdf($trabajador, $anio), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->certificados->nombre($trabajador, $anio).'"',
        ]);
    }

    public function lote(Request $request, int $anio): BinaryFileResponse|RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        /** @var Empresa $empresa */
        $empresa = $this->empresaActiva->obtener($usuario) ?? abort(409);
        abort_unless($anio >= 2000 && $anio <= (int) now()->year, 404);

        try {
            $ruta = $this->certificados->zip($empresa, $anio);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        return response()->download($ruta, "Certificados quinta {$anio} {$empresa->ruc}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
