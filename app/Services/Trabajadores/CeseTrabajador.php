<?php

namespace App\Services\Trabajadores;

use App\Domain\Licencia\Recurso;
use App\Enums\MotivoCese;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Services\Licencia\LicenciaActual;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cese del trabajador (fecha y motivo de la tabla 17 de T-Registro).
 * - Puede ser futuro (fin de contrato programado): sigue activo hasta esa fecha.
 * - No puede quedar en una planilla de un mes posterior al cese.
 * - Revertir = error de registro. El reingreso con nuevo periodo laboral llega con T-Registro (v1.1).
 * La liquidación de beneficios sociales se calcula en Beneficios → Liquidaciones (LiquidacionesBeneficios).
 */
class CeseTrabajador
{
    public function __construct(private readonly LicenciaActual $licencia) {}

    public function registrar(Trabajador $trabajador, CarbonImmutable $fecha, MotivoCese $motivo): void
    {
        if ($fecha->lt($trabajador->fecha_ingreso)) {
            throw ValidationException::withMessages([
                'fecha_cese' => 'El cese no puede ser anterior al ingreso ('.$trabajador->fecha_ingreso->format('d/m/Y').').',
            ]);
        }

        $posterior = Periodo::query()
            ->whereHas('lineas', fn ($q) => $q->where('trabajador_id', $trabajador->id))
            ->whereRaw('anio * 100 + mes > ?', [$fecha->year * 100 + $fecha->month])
            ->orderBy('anio')->orderBy('mes')
            ->first();

        if ($posterior !== null) {
            throw ValidationException::withMessages([
                'fecha_cese' => sprintf(
                    'Figura en la planilla de %02d/%d, posterior al cese. Retíralo de ese periodo primero.',
                    $posterior->mes, $posterior->anio,
                ),
            ]);
        }

        $this->descartarLiquidacion($trabajador, 'fecha_cese');
        $trabajador->update(['fecha_cese' => $fecha->toDateString(), 'motivo_cese' => $motivo]);
    }

    public function revertir(Trabajador $trabajador): void
    {
        // Si ya había cesado, revertir lo vuelve a contar como activo.
        if ($trabajador->estaCesado()) {
            $this->licencia->asegurarCupo(Recurso::Trabajadores);
        }

        $this->descartarLiquidacion($trabajador, 'cese');
        $trabajador->update(['fecha_cese' => null, 'motivo_cese' => null]);
    }

    /** Con una liquidación cerrada el cese no cambia; una en borrador se descarta (se recalcula con el nuevo cese). */
    private function descartarLiquidacion(Trabajador $trabajador, string $campo): void
    {
        $liquidaciones = Liquidacion::query()->where('trabajador_id', $trabajador->id)->get();

        if ($liquidaciones->contains(fn (Liquidacion $l) => $l->estaCerrada())) {
            throw ValidationException::withMessages([
                $campo => 'La liquidación de beneficios está cerrada: reábrela antes de cambiar el cese.',
            ]);
        }

        $liquidaciones->each(fn (Liquidacion $l) => $l->delete());
    }
}
