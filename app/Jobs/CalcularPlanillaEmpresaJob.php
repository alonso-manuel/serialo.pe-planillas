<?php

namespace App\Jobs;

use App\Models\Periodo;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use App\Services\Planilla\CalculoMasivo;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Calcula la planilla de una empresa dentro de un cálculo masivo. */
class CalcularPlanillaEmpresaJob implements ShouldQueue
{
    use Batchable, Queueable;

    /** Un solo intento: los problemas de datos no se arreglan reintentando. */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $calculoId,
        public readonly int $periodoId,
    ) {}

    public function handle(CalculoMasivo $masivo, CalcularPlanillaPeriodo $calculo): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $periodo = Periodo::query()->find($this->periodoId);

        if ($periodo !== null) {
            $masivo->calcularEmpresa($this->calculoId, $periodo, $calculo);
        }
    }
}
