<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/**
 * Datos de un trabajador para calcular su gratificación de julio o diciembre.
 *
 * `meses` trae solo los meses del semestre que tienen planilla en el sistema (anteriores al mes de pago):
 * mes (1–12) => [días de falta injustificada, monto de conceptos variables del mes].
 * Los meses que no tienen planilla se cuentan como completos, sin faltas ni variables (y se informan).
 */
final readonly class EntradaGratificacion
{
    /**
     * @param  int  $mesPago  7 (julio) o 12 (diciembre); la calculadora rechaza otros meses
     * @param  array<int, array{faltas: int, variables: string}>  $meses
     */
    public function __construct(
        public int $anio,
        public int $mesPago,
        public DateTimeImmutable $fechaIngreso,
        public string $remuneracionBasica,
        public bool $asignacionFamiliar,
        public array $meses = [],
        /** Cese (liquidación): solo cuentan los meses completos hasta esa fecha (gratificación trunca). */
        public ?DateTimeImmutable $fechaCese = null,
    ) {}
}
