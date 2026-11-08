<?php

namespace App\Services\Planilla;

use App\Enums\EstadoPeriodo;
use App\Models\Auditoria;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cierre y reapertura de un periodo (solo admin; la autorización la hace la policy).
 * - Cerrar: solo un periodo «calculado». Queda inmutable (regla 5).
 * - Reabrir: vuelve a «calculado» (los montos no cambian) y exige un motivo.
 * Ambas acciones quedan en la auditoría con acción propia («cerrado» / «reabierto»).
 */
class CierrePeriodo
{
    public function cerrar(Periodo $periodo, User $usuario): void
    {
        if ($periodo->estado !== EstadoPeriodo::Calculado) {
            throw new \DomainException($periodo->estaCerrado()
                ? 'El periodo ya está cerrado.'
                : 'Solo se puede cerrar un periodo calculado. Calcula la planilla antes de cerrarla.');
        }

        DB::transaction(function () use ($periodo, $usuario) {
            $anterior = $periodo->estado->value;

            Auditoria::sinRegistrar(fn () => $periodo->update([
                'estado' => EstadoPeriodo::Cerrado,
                'cerrado_por' => $usuario->id,
                'cerrado_en' => now(),
            ]));

            $periodo->registrarAuditoria('cerrado', ['estado' => [$anterior, EstadoPeriodo::Cerrado->value]]);
        });
    }

    public function reabrir(Periodo $periodo, string $motivo): void
    {
        if (! $periodo->estaCerrado()) {
            throw new \DomainException('El periodo no está cerrado.');
        }

        DB::transaction(function () use ($periodo, $motivo) {
            Auditoria::sinRegistrar(fn () => $periodo->update([
                'estado' => EstadoPeriodo::Calculado,
                'cerrado_por' => null,
                'cerrado_en' => null,
            ]));

            $periodo->registrarAuditoria('reabierto', [
                'estado' => [EstadoPeriodo::Cerrado->value, EstadoPeriodo::Calculado->value],
                'motivo' => [null, $motivo],
            ]);
        });
    }

    /**
     * Avisos de orden entre meses (no bloquean; los meses siguientes usarán datos de los anteriores).
     *
     * @return array{anterior_sin_cerrar: string|null, posteriores_cerrados: list<string>}
     */
    public function avisos(Periodo $periodo): array
    {
        $previo = $periodo->mes === 1 ? [$periodo->anio - 1, 12] : [$periodo->anio, $periodo->mes - 1];

        $anterior = Periodo::query()
            ->where('empresa_id', $periodo->empresa_id)
            ->where('anio', $previo[0])->where('mes', $previo[1])
            ->first();

        $posteriores = Periodo::query()
            ->where('empresa_id', $periodo->empresa_id)
            ->where('estado', EstadoPeriodo::Cerrado)
            ->where(fn ($q) => $q->where('anio', '>', $periodo->anio)
                ->orWhere(fn ($q) => $q->where('anio', $periodo->anio)->where('mes', '>', $periodo->mes)))
            ->orderBy('anio')->orderBy('mes')
            ->get();

        return [
            'anterior_sin_cerrar' => $anterior !== null && ! $anterior->estaCerrado() ? $anterior->etiqueta() : null,
            'posteriores_cerrados' => array_values($posteriores->map(fn (Periodo $p) => $p->etiqueta())->all()),
        ];
    }
}
