<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Services\Empresas\EmpresaActiva;
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

    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
