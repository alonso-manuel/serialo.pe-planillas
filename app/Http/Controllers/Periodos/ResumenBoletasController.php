<?php

namespace App\Http\Controllers\Periodos;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Enums\RegimenLaboral;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EnvioBoleta;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accesos del menú a lo que vive dentro de cada periodo de la empresa activa:
 * boletas (descarga y envío) y gratificaciones (julio y diciembre).
 */
class ResumenBoletasController extends Controller
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    public function boletas(Request $request): Response
    {
        $empresa = $this->empresa($request);

        $periodos = Periodo::query()->deEmpresa($empresa->id)
            ->whereHas('lineas', fn ($q) => $q->whereNotNull('calculado_en'))
            ->withCount(['lineas as calculadas_count' => fn ($q) => $q->whereNotNull('calculado_en')])
            ->orderByDesc('anio')->orderByDesc('mes')->get();

        $ids = $periodos->pluck('id');
        $enviadas = EnvioBoleta::query()->where('estado', EnvioBoleta::ENVIADO)
            ->join('planilla_lineas', 'planilla_lineas.id', '=', 'envios_boleta.planilla_linea_id')
            ->whereIn('planilla_lineas.periodo_id', $ids)
            ->selectRaw('planilla_lineas.periodo_id, count(distinct envios_boleta.planilla_linea_id) as total')
            ->groupBy('planilla_lineas.periodo_id')->pluck('total', 'periodo_id');
        $conCorreo = PlanillaLinea::query()->whereIn('periodo_id', $ids)->whereNotNull('calculado_en')
            ->whereHas('trabajador', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->selectRaw('periodo_id, count(*) as total')->groupBy('periodo_id')->pluck('total', 'periodo_id');

        return Inertia::render('boletas/Index', [
            'empresa' => $empresa->razon_social,
            'periodos' => $periodos->map(fn (Periodo $p) => [
                'id' => $p->id,
                'etiqueta' => $p->etiqueta(),
                'estado' => $p->estado->value,
                'estado_etiqueta' => $p->estado->etiqueta(),
                'cerrado' => $p->estaCerrado(),
                'boletas' => (int) $p->getAttribute('calculadas_count'),
                'enviadas' => (int) ($enviadas[$p->id] ?? 0),
                'con_correo' => (int) ($conCorreo[$p->id] ?? 0),
            ])->all(),
        ]);
    }

    public function gratificaciones(Request $request): Response
    {
        $empresa = $this->empresa($request);

        $periodos = Periodo::query()->deEmpresa($empresa->id)->whereIn('mes', [7, 12])
            ->orderByDesc('anio')->orderByDesc('mes')->get();

        $montos = PlanillaConcepto::query()
            ->join('planilla_lineas', 'planilla_lineas.id', '=', 'planilla_conceptos.planilla_linea_id')
            ->join('conceptos', 'conceptos.id', '=', 'planilla_conceptos.concepto_id')
            ->whereIn('planilla_lineas.periodo_id', $periodos->pluck('id'))
            ->whereIn('conceptos.codigo_plame', [CalculadoraGratificacion::GRATIFICACION, CalculadoraGratificacion::BONIFICACION])
            ->get(['planilla_lineas.periodo_id', 'conceptos.codigo_plame', 'planilla_conceptos.monto_pagado', 'planilla_lineas.trabajador_id'])
            ->groupBy('periodo_id');

        // Próxima gratificación: julio o diciembre que viene.
        $hoy = now();
        [$anioProx, $mesProx] = $hoy->month <= 7 ? [$hoy->year, 7] : [$hoy->year, 12];

        return Inertia::render('gratificaciones/Index', [
            'empresa' => $empresa->razon_social,
            'aplica' => $empresa->regimen_laboral !== RegimenLaboral::Microempresa,
            'regimen' => $empresa->regimen_laboral->etiqueta(),
            'proxima' => Periodo::MESES[$mesProx]." {$anioProx}",
            'periodos' => $periodos->map(function (Periodo $p) use ($montos) {
                $filas = $montos->get($p->id, collect());
                $suma = fn (string $codigo) => (string) $filas->where('codigo_plame', $codigo)
                    ->reduce(fn (BigDecimal $s, $f) => $s->plus((string) $f->getAttribute('monto_pagado')), BigDecimal::zero())->toScale(2);

                return [
                    'id' => $p->id,
                    'etiqueta' => $p->etiqueta(),
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'calculada' => $filas->isNotEmpty(),
                    'trabajadores' => $filas->where('codigo_plame', CalculadoraGratificacion::GRATIFICACION)->pluck('trabajador_id')->unique()->count(),
                    'gratificacion' => $suma(CalculadoraGratificacion::GRATIFICACION),
                    'bonificacion' => $suma(CalculadoraGratificacion::BONIFICACION),
                ];
            })->all(),
        ]);
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
