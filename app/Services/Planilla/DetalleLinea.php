<?php

namespace App\Services\Planilla;

use App\Enums\EstadoPeriodo;
use App\Enums\SistemaPension;
use App\Enums\TipoConcepto;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Resultado de un trabajador en un periodo, listo para mostrar: datos del trabajador vigentes en el mes,
 * días, conceptos agrupados y totales. Lo usan la pantalla de detalle y la boleta de pago.
 */
class DetalleLinea
{
    /** Grupos del detalle, en el orden en que se muestran. */
    public const GRUPOS = [
        'ingresos' => [TipoConcepto::Ingreso, 'Ingresos'],
        'aportes_trabajador' => [TipoConcepto::AporteTrabajador, 'Aportes del trabajador'],
        'descuentos' => [TipoConcepto::DescuentoTrabajador, 'Otros descuentos'],
        'aportes_empleador' => [TipoConcepto::AporteEmpleador, 'Aportes del empleador'],
    ];

    public function __construct(private readonly HistorialTrabajador $historial) {}

    /**
     * @return array{
     *     trabajador: array<string, mixed>,
     *     linea: array<string, mixed>,
     *     grupos: array<string, array{titulo: string, conceptos: list<array{codigo: string, nombre: string, monto: string}>, total: string}>,
     *     afectas: array{essalud: string, pension: string|null, quinta: string}|null
     * }
     */
    public function datos(Periodo $periodo, PlanillaLinea $linea): array
    {
        $linea->loadMissing(['trabajador.afp', 'conceptos.concepto']);
        // Datos del trabajador vigentes en el mes (los mismos que usó el cálculo).
        $t = $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia());
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
                'conceptos' => array_values($filas->map(fn (PlanillaConcepto $c) => [
                    'codigo' => $c->concepto->codigo_plame,
                    'nombre' => $c->concepto->nombre,
                    'monto' => $c->monto_pagado,
                ])->all()),
                'total' => $this->suma($filas),
            ];
        }

        $ingresos = $conceptos->filter(fn (PlanillaConcepto $c) => $c->concepto->tipo === TipoConcepto::Ingreso);
        $afecta = fn (string $campo): string => $this->suma(
            $ingresos->filter(fn (PlanillaConcepto $c) => (bool) $c->concepto->getAttribute($campo)),
        );

        return [
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
                    SistemaPension::Afp => $afp->nombre ?? 'AFP (sin asignar)', // el nombre ya dice «AFP …» o «… AFP»
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
        ];
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
