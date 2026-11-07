<?php

namespace App\Http\Controllers\TasasAfp;

use App\Http\Controllers\Controller;
use App\Models\TasaAfp;
use App\Services\TasasAfp\ImportadorTasasAfp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Importación de tasas AFP desde Excel/CSV: subir → vista previa → confirmar. */
class ImportacionTasasAfpController extends Controller
{
    public function __construct(private readonly ImportadorTasasAfp $importador) {}

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TasaAfp::class);

        $request->validate(
            ['archivo' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:2048']],
            ['archivo.extensions' => 'Sube la plantilla en formato .xlsx o .csv.'],
        );

        $archivo = $request->file('archivo');
        $extension = mb_strtolower($archivo->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        $resumen = $this->importador->previsualizar(
            (string) $archivo->getRealPath(),
            $extension,
            $archivo->getClientOriginalName(),
        );

        return to_route('tasas-afp.index', $resumen['primera'] ?? []);
    }

    public function confirmar(): RedirectResponse
    {
        Gate::authorize('create', TasaAfp::class);

        $resultado = $this->importador->confirmar();

        Inertia::flash('toast', $resultado['guardadas'] > 0
            ? ['type' => 'success', 'message' => "Se importaron {$resultado['guardadas']} filas de tasas AFP."]
            : ['type' => 'warning', 'message' => 'No había filas válidas para importar.']);

        return to_route('tasas-afp.index', $resultado['primera'] ?? []);
    }

    public function cancelar(): RedirectResponse
    {
        Gate::authorize('create', TasaAfp::class);

        $this->importador->cancelar();

        return back();
    }
}
