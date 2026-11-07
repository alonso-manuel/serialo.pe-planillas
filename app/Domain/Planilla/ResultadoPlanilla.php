<?php

namespace App\Domain\Planilla;

/**
 * Resultado del cálculo de un trabajador en un periodo.
 * Todos los montos son strings con 2 decimales.
 */
final readonly class ResultadoPlanilla
{
    /**
     * @param  array<string, string>  $ingresos  código PLAME => monto
     * @param  array<string, string>  $aportesTrabajador  código PLAME => monto (AFP/ONP)
     * @param  array<string, string>  $descuentos  código PLAME => monto
     * @param  array<string, string>  $aportesEmpleador  código PLAME => monto (EsSalud/SIS)
     */
    public function __construct(
        public array $ingresos,
        public array $aportesTrabajador,
        public array $descuentos,
        public array $aportesEmpleador,
        public string $totalIngresos,
        public string $totalDescuentos,
        public string $totalAportesEmpleador,
        public string $netoPagar,
    ) {}

    /**
     * Todos los conceptos en un solo arreglo (para la boleta y el archivo .rem).
     *
     * @return array<string, string> código PLAME => monto
     */
    public function conceptos(): array
    {
        return $this->ingresos + $this->aportesTrabajador + $this->descuentos + $this->aportesEmpleador;
    }
}
