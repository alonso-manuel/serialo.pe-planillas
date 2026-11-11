<?php

namespace App\Domain\Planilla;

/**
 * Parámetros de la indemnización por despido según el régimen (tabla `parametros`), en remuneraciones diarias:
 * general 45 por año (1.5 sueldos) con tope 360 (12 sueldos); pequeña empresa 20 / 120; microempresa 10 / 90.
 */
final readonly class ParametrosIndemnizacion
{
    public function __construct(
        public int $diasPorAnio,
        public int $topeDias,
        /** Plazo fijo (art. 76): días por cada mes que faltaba; null = el régimen usa la fórmula por año ⚠. */
        public ?int $diasPorMesPlazoFijo,
        /** Sin indemnización dentro del periodo de prueba. */
        public int $mesesPrueba = 3,
    ) {}
}
