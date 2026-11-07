<?php

namespace App\Enums;

enum RegimenLaboral: string
{
    case General = 'general';
    case PequenaEmpresa = 'pequena';
    case Microempresa = 'micro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::General => 'Régimen general',
            self::PequenaEmpresa => 'Pequeña empresa (REMYPE)',
            self::Microempresa => 'Microempresa (REMYPE)',
        };
    }
}
