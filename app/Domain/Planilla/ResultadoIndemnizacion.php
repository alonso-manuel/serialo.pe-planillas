<?php

namespace App\Domain\Planilla;

/** Indemnización por despido arbitrario calculada. */
final readonly class ResultadoIndemnizacion
{
    public function __construct(
        /** Tabla 22: 0501 (despido) o 0503 (resolución de contrato sujeto a modalidad). */
        public string $codigo,
        public string $monto,
        public string $remuneracionMensual,
        /** Tiempo que se indemniza: servicios (indeterminado) o lo que faltaba del contrato (plazo fijo). */
        public int $anios,
        public int $meses,
        public int $dias,
        public bool $topeAplicado,
        public ?string $aviso = null,
    ) {}

    /** @return array<string, string|int|bool|null> */
    public function detalle(): array
    {
        return [
            'codigo' => $this->codigo, 'monto' => $this->monto, 'remuneracion' => $this->remuneracionMensual,
            'anios' => $this->anios, 'meses' => $this->meses, 'dias' => $this->dias, 'tope' => $this->topeAplicado,
        ];
    }
}
