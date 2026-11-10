<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoEstudio;
use App\Enums\TipoEstudio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarCuentaRequest;
use App\Http\Requests\Panel\GuardarCuentaRequest;
use App\Models\Central\Estudio;
use App\Services\Central\GestionEstudios;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Cuentas (estudios) del modo SaaS: listado, alta con invitación, edición, exportación, soporte, suspensión, acceso y eliminación. */
class CuentaController extends Controller
{
    public function __construct(private readonly GestionEstudios $gestion) {}

    public function index(): Response
    {
        $hoy = CarbonImmutable::today();

        $cuentas = [];
        foreach (Estudio::query()->orderByDesc('created_at')->get() as $e) {
            if (! $e instanceof Estudio) {
                continue;
            }

            $cuentas[] = [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'url' => $e->url(),
                'tipo' => $e->tipo->value,
                'tipo_etiqueta' => $e->tipo->etiqueta(),
                'plan' => $e->plan,
                'limites' => $this->limites($e->max_empresas, $e->max_usuarios, $e->max_trabajadores),
                'estado' => $e->estado->value,
                'estado_etiqueta' => $e->estado->etiqueta(),
                'correo_admin' => $e->correo_admin,
                'vence_en' => $e->vence_en?->format('d/m/Y'),
                'vencida' => $e->vence_en !== null && $e->vence_en->lt($hoy),
                'creada' => $e->created_at->format('d/m/Y'),
            ];
        }

        return Inertia::render('panel/cuentas/Index', ['cuentas' => $cuentas]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/cuentas/Create', [
            'url_estudio' => (string) config('planillas.url_estudio'),
            'vence_demo' => CarbonImmutable::today()->addDays((int) config('planillas.planes.demo.dias', 15))->toDateString(),
            'planes' => config('planillas.planes'),
        ]);
    }

    public function edit(Estudio $estudio): Response
    {
        return Inertia::render('panel/cuentas/Edit', [
            'cuenta' => [
                'id' => $estudio->id,
                'nombre' => $estudio->nombre,
                'url' => $estudio->url(),
                'correo_admin' => $estudio->correo_admin,
                'tipo' => $estudio->tipo->value,
                'plan' => $estudio->plan,
                'max_empresas' => $estudio->max_empresas,
                'max_usuarios' => $estudio->max_usuarios,
                'max_trabajadores' => $estudio->max_trabajadores,
                'vence_en' => $estudio->vence_en?->toDateString(),
            ],
            'planes' => config('planillas.planes'),
        ]);
    }

    public function update(ActualizarCuentaRequest $request, Estudio $estudio): RedirectResponse
    {
        /** @var array{nombre: string, tipo: string, plan: string, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null, vence_en: string|null} $datos */
        $datos = [...$request->validated(), 'vence_en' => $request->validated('vence_en')];
        $this->gestion->actualizar($estudio, $datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cuenta {$estudio->nombre} actualizada."]);

        return to_route('panel.cuentas.index');
    }

    public function store(GuardarCuentaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $tipo = TipoEstudio::from($datos['tipo']);

        $estudio = $this->gestion->crear(
            subdominio: $datos['subdominio'],
            nombre: $datos['nombre'],
            correo: $datos['correo_admin'],
            nombreAdmin: $datos['nombre_admin'],
            tipo: $tipo,
            venceEn: isset($datos['vence_en']) ? CarbonImmutable::parse($datos['vence_en']) : null,
            plan: empty($datos['plan']) ? null : [
                'nombre' => $datos['plan'],
                'max_empresas' => $datos['max_empresas'] ?? null,
                'max_usuarios' => $datos['max_usuarios'] ?? null,
                'max_trabajadores' => $datos['max_trabajadores'] ?? null,
            ],
        );

        $this->avisarInvitacion($estudio, $this->gestion->invitar($estudio), 'creada');

        return to_route('panel.cuentas.index');
    }

    public function estado(Estudio $estudio): RedirectResponse
    {
        $nuevo = $estudio->suspendido() ? EstadoEstudio::Activo : EstadoEstudio::Suspendido;
        $this->gestion->cambiarEstado($estudio, $nuevo);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $nuevo === EstadoEstudio::Suspendido
                ? "{$estudio->nombre} quedó suspendida: nadie puede ingresar, sus datos se conservan."
                : "{$estudio->nombre} vuelve a estar activa.",
        ]);

        return back();
    }

    /** Descarga la base completa de la cuenta (.sql.gz): para entregarla al cliente o pasarla a su hosting. */
    public function exportar(Estudio $estudio): BinaryFileResponse
    {
        $ruta = $this->gestion->exportar($estudio);

        return response()->download($ruta, basename($ruta), ['Content-Type' => 'application/gzip'])->deleteFileAfterSend();
    }

    /** Entra al estudio como «Soporte Serialo.pe», solo si su administrador lo autorizó. */
    public function soporte(Estudio $estudio): SymfonyResponse
    {
        $enlace = $this->gestion->enlaceSoporte($estudio);

        if ($enlace === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $estudio->suspendido()
                ? "{$estudio->nombre} está suspendida."
                : "{$estudio->nombre} no ha autorizado el acceso de soporte. Pídele al administrador que lo active en Administración → Licencia."]);

            return back();
        }

        return Inertia::location($enlace);
    }

    public function acceso(Estudio $estudio): RedirectResponse
    {
        $this->avisarInvitacion($estudio, $this->gestion->invitar($estudio), 'acceso');

        return back();
    }

    public function destroy(Request $request, Estudio $estudio): RedirectResponse
    {
        $confirmacion = (string) $request->validate(['confirmacion' => ['required', 'string']])['confirmacion'];

        if ($confirmacion !== $estudio->id) {
            throw ValidationException::withMessages(['confirmacion' => "Escribe «{$estudio->id}» para confirmar."]);
        }

        $nombre = $estudio->nombre;
        $this->gestion->eliminar($estudio);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se eliminó la cuenta {$nombre} y su base de datos."]);

        return to_route('panel.cuentas.index');
    }

    /** «40 empresas · 3 usuarios · trabajadores sin límite» */
    private function limites(?int $empresas, ?int $usuarios, ?int $trabajadores): string
    {
        $texto = fn (?int $n, string $uno, string $varios) => $n === null ? "{$varios} sin límite" : $n.' '.($n === 1 ? $uno : $varios);

        return implode(' · ', [$texto($empresas, 'empresa', 'empresas'), $texto($usuarios, 'usuario', 'usuarios'), $texto($trabajadores, 'trabajador', 'trabajadores')]);
    }

    /** Si el correo no salió, el enlace se muestra en el panel para entregarlo a mano. */
    private function avisarInvitacion(Estudio $estudio, ?string $enlace, string $motivo): void
    {
        $inicio = $motivo === 'creada' ? "Cuenta {$estudio->nombre} creada." : 'Enlace de acceso generado.';

        if ($enlace === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$inicio} Se envió el correo a {$estudio->correo_admin}."]);

            return;
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => "{$inicio} El correo no está configurado: entrega el enlace a mano."]);
        Inertia::flash('invitacion', ['cuenta' => $estudio->nombre, 'correo' => $estudio->correo_admin, 'enlace' => $enlace]);
    }
}
