<?php

namespace App\Http\Controllers\Periodos;

use App\Enums\EstadoPeriodo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Planilla\PeriodosPlanilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Periodos de planilla de la empresa activa (rutas con middleware `empresa.activa`). */
class PeriodoController extends Controller
{
    public function __construct(
        private readonly PeriodosPlanilla $periodos,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresa($request);

        $periodos = Periodo::query()
            ->deEmpresa($empresa->id)
            ->withCount('lineas')
            ->withSum('lineas', 'neto_pagar')
            ->withMax('lineas', 'calculado_en')
            ->orderByDesc('anio')->orderByDesc('mes')
            ->get()
            ->map(fn (Periodo $p) => [
                'id' => $p->id,
                'anio' => $p->anio,
                'mes' => $p->mes,
                'etiqueta' => $p->etiqueta(),
                'estado' => $p->estado->value,
                'estado_etiqueta' => $p->estado->etiqueta(),
                'trabajadores' => (int) $p->getAttribute('lineas_count'),
                'neto' => $p->estado === EstadoPeriodo::Abierto ? null : $p->getAttribute('lineas_sum_neto_pagar'),
                'calculado_en' => $p->getAttribute('lineas_max_calculado_en'),
            ]);

        return Inertia::render('periodos/Index', [
            'periodos' => $periodos,
            'siguiente' => $this->periodos->siguiente($empresa),
            'hoy' => ['anio' => (int) now()->year, 'mes' => (int) now()->month],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Periodo::class);

        $datos = $request->validate(
            ['anio' => ['required', 'integer', 'between:2000,2100'], 'mes' => ['required', 'integer', 'between:1,12']],
            ['mes.between' => 'Elige un mes válido.', 'anio.between' => 'Elige un año válido.'],
            ['anio' => 'año', 'mes' => 'mes'],
        );

        $periodo = $this->periodos->abrir($this->empresa($request), (int) $datos['anio'], (int) $datos['mes']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Periodo {$periodo->etiqueta()} abierto con {$periodo->lineas()->count()} trabajadores.",
        ]);

        return to_route('periodos.show', $periodo);
    }

    public function show(Request $request, Periodo $periodo): Response
    {
        Gate::authorize('view', $periodo);

        /** @var User $usuario */
        $usuario = $request->user();

        $lineas = $periodo->lineas()
            ->with('trabajador:id,tipo_documento,numero_documento,apellido_paterno,apellido_materno,nombres,fecha_ingreso,fecha_cese,cargo')
            ->withCount('novedades')
            ->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)
            ->values()
            ->map(fn (PlanillaLinea $l) => [
                'id' => $l->id,
                'trabajador_id' => $l->trabajador_id,
                'nombre' => $l->trabajador->nombre_completo,
                'documento' => "{$l->trabajador->tipo_documento->abreviatura()} {$l->trabajador->numero_documento}",
                'cargo' => $l->trabajador->cargo,
                'dias_laborados' => $l->dias_laborados,
                'ingresa_en_mes' => $l->trabajador->fecha_ingreso->year === $periodo->anio && $l->trabajador->fecha_ingreso->month === $periodo->mes
                    ? $l->trabajador->fecha_ingreso->format('d/m') : null,
                'cesa_en_mes' => ($cese = $l->trabajador->fecha_cese) !== null && $cese->year === $periodo->anio && $cese->month === $periodo->mes
                    ? $cese->format('d/m') : null,
                'novedades' => (int) $l->getAttribute('novedades_count'),
                'neto_pagar' => $l->calculado_en === null ? null : $l->neto_pagar,
            ]);

        return Inertia::render('periodos/Show', [
            'periodo' => [
                'id' => $periodo->id,
                'etiqueta' => $periodo->etiqueta(),
                'estado' => $periodo->estado->value,
                'estado_etiqueta' => $periodo->estado->etiqueta(),
                'puede_editar' => $usuario->can('update', $periodo),
                'empresa' => $periodo->empresa?->razon_social,
            ],
            'lineas' => $lineas,
        ]);
    }

    public function sincronizar(Periodo $periodo): RedirectResponse
    {
        Gate::authorize('update', $periodo);

        $r = $this->periodos->sincronizar($periodo);

        $mensaje = $r['agregados'] === 0 && $r['quitados'] === 0
            ? 'La lista de trabajadores ya estaba al día.'
            : "Se agregaron {$r['agregados']} y se quitaron {$r['quitados']} trabajadores.";
        if ($r['conservados'] !== []) {
            $mensaje .= ' Se mantuvieron por tener novedades: '.implode(', ', $r['conservados']).'.';
        }

        Inertia::flash('toast', ['type' => $r['conservados'] === [] ? 'success' : 'warning', 'message' => $mensaje]);

        return back();
    }

    public function destroy(Periodo $periodo): RedirectResponse
    {
        Gate::authorize('update', $periodo);

        try {
            $etiqueta = $periodo->etiqueta();
            $this->periodos->eliminar($periodo);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Periodo {$etiqueta} eliminado."]);

            return to_route('periodos.index');
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
