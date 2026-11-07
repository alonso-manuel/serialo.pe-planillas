<?php

namespace App\Enums;

enum SeguroSalud: string
{
    case Essalud = 'essalud';
    case Sis = 'sis'; // solo microempresas acogidas al SIS

    public function etiqueta(): string
    {
        return match ($this) {
            self::Essalud => 'EsSalud',
            self::Sis => 'SIS (solo microempresa)',
        };
    }
}
