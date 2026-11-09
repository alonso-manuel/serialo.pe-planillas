<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/** Detalle del cálculo de la CTS (montos en texto con 2 decimales). */
final readonly class ResultadoCts
{
    /** @param  list<string>  $mesesSinPlanilla  "AAAA-MM" sin planilla en el sistema */
    public function __construct(
        public DateTimeImmutable $computoDesde,
        public DateTimeImmutable $computoHasta,
        public int $meses,
        public int $dias,
        public int $diasDescontados,
        public string $remuneracionBasica,
        public string $asignacionFamiliar,
        public string $sextoGratificacion,
        public string $promedioVariables,
        public string $remuneracionComputable,
        public string $monto,
        public array $mesesSinPlanilla = [],
    ) {}

    public static function sinDerecho(DateTimeImmutable $desde, DateTimeImmutable $hasta): self
    {
        return new self($desde, $hasta, 0, 0, 0, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00');
    }

    public function corresponde(): bool
    {
        return $this->monto !== '0.00';
    }
}
