<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoEstudio;
use App\Enums\TipoEstudio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarCuentaRequest;
use App\Models\Central\Estudio;
use App\Services\Central\GestionEstudios;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Cuentas (estudios) del modo SaaS: listado, alta con invitación, suspensión, acceso y eliminación. */
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
            'vence_demo' => CarbonImmutable::today()->addDays(15)->toDateString(),
        ]);
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
