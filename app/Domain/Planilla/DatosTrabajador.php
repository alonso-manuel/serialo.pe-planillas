<?php

namespace App\Domain\Planilla;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;

final readonly class DatosTrabajador
{
    public function __construct(
        public string $remuneracionBasica,
        public bool $asignacionFamiliar,
        public SistemaPension $sistemaPension,
        public SeguroSalud $seguroSalud,
        public ?TasaAfpPeriodo $tasaAfp = null,
    ) {}
}
