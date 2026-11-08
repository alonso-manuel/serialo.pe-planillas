<?php

namespace App\Http\Controllers\Periodos;

use App\Enums\EstadoPeriodo;
use App\Enums\SistemaPension;
use App\Enums\TipoConcepto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Periodos\GuardarLineaRequest;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Planilla\NovedadesPlanilla;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Una línea (trabajador) del periodo: guardado por fila de la grilla y detalle del resultado. */
class LineaPlanillaController extends Controller
{
    /** Grupos del detalle, en el orden en que se muestran. */
    private const GRUPOS = [
        'ingresos' => [TipoConcepto::Ingreso, 'Ingresos'],
        'aportes_trabajador' => [TipoConcepto::AporteTrabajador, 'Aportes del trabajador'],
        'descuentos' => [TipoConcepto::DescuentoTrabajador, 'Otros descuentos'],
        'aportes_empleador' => [TipoConcepto::AporteEmpleador, 'Aportes del empleador'],
    ];

    public function show(Request $request, Periodo $periodo, PlanillaLinea $linea, HistorialTrabajador $historial): Response
    {
        Gate::authorize('view', $periodo);

        /** @var User $usuario */
        $usuario = $request->user();

        $linea->load(['trabajador.afp', 'conceptos.concepto']);
        // Datos del trabajador vigentes en el mes (los mismos que usó el cálculo).
        $t = $historial->vistaAl($linea->trabajador, $periodo->ultimoDia());
        $afp = $t->afp_id === null ? null : ($t->afp_id === $linea->trabajador->afp_id ? $linea->trabajador->afp : $t->afp()->first());

        $calculada = $linea->calculado_en !== null;
        /** @var Collection<int, PlanillaConcepto> $conceptos */
        $conceptos = $calculada ? $linea->conceptos : collect();

        $grupos = [];
        foreach (self::GRUPOS as $clave => [$tipo, $titulo]) {
            $filas = $conceptos
                ->filter(fn (PlanillaConcepto $c) => $c->concepto->tipo === $tipo)
                ->sortBy(fn (PlanillaConcepto $c) => $c->concepto->codigo_plame)
                ->values();

            $grupos[$clave] = [
                'titulo' => $titulo,
                'conceptos' => $filas->map(fn (PlanillaConcepto $c) => [
                    'codigo' => $c->concepto->codigo_plame,
                    'nombre' => $c->concepto->nombre,
                    'monto' => $c->monto_pagado,
                ])->all(),
                'total' => $this->suma($filas),
            ];
        }

        $ingresos = $conceptos->filter(fn (PlanillaConcepto $c) => $c->concepto->tipo === TipoConcepto::Ingreso);
        $afecta = fn (string $campo): string => $this->suma(
            $ingresos->filter(fn (PlanillaConcepto $c) => (bool) $c->concepto->getAttribute($campo)),
        );

        // Orden de la grilla (alfabético) para navegar entre trabajadores.
        $orden = $periodo->lineas()
            ->with('trabajador:id,apellido_paterno,apellido_materno,nombres')
            ->get(['id', 'trabajador_id'])
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)
            ->pluck('id')
            ->values();
        $posicion = (int) $orden->search($linea->id);

        return Inertia::render('periodos/Linea', [
            'periodo' => [
                'id' => $periodo->id,
                'etiqueta' => $periodo->etiqueta(),
                'estado' => $periodo->estado->value,
                'estado_etiqueta' => $periodo->estado->etiqueta(),
                'puede_editar' => $usuario->can('update', $periodo),
                'empresa' => $periodo->empresa?->razon_social,
                'regimen' => $periodo->empresa?->regimen_laboral->etiqueta(),
            ],
            'trabajador' => [
                'id' => $t->id,
                'nombre' => $t->nombre_completo,
                'documento' => "{$t->tipo_documento->abreviatura()} {$t->numero_documento}",
                'cargo' => $t->cargo,
                'contrato' => $t->tipo_contrato->etiqueta(),
                'fecha_ingreso' => $t->fecha_ingreso->format('d/m/Y'),
                'fecha_cese' => $t->fecha_cese?->format('d/m/Y'),
                'remuneracion_basica' => $t->remuneracion_basica,
                'asignacion_familiar' => $t->asignacion_familiar,
                'pension' => match ($t->sistema_pension) {
                    SistemaPension::Onp => 'ONP',
                    SistemaPension::Afp => 'AFP '.($afp->nombre ?? '(sin asignar)'),
                    SistemaPension::Ninguno => 'Sin sistema de pensiones',
                },
                'comision' => $t->sistema_pension === SistemaPension::Afp ? $t->tipo_comision_afp?->etiqueta() : null,
                'cuspp' => $t->sistema_pension === SistemaPension::Afp ? $t->cuspp : null,
                'seguro_salud' => $t->seguro_salud->etiqueta(),
            ],
            'linea' => [
                'id' => $linea->id,
                'dias_laborados' => $linea->dias_laborados,
                'dias_no_laborados' => $linea->dias_no_laborados,
                'dias_subsidiados' => $linea->dias_subsidiados,
                'horas_extra_25' => $linea->horas_extra_25,
                'horas_extra_35' => $linea->horas_extra_35,
                'calculada' => $calculada,
                // Se editaron novedades después del último cálculo (el periodo volvió a «abierto»).
                'desactualizada' => $calculada && $periodo->estado === EstadoPeriodo::Abierto,
                'calculado_en' => $linea->calculado_en?->format('d/m/Y H:i'),
                'total_ingresos' => $linea->total_ingresos,
                'total_descuentos' => $linea->total_descuentos,
                'total_aportes_empleador' => $linea->total_aportes_empleador,
                'neto_pagar' => $linea->neto_pagar,
            ],
            'grupos' => $grupos,
            'afectas' => $calculada ? [
                'essalud' => $afecta('afecto_essalud'),
                'pension' => match ($t->sistema_pension) {
                    SistemaPension::Onp => $afecta('afecto_onp'),
                    SistemaPension::Afp => $afecta('afecto_afp'),
                    SistemaPension::Ninguno => null,
                },
                'quinta' => $afecta('afecto_quinta'),
            ] : null,
            'anterior' => $posicion > 0 ? $orden[$posicion - 1] : null,
            'siguiente' => $orden[$posicion + 1] ?? null,
            'posicion' => $posicion + 1,
            'total' => $orden->count(),
        ]);
    }

    public function update(GuardarLineaRequest $request, Periodo $periodo, PlanillaLinea $linea, NovedadesPlanilla $novedades): JsonResponse
    {
        $linea = $novedades->guardarLinea($periodo, $linea, $request->datos());

        return response()->json([
            ...$novedades->fila($linea, $periodo),
            'estado_periodo' => $periodo->refresh()->estado->value,
        ]);
    }

    /**
     * Suma de montos pagados ya redondeados (sin float).
     *
     * @param  Collection<int, PlanillaConcepto>  $conceptos
     */
    private function suma(Collection $conceptos): string
    {
        return (string) $conceptos
            ->reduce(fn (BigDecimal $s, PlanillaConcepto $c) => $s->plus($c->monto_pagado), BigDecimal::zero())
            ->toScale(2, RoundingMode::HalfUp);
    }
}
