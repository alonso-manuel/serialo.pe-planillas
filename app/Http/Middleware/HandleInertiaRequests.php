<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Licencia\LicenciaActual;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly EmpresaActiva $empresaActiva,
        private readonly LicenciaActual $licenciaActual,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'esAdmin' => $user?->esAdmin() ?? false,
            ],
            'empresaActiva' => fn () => $user === null ? null : $this->resumen($this->empresaActiva->obtener($user)),
            'empresas' => fn () => $user === null ? [] : $this->empresaActiva->seleccionables($user)
                ->get(['id', 'ruc', 'razon_social'])
                ->map(fn (Empresa $empresa) => $this->resumen($empresa))
                ->all(),
            'avisoLicencia' => fn () => $user === null ? null : $this->avisoLicencia($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Para la franja de aviso: solo lectura o días de prueba.
     *
     * @return array{tipo: string, solo_lectura: bool, mensaje: string, dias_prueba: int|null}|null
     */
    private function avisoLicencia(Request $request): ?array
    {
        $estado = $this->licenciaActual->estado($request->getHost());

        if (! $estado->soloLectura() && $estado->diasPrueba === null) {
            return null;
        }

        return [
            'tipo' => $estado->tipo->value,
            'solo_lectura' => $estado->soloLectura(),
            'mensaje' => $estado->mensaje,
            'dias_prueba' => $estado->diasPrueba,
        ];
    }

    /**
     * @return array{id: int, ruc: string, razon_social: string}|null
     */
    private function resumen(?Empresa $empresa): ?array
    {
        return $empresa === null ? null : [
            'id' => $empresa->id,
            'ruc' => $empresa->ruc,
            'razon_social' => $empresa->razon_social,
        ];
    }
}
