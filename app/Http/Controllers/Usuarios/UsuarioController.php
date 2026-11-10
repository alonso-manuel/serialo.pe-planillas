<?php

namespace App\Http\Controllers\Usuarios;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\GuardarUsuarioRequest;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Usuarios\GestionUsuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function __construct(private readonly GestionUsuarios $gestion) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $buscar = trim($request->string('buscar')->toString());
        $rol = RolUsuario::tryFrom($request->string('rol')->toString());
        $estado = in_array($request->query('estado'), ['activos', 'inactivos'], true) ? $request->query('estado') : null;

        $usuarios = User::query()
            ->where('es_soporte', false)
            ->withCount('empresas')
            ->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$buscar}%")
                ->orWhere('email', 'like', "%{$buscar}%")))
            ->when($rol !== null, fn (Builder $q) => $q->where('rol', $rol))
            ->when($estado !== null, fn (Builder $q) => $q->where('activo', $estado === 'activos'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $usuario) => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol->value,
                'rol_etiqueta' => $usuario->rol->etiqueta(),
                'activo' => $usuario->activo,
                'empresas_count' => $usuario->empresas_count,
                'es_actual' => $usuario->is($request->user()),
            ]);

        return Inertia::render('usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros' => [
                'buscar' => $buscar,
                'rol' => $rol?->value,
                'estado' => $estado,
            ],
            'roles' => $this->roles(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('usuarios/Form', [
            'usuario' => null,
            'empresas' => $this->empresasActivas(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, rol: string, password: string, empresas?: array<int, int>} $datos */
        $datos = $request->validated();
        $usuario = $this->gestion->crear($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$usuario->name} creado."]);

        return to_route('usuarios.index');
    }

    public function edit(Request $request, User $usuario): Response
    {
        Gate::authorize('update', $usuario);

        return Inertia::render('usuarios/Form', [
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol->value,
                'activo' => $usuario->activo,
                'empresas' => $usuario->empresas()->orderBy('empresas.id')->pluck('empresas.id')->all(),
                'es_actual' => $usuario->is($request->user()),
            ],
            'empresas' => $this->empresasActivas(),
            'roles' => $this->roles(),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario): RedirectResponse
    {
        /** @var array{name: string, email: string, rol: string, password?: string|null, empresas?: array<int, int>} $datos */
        $datos = $request->validated();
        /** @var User $actor */
        $actor = $request->user();

        $this->gestion->actualizar($usuario, $datos, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$usuario->name} actualizado."]);

        return to_route('usuarios.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roles(): array
    {
        return array_map(
            fn (RolUsuario $rol) => ['value' => $rol->value, 'label' => $rol->etiqueta()],
            RolUsuario::cases(),
        );
    }

    /**
     * @return array<int, array{id: int, ruc: string, razon_social: string}>
     */
    private function empresasActivas(): array
    {
        return Empresa::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social'])
            ->map(fn (Empresa $e) => ['id' => $e->id, 'ruc' => $e->ruc, 'razon_social' => $e->razon_social])
            ->all();
    }
}
