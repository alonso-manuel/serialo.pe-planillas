<?php

namespace App\Http\Controllers\Respaldos;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\Respaldo\Respaldos;
use App\Support\ModoSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Administración → Respaldos: copias diarias de la base, crear una ahora y descargarlas (solo admin). */
class RespaldoController extends Controller
{
    public function __construct(private readonly Respaldos $respaldos) {}

    public function index(Request $request): Response
    {
        $this->soloAdmin($request);

        return Inertia::render('respaldos/Index', [
            'respaldos' => array_map(fn (array $r) => [
                'archivo' => $r['archivo'],
                'tamano' => Respaldos::tamano($r['tamano']),
                'fecha' => $r['fecha']->format('d/m/Y H:i'),
            ], $this->respaldos->listar()),
            'dias' => max(1, (int) config('planillas.respaldos.dias', 14)),
            'hora' => (string) config('planillas.respaldos.hora', '03:00'),
            'externo' => ! ModoSistema::saas() && filled(config('planillas.respaldos.disco_externo')),
            'saas' => ModoSistema::saas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->soloAdmin($request);

        $r = $this->respaldos->crear();
        $this->auditar('creado', $r['archivo']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Respaldo creado: {$r['archivo']} (".Respaldos::tamano($r['tamano']).').']);

        return to_route('respaldos.index');
    }

    public function descargar(Request $request, string $archivo): BinaryFileResponse
    {
        $this->soloAdmin($request);

        $ruta = $this->respaldos->ruta($archivo);
        abort_if($ruta === null, 404);

        $this->auditar('descargado', $archivo);

        return response()->download($ruta, $archivo, ['Content-Type' => 'application/gzip']);
    }

    /** La base completa contiene datos personales de todos los trabajadores: queda registro de quién la creó o descargó. */
    private function auditar(string $accion, string $archivo): void
    {
        Auditoria::create([
            'user_id' => Auth::guard('web')->id(),
            'empresa_id' => null,
            'auditable_type' => 'respaldo',
            'auditable_id' => 0,
            'descripcion' => "Respaldo {$archivo}",
            'accion' => $accion,
            'cambios' => null,
            'ip' => $this->ip(),
        ]);
    }

    private function ip(): ?string
    {
        return app()->bound('request') ? request()->ip() : null;
    }

    private function soloAdmin(Request $request): void
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($usuario->esAdmin(), 403);
    }
}
