<?php

namespace App\Http\Controllers\Beneficios;

use App\Domain\Planilla\CalculadoraVacaciones;
use App\Domain\Planilla\PeriodoVacacional;
use App\Enums\EstadoPeriodo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\VacacionGoce;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\VacacionesTrabajador;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Vacaciones de los trabajadores de la empresa activa: periodos, saldos y goces. */
class VacacionesController extends Controller
{
    public function __construct(
        private readonly VacacionesTrabajador $vacaciones,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        /** @var Empresa $empresa */
        $empresa = $this->empresaActiva->obtener($usuario) ?? abort(409);
        $hoy = CarbonImmutable::today();

        $cerrados = Periodo::query()->where('empresa_id', $empresa->id)->where('estado', EstadoPeriodo::Cerrado)
            ->get(['anio', 'mes'])->map(fn (Periodo $p) => "{$p->anio}-{$p->mes}")->flip();

        $trabajadores = Trabajador::query()->deEmpresa($empresa->id)
            ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>=', $hoy->subYear()))
            ->with(['empresa'])
            ->get()
            ->sortBy('nombre_completo')
            ->values();

        $goces = VacacionGoce::query()->whereIn('trabajador_id', $trabajadores->pluck('id'))->orderByDesc('fecha_inicio')->get()->groupBy('trabajador_id');

        return Inertia::render('vacaciones/Index', [
            'empresa' => $empresa->razon_social,
            'trabajadores' => $trabajadores->map(function (Trabajador $t) use ($goces, $cerrados, $hoy) {
                $periodos = $this->vacaciones->periodos($t);
                $pendientes = array_filter($periodos, fn (PeriodoVacacional $p) => $p->cumplido && $p->saldo > 0);
                $enCurso = collect($periodos)->first(fn (PeriodoVacacional $p) => ! $p->cumplido);

                return [
                    'id' => $t->id,
                    'nombre' => $t->nombre_completo,
                    'documento' => "{$t->tipo_documento->abreviatura()} {$t->numero_documento}",
                    'ingreso' => $t->fecha_ingreso->format('d/m/Y'),
                    'cesado' => $t->fecha_cese !== null && $t->fecha_cese->lte($hoy),
                    'saldo' => array_sum(array_map(fn (PeriodoVacacional $p) => $p->saldo, $pendientes)),
                    'vencidos' => count(array_filter($periodos, fn (PeriodoVacacional $p) => $p->vencido)),
                    'proximo_vencimiento' => ($primero = reset($pendientes)) ? $primero->vence->format('d/m/Y') : null,
                    'cumple_anio' => $enCurso?->hasta->modify('+1 day')->format('d/m/Y'),
                    'aviso_record' => count(array_filter($periodos, fn (PeriodoVacacional $p) => $p->avisoRecord)) > 0,
                    'periodos' => array_map(fn (PeriodoVacacional $p) => [
                        'numero' => $p->numero,
                        'desde' => $p->desde->format('d/m/Y'),
                        'hasta' => $p->hasta->format('d/m/Y'),
                        'cumplido' => $p->cumplido,
                        'derecho' => $p->derecho,
                        'gozados' => $p->gozados,
                        'saldo' => $p->saldo,
                        'vence' => $p->vence->format('d/m/Y'),
                        'vencido' => $p->vencido,
                        'dias_no_efectivos' => $p->diasNoEfectivos,
                        'aviso_record' => $p->avisoRecord,
                    ], array_reverse($periodos)),
                    'goces' => ($goces->get($t->id) ?? collect())->map(fn (VacacionGoce $g) => [
                        'id' => $g->id,
                        'inicio' => $g->fecha_inicio->format('d/m/Y'),
                        'fin' => $g->fecha_fin->format('d/m/Y'),
                        'dias' => $g->dias,
                        'observacion' => $g->observacion,
                        // Se puede borrar si ninguno de sus meses tiene la planilla cerrada.
                        'puede_eliminar' => collect(CalculadoraVacaciones::reparto($g->fecha_inicio->toDateTimeImmutable(), $g->dias))
                            ->every(fn (array $m) => ! $cerrados->has("{$m[0]}-{$m[1]}")),
                    ])->values()->all(),
                ];
            })->all(),
        ]);
    }

    public function store(Request $request, Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'dias' => ['required', 'integer', 'between:1,60'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [], ['fecha_inicio' => 'fecha de inicio', 'dias' => 'días']);

        $goce = $this->vacaciones->registrar(
            $trabajador,
            CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha_inicio'])->startOfDay(),
            (int) $datos['dias'],
            $datos['observacion'] ?? null,
            $usuario,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Vacaciones registradas: {$goce->dias} días, del {$goce->fecha_inicio->format('d/m/Y')} al {$goce->fecha_fin->format('d/m/Y')}."]);

        return to_route('vacaciones.index');
    }

    public function destroy(VacacionGoce $goce): RedirectResponse
    {
        Gate::authorize('update', $goce->trabajador);

        $this->vacaciones->eliminar($goce);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vacaciones eliminadas.']);

        return to_route('vacaciones.index');
    }
}
