<?php

namespace App\Http\Controllers\Empresas;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Http\Controllers\Controller;
use App\Http\Requests\Empresas\GuardarEmpresaRequest;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Services\Empresas\GestionEmpresas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function __construct(private readonly GestionEmpresas $gestion) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Empresa::class);

        $buscar = trim($request->string('buscar')->toString());
        $regimen = RegimenLaboral::tryFrom($request->string('regimen')->toString());
        $estado = in_array($request->query('estado'), ['activas', 'inactivas'], true) ? $request->query('estado') : null;

        $empresas = Empresa::query()
            ->withCount(['trabajadores', 'establecimientos'])
            ->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('ruc', 'like', "{$buscar}%")
                ->orWhere('razon_social', 'like', "%{$buscar}%")
                ->orWhere('nombre_comercial', 'like', "%{$buscar}%")))
            ->when($regimen !== null, fn (Builder $q) => $q->where('regimen_laboral', $regimen))
            ->when($estado !== null, fn (Builder $q) => $q->where('activo', $estado === 'activas'))
            ->orderBy('razon_social')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Empresa $empresa) => [
                'id' => $empresa->id,
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
                'regimen_laboral' => $empresa->regimen_laboral->value,
                'regimen_etiqueta' => $empresa->regimen_laboral->etiqueta(),
                'seguro_salud' => $empresa->seguro_salud->value,
                'activo' => $empresa->activo,
                'trabajadores_count' => $empresa->trabajadores_count,
                'establecimientos_count' => $empresa->establecimientos_count,
            ]);

        return Inertia::render('empresas/Index', [
            'empresas' => $empresas,
            'filtros' => [
                'buscar' => $buscar,
                'regimen' => $regimen?->value,
                'estado' => $estado,
            ],
            'regimenes' => $this->regimenes(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Empresa::class);

        return Inertia::render('empresas/Form', [
            'empresa' => null,
            'establecimientos' => [],
            ...$this->catalogos(),
        ]);
    }

    public function store(GuardarEmpresaRequest $request): RedirectResponse
    {
        $empresa = $this->gestion->crear($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Empresa {$empresa->razon_social} registrada. Revisa sus establecimientos.",
        ]);

        return to_route('empresas.edit', $empresa);
    }

    public function edit(Empresa $empresa): Response
    {
        Gate::authorize('update', $empresa);

        return Inertia::render('empresas/Form', [
            'empresa' => [
                'id' => $empresa->id,
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
                'direccion' => $empresa->direccion,
                'ubigeo' => $empresa->ubigeo,
                'regimen_laboral' => $empresa->regimen_laboral->value,
                'seguro_salud' => $empresa->seguro_salud->value,
                'representante_legal' => $empresa->representante_legal,
                'activo' => $empresa->activo,
                // Versión en la URL para que el navegador no muestre un logo anterior en caché.
                'logo' => $empresa->logo_path !== null ? md5($empresa->logo_path) : null,
            ],
            'establecimientos' => $empresa->establecimientos()
                ->withCount('trabajadores')
                ->orderBy('codigo')
                ->get()
                ->map(fn (Establecimiento $e) => [
                    'id' => $e->id,
                    'codigo' => $e->codigo,
                    'nombre' => $e->nombre,
                    'direccion' => $e->direccion,
                    'es_principal' => $e->esPrincipal(),
                    'trabajadores_count' => $e->trabajadores_count,
                ]),
            ...$this->catalogos(),
        ]);
    }

    public function update(GuardarEmpresaRequest $request, Empresa $empresa): RedirectResponse
    {
        $this->gestion->actualizar($empresa, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Empresa {$empresa->razon_social} actualizada."]);

        return to_route('empresas.index');
    }

    /**
     * @return array{regimenes: list<array{value: string, label: string}>, seguros: list<array{value: string, label: string}>}
     */
    private function catalogos(): array
    {
        return [
            'regimenes' => $this->regimenes(),
            'seguros' => array_map(
                fn (SeguroSalud $s) => ['value' => $s->value, 'label' => $s->etiqueta()],
                SeguroSalud::cases(),
            ),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function regimenes(): array
    {
        return array_map(
            fn (RegimenLaboral $r) => ['value' => $r->value, 'label' => $r->etiqueta()],
            RegimenLaboral::cases(),
        );
    }
}
