<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/** Datos de un trabajador despedido arbitrariamente, para su indemnización. */
final readonly class EntradaIndemnizacion
{
    /**
     * @param  string  $remuneracionMensual  remuneración ordinaria mensual (básico + asignación familiar + promedio de variables) ⚠
     * @param  DateTimeImmutable|null  $finContrato  vencimiento del contrato a plazo fijo; null = indeterminado
     */
    public function __construct(
        public DateTimeImmutable $fechaIngreso,
        public DateTimeImmutable $fechaCese,
        public string $remuneracionMensual,
        public ?DateTimeImmutable $finContrato = null,
    ) {}
}
