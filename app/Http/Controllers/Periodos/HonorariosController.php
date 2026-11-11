<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\PrestadorServicio;
use App\Models\ReciboHonorarios;
use App\Services\Planilla\HonorariosPeriodo;
use App\Support\Formato;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Recibos por honorarios (cuarta categoría) del periodo: se pagan en el mes y van al PLAME (.4ta / .ps4). */
class HonorariosController extends Controller
{
    public function __construct(private readonly HonorariosPeriodo $honorarios) {}

    public function index(Periodo $periodo): Response
    {
        Gate::authorize('view', $periodo);

        $recibos = $periodo->recibosHonorarios()->with('prestador')->orderBy('fecha_pago')->orderBy('id')->get();
        $suma = fn (string $campo) => (string) $recibos->reduce(fn (BigDecimal $s, ReciboHonorarios $r) => $s->plus((string) $r->getAttribute($campo)), BigDecimal::zero())->toScale(2);

        return Inertia::render('periodos/Honorarios', [
            'periodo' => [
                'id' => $periodo->id,
                'etiqueta' => $periodo->etiqueta(),
                'cerrado' => $periodo->estaCerrado(),
                'desde' => $periodo->ultimoDia()->startOfMonth()->toDateString(),
                'hasta' => $periodo->ultimoDia()->toDateString(),
                'puede_editar' => Gate::allows('update', $periodo),
            ],
            'recibos' => $recibos->map(fn (ReciboHonorarios $r) => [
                'id' => $r->id,
                'prestador' => $r->prestador->nombre_completo,
                'documento' => $r->prestador->numero_documento,
                'comprobante' => "{$r->tipo_comprobante} {$r->serie}-{$r->numero}",
                'fecha_emision' => $r->fecha_emision->format('d/m/Y'),
                'fecha_pago' => $r->fecha_pago->format('d/m/Y'),
                'monto' => (string) $r->monto,
                'retencion' => (string) $r->retencion,
                'neto' => (string) BigDecimal::of((string) $r->monto)->minus((string) $r->retencion)->toScale(2),
            ])->values(),
            'totales' => ['monto' => $suma('monto'), 'retencion' => $suma('retencion')],
            'prestadores' => PrestadorServicio::query()->where('empresa_id', $periodo->empresa_id)->orderBy('apellido_paterno')->get()
                ->map(fn (PrestadorServicio $p) => [
                    'tipo_documento' => $p->tipo_documento, 'numero_documento' => $p->numero_documento,
                    'apellido_paterno' => $p->apellido_paterno, 'apellido_materno' => $p->apellido_materno, 'nombres' => $p->nombres,
                    'domiciliado' => $p->domiciliado, 'suspension_anio' => $p->suspension_anio,
                ])->values(),
        ]);
    }

    public function store(Request $request, Periodo $periodo): RedirectResponse
    {
        Gate::authorize('update', $periodo);
        $desde = $periodo->ultimoDia()->startOfMonth()->toDateString();
        $hasta = $periodo->ultimoDia()->toDateString();

        $datos = $request->validate([
            'tipo_documento' => ['required', Rule::in(['06', '01', '04', '07'])],
            'numero_documento' => ['required', 'string', 'max:15', Rule::when($request->input('tipo_documento') === '06', ['digits:11', 'regex:/^(10|15|17)\d{9}$/'])],
            'apellido_paterno' => ['required', 'string', 'max:40'],
            'apellido_materno' => ['nullable', 'string', 'max:40'],
            'nombres' => ['required', 'string', 'max:40'],
            'domiciliado' => ['boolean'],
            'suspension' => ['boolean'],
            'tipo_comprobante' => ['required', Rule::in(['R', 'N', 'D', 'O'])],
            'serie' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]+$/'],
            'numero' => ['required', 'digits_between:1,8'],
            'fecha_emision' => ['required', 'date_format:Y-m-d', 'before_or_equal:fecha_pago'],
            'fecha_pago' => ['required', 'date_format:Y-m-d', "after_or_equal:{$desde}", "before_or_equal:{$hasta}"],
            'monto' => ['required', 'decimal:0,2', 'gt:0', 'max:99999.99'],
        ], [
            'numero_documento.regex' => 'El RUC de una persona natural empieza con 10, 15 o 17.',
            'fecha_pago.after_or_equal' => "La fecha de pago debe estar dentro de {$periodo->etiqueta()}.",
            'fecha_pago.before_or_equal' => "La fecha de pago debe estar dentro de {$periodo->etiqueta()}.",
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser posterior al pago.',
        ], [
            'numero_documento' => 'RUC / documento', 'tipo_comprobante' => 'tipo de comprobante', 'fecha_emision' => 'fecha de emisión',
            'fecha_pago' => 'fecha de pago', 'serie' => 'serie', 'numero' => 'número',
        ]);

        $existe = ReciboHonorarios::query()
            ->whereHas('prestador', fn ($q) => $q->where('empresa_id', $periodo->empresa_id)->where('tipo_documento', $datos['tipo_documento'])->where('numero_documento', $datos['numero_documento']))
            ->where('tipo_comprobante', $datos['tipo_comprobante'])->where('serie', mb_strtoupper($datos['serie']))->where('numero', ltrim($datos['numero'], '0') ?: '0')
            ->exists();
        if ($existe) {
            return back()->withErrors(['numero' => 'Ese comprobante ya está registrado.']);
        }

        try {
            $texto = fn (string $campo) => (string) $datos[$campo];
            $recibo = $this->honorarios->registrar($periodo, [
                'tipo_documento' => $texto('tipo_documento'),
                'numero_documento' => $texto('numero_documento'),
                'apellido_paterno' => $texto('apellido_paterno'),
                'apellido_materno' => isset($datos['apellido_materno']) ? $texto('apellido_materno') : null,
                'nombres' => $texto('nombres'),
                'domiciliado' => $request->boolean('domiciliado', true),
                'suspension' => $request->boolean('suspension'),
                'tipo_comprobante' => $texto('tipo_comprobante'),
                'serie' => $texto('serie'),
                'numero' => $texto('numero'),
                'fecha_emision' => $texto('fecha_emision'),
                'fecha_pago' => $texto('fecha_pago'),
                'monto' => $texto('monto'),
            ]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => BigDecimal::of((string) $recibo->retencion)->isPositive()
            ? 'Recibo registrado. Retención de cuarta: S/ '.Formato::monto((string) $recibo->retencion).'.'
            : 'Recibo registrado sin retención.']);

        return back();
    }

    public function destroy(Periodo $periodo, ReciboHonorarios $recibo): RedirectResponse
    {
        Gate::authorize('update', $periodo);
        abort_unless($recibo->periodo_id === $periodo->id, 404);

        try {
            $this->honorarios->eliminar($recibo);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Recibo eliminado.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
