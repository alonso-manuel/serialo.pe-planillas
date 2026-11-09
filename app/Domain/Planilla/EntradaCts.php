<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/**
 * Datos de un trabajador para su CTS de mayo o noviembre.
 *
 * `meses` trae solo los meses del periodo que tienen planilla en el sistema, con clave "AAAA-MM":
 * faltas injustificadas, licencias sin goce, días de subsidio por enfermedad (21) y monto de variables.
 * Los meses sin planilla se cuentan completos, sin descuentos ni variables (y se informan).
 */
final readonly class EntradaCts
{
    /**
     * @param  int  $mesDeposito  5 (mayo) u 11 (noviembre)
     * @param  string  $gratificacion  gratificación percibida en el periodo (diciembre o julio)
     * @param  array<string, array{faltas: int, licencias: int, subsidio_enfermedad: int, variables: string}>  $meses
     */
    public function __construct(
        public int $anio,
        public int $mesDeposito,
        public DateTimeImmutable $fechaIngreso,
        public string $remuneracionBasica,
        public bool $asignacionFamiliar,
        public string $gratificacion = '0',
        public array $meses = [],
    ) {}
}
