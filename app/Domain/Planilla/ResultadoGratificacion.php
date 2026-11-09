<?php

namespace App\Domain\Planilla;

/** Detalle del cálculo de la gratificación (montos en texto con 2 decimales). */
final readonly class ResultadoGratificacion
{
    /**
     * @param  list<int>  $mesesSinPlanilla  meses del semestre sin planilla en el sistema (se asumieron completos)
     */
    public function __construct(
        public int $mesesComputables,
        public int $diasFalta,
        public int $mesesConVariables,
        public string $promedioVariables,
        public string $remuneracionComputable,
        public string $gratificacion,
        public string $bonificacion,
        public array $mesesSinPlanilla,
    ) {}

    public function corresponde(): bool
    {
        return $this->gratificacion !== '0.00';
    }
}
