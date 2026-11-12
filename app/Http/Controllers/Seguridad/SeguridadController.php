<?php

namespace App\Http\Controllers\Seguridad;

use App\Enums\EstadoPeriodo;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\FirmaDigital;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Respaldo\Respaldos;
use App\Support\ModoSistema;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Seguridad y cumplimiento (solo admin): cómo protege el sistema la información, con el estado real
 * de esta instalación (cierres, auditoría, firma digital, respaldos y doble factor).
 */
class SeguridadController extends Controller
{
    /** Días antes del vencimiento en que la firma digital se marca «por vencer» (como en la ficha de la empresa). */
    private const DIAS_AVISO_FIRMA = 30;

    public function __invoke(Request $request, Respaldos $respaldos): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($usuario->esAdmin(), 403);

        $hoy = now()->toImmutable();
        $ultimoCierre = Periodo::query()->where('estado', EstadoPeriodo::Cerrado)->max('cerrado_en');
        $lista = $respaldos->listar();
        $usuarios = User::query()->where('activo', true)->where('es_soporte', false)->orderBy('name')->get(['name', 'two_factor_confirmed_at']);
        $firmas = FirmaDigital::query()->get()->keyBy('empresa_id');

        return Inertia::render('seguridad/Index', [
            'cierres' => [
                'cerrados' => Periodo::query()->where('estado', EstadoPeriodo::Cerrado)->count(),
                'ultimo' => $ultimoCierre === null ? null : $hoy->parse((string) $ultimoCierre)->format('d/m/Y H:i'),
                'reaperturas' => Auditoria::query()->where('accion', 'reabierto')->where('created_at', '>=', $hoy->subDays(90))->count(),
            ],
            'auditoria' => [
                'total' => Auditoria::query()->count(),
                'ultimos_30' => Auditoria::query()->where('created_at', '>=', $hoy->subDays(30))->count(),
            ],
            'firmas' => Empresa::query()->where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social'])
                ->map(function (Empresa $e) use ($firmas, $hoy) {
                    /** @var FirmaDigital|null $f */
                    $f = $firmas->get($e->id);

                    return [
                        'empresa' => $e->razon_social,
                        'estado' => match (true) {
                            $f === null => 'sin_certificado',
                            ! $f->valido_hasta->isFuture() => 'vencida',
                            $f->valido_hasta->lte($hoy->addDays(self::DIAS_AVISO_FIRMA)) => 'por_vencer',
                            default => 'vigente',
                        },
                        'titular' => $f?->titular,
                        'vence' => $f?->valido_hasta->format('d/m/Y'),
                    ];
                })->values()->all(),
            'respaldos' => [
                'cantidad' => count($lista),
                'ultimo' => ($lista[0] ?? null) === null ? null : $lista[0]['fecha']->format('d/m/Y H:i'),
                'dias' => max(1, (int) config('planillas.respaldos.dias', 14)),
                'hora' => (string) config('planillas.respaldos.hora', '03:00'),
                'externo' => ! ModoSistema::saas() && filled(config('planillas.respaldos.disco_externo')),
                'saas' => ModoSistema::saas(),
            ],
            'accesos' => [
                'activos' => $usuarios->count(),
                'sin_doble_factor' => $usuarios->whereNull('two_factor_confirmed_at')->pluck('name')->values()->all(),
            ],
        ]);
    }
}
