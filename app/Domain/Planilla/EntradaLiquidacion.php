<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/**
 * Datos de un trabajador que cesa, para su liquidación de beneficios sociales.
 *
 * `meses` trae los meses con planilla en el sistema (clave "AAAA-MM") de los últimos doce meses hasta el cese:
 * faltas, licencias sin goce, días de subsidio por enfermedad y monto de variables (horas extra).
 */
final readonly class EntradaLiquidacion
{
    /**
     * @param  array<string, array{faltas: int, licencias: int, subsidio_enfermedad: int, variables: string}>  $meses
     * @param  string|null  $gratificacionPercibida  gratificación cobrada en planilla dentro del periodo de la CTS trunca
     * @param  list<PeriodoVacacional>  $periodosVacacionales  años de servicio al cese, con lo gozado
     */
    public function __construct(
        public DateTimeImmutable $fechaIngreso,
        public DateTimeImmutable $fechaCese,
        public string $remuneracionBasica,
        public bool $asignacionFamiliar,
        public array $meses = [],
        public ?string $gratificacionPercibida = null,
        public array $periodosVacacionales = [],
    ) {}
}
