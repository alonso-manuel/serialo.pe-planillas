<?php

namespace App\Http\Controllers\Licencia;

use App\Domain\Licencia\ClaveLicenciaInvalida;
use App\Domain\Licencia\Recurso;
use App\Http\Controllers\Controller;
use App\Models\PermisoSoporte;
use App\Models\User;
use App\Services\Licencia\LicenciaActual;
use App\Support\ModoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Administración → Licencia: plan contratado, uso frente a los límites y registro de una clave nueva (solo admin). */
class LicenciaController extends Controller
{
    public function __construct(private readonly LicenciaActual $licencia) {}

    public function show(Request $request): Response
    {
        $this->soloAdmin($request);

        $estado = $this->licencia->estado($request->getHost());
        $licencia = $estado->licencia;
        $uso = $this->licencia->uso();

        return Inertia::render('licencia/Index', [
            'estado' => [
                'tipo' => $estado->tipo->value,
                'etiqueta' => $estado->tipo->etiqueta(),
                'mensaje' => $estado->mensaje,
                'solo_lectura' => $estado->soloLectura(),
                'dias_prueba' => $estado->diasPrueba,
            ],
            'licencia' => $licencia === null || ModoSistema::saas() ? null : [
                'cliente' => $licencia->cliente,
                'plan' => $licencia->plan,
                'dominio' => $licencia->dominio,
                'emitida' => CarbonImmutable::parse($licencia->emitida)->format('d/m/Y'),
                'actualizaciones_hasta' => CarbonImmutable::parse($licencia->actualizacionesHasta)->format('d/m/Y'),
                'con_actualizaciones' => $licencia->conActualizaciones(CarbonImmutable::today()->toDateTimeImmutable()),
            ],
            'uso' => array_map(fn (Recurso $r) => [
                'recurso' => $r->value,
                'etiqueta' => $r->etiqueta(),
                'usados' => $uso[$r->value],
                'limite' => $estado->limite($r),
            ], Recurso::cases()),
            'dominio_actual' => $request->getHost(),
            // Modo SaaS: el plan lo administra Serialo.pe desde el panel central (no hay clave que registrar).
            'saas' => ModoSistema::saas(),
            'soporte' => ModoSistema::saas() ? $this->soporte() : null,
        ]);
    }

    /** @return array{vigente: bool, vence: string|null, autorizado_por: string|null} */
    private function soporte(): array
    {
        $permiso = PermisoSoporte::vigente();

        return [
            'vigente' => $permiso !== null,
            'vence' => $permiso?->vence_en->format('d/m/Y H:i'),
            'autorizado_por' => $permiso?->autorizador?->name,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $this->soloAdmin($request);
        abort_if(ModoSistema::saas(), 404);

        $datos = $request->validate(['clave' => ['required', 'string', 'max:4000']], [], ['clave' => 'clave de licencia']);

        try {
            $licencia = $this->licencia->guardar($datos['clave'], $request->getHost());
        } catch (ClaveLicenciaInvalida $e) {
            throw ValidationException::withMessages(['clave' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Licencia {$licencia->plan} registrada para {$licencia->cliente}."]);

        return to_route('licencia.show');
    }

    private function soloAdmin(Request $request): void
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($usuario->esAdmin(), 403);
    }
}
