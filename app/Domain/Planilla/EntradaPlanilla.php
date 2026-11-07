<?php

namespace App\Domain\Planilla;

/**
 * Novedades del mes de un trabajador.
 */
final readonly class EntradaPlanilla
{
    /**
     * @param  array<string, IngresoAdicional>  $ingresosAdicionales  clave = código PLAME
     * @param  array<string, string>  $descuentos  código PLAME => monto (ej. '0701' => '100.00')
     */
    public function __construct(
        public int $diasLaborados = 30,
        public string $horasExtra25 = '0',
        public string $horasExtra35 = '0',
        public array $ingresosAdicionales = [],
        public array $descuentos = [],
    ) {}
}
