<?php

namespace App\Http\Controllers\Parametros;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametros\NuevaVigenciaRequest;
use App\Models\Parametro;
use App\Services\Parametros\VigenciasParametros;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Parámetros legales con vigencia. Sin edición: un cambio es una vigencia nueva. */
class ParametroController extends Controller
{
    public function __construct(private readonly VigenciasParametros $vigencias) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Parametro::class);

        return Inertia::render('parametros/Index', [
            'claves' => $this->vigencias->resumen(CarbonImmutable::today()),
            'hoy' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(NuevaVigenciaRequest $request): RedirectResponse
    {
        $parametro = $this->vigencias->registrar($request->datos());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$parametro->clave->etiqueta()}: {$parametro->clave->formatear($parametro->valor)} desde el "
                .$parametro->vigente_desde->format('d/m/Y').'.',
        ]);

        return to_route('parametros.index');
    }

    public function destroy(Parametro $parametro): RedirectResponse
    {
        Gate::authorize('delete', $parametro);

        try {
            $this->vigencias->anular($parametro);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Vigencia anulada.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
