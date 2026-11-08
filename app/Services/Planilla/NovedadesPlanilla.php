<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\DiasLaborados;
use App\Enums\EstadoPeriodo;
use App\Enums\TipoConcepto;
use App\Models\Concepto;
use App\Models\Novedad;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Novedades del mes por trabajador (grilla del periodo).
 * - Días pagados = días del mes (según ingreso/cese) − faltas − días subsidiados.
 * - Una novedad por concepto y línea; monto 0 o vacío la elimina.
 * - Editar un periodo calculado lo devuelve a Abierto (hay que recalcular).
 */
class NovedadesPlanilla
{
    /** Días del mes que le corresponden al trabajador (mes comercial de 30). */
    public function diasDelMes(PlanillaLinea $linea, Periodo $periodo): int
    {
        $trabajador = $linea->trabajador;

        return DiasLaborados::proponer(
            $trabajador->fecha_ingreso->toDateTimeImmutable(),
            $trabajador->fecha_cese?->toDateTimeImmutable(),
            $periodo->anio,
            $periodo->mes,
        );
    }

    /**
     * Conceptos que se pueden ingresar como novedad: ingresos y descuentos activos, no de sistema.
     *
     * @return Collection<int, Concepto>
     */
    public function conceptosDisponibles(): Collection
    {
        return Concepto::query()
            ->where('activo', true)
            ->where('sistema', false)
            ->whereIn('tipo', [TipoConcepto::Ingreso, TipoConcepto::DescuentoTrabajador])
            ->orderBy('codigo_plame')
            ->get();
    }

    /**
     * @param  array{dias_no_laborados: int, dias_subsidiados: int, horas_extra_25: string, horas_extra_35: string, novedades: array<int, string|null>}  $datos
     */
    public function guardarLinea(Periodo $periodo, PlanillaLinea $linea, array $datos): PlanillaLinea
    {
        return DB::transaction(function () use ($periodo, $linea, $datos) {
            $diasMes = $this->diasDelMes($linea, $periodo);

            $linea->update([
                'dias_no_laborados' => $datos['dias_no_laborados'],
                'dias_subsidiados' => $datos['dias_subsidiados'],
                'dias_laborados' => max(0, $diasMes - $datos['dias_no_laborados'] - $datos['dias_subsidiados']),
                'horas_extra_25' => $datos['horas_extra_25'],
                'horas_extra_35' => $datos['horas_extra_35'],
            ]);

            $existentes = $linea->novedades()->get()->keyBy('concepto_id');

            foreach ($datos['novedades'] as $conceptoId => $monto) {
                /** @var Novedad|null $novedad */
                $novedad = $existentes->get($conceptoId);
                $vacio = $monto === null || $monto === '' || BigDecimal::of($monto)->isZero();

                if ($vacio) {
                    $novedad?->delete();
                } elseif ($novedad === null) {
                    $linea->novedades()->create(['concepto_id' => $conceptoId, 'monto' => $monto]);
                } else {
                    $novedad->update(['monto' => $monto]);
                }
            }

            if ($periodo->estado === EstadoPeriodo::Calculado) {
                $periodo->update(['estado' => EstadoPeriodo::Abierto]);
            }

            return $linea->refresh();
        });
    }

    /**
     * Fila de la grilla tal como la usa la pantalla.
     *
     * @return array<string, mixed>
     */
    public function fila(PlanillaLinea $linea, Periodo $periodo): array
    {
        $linea->loadMissing('novedades');

        return [
            'dias_mes' => $this->diasDelMes($linea, $periodo),
            'dias_no_laborados' => $linea->dias_no_laborados,
            'dias_subsidiados' => $linea->dias_subsidiados,
            'dias_laborados' => $linea->dias_laborados,
            'horas_extra_25' => $linea->horas_extra_25,
            'horas_extra_35' => $linea->horas_extra_35,
            'novedades' => $linea->novedades
                ->mapWithKeys(fn (Novedad $n) => [$n->concepto_id => $n->monto])
                ->all(),
        ];
    }
}
