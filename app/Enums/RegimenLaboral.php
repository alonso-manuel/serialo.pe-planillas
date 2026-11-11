<?php

namespace App\Enums;

enum RegimenLaboral: string
{
    case General = 'general';
    case PequenaEmpresa = 'pequena';
    case Microempresa = 'micro';
    case Agrario = 'agrario'; // Ley 31110

    public function etiqueta(): string
    {
        return match ($this) {
            self::General => 'Régimen general',
            self::PequenaEmpresa => 'Pequeña empresa (REMYPE)',
            self::Microempresa => 'Microempresa (REMYPE)',
            self::Agrario => 'Régimen agrario (Ley 31110)',
        };
    }
}
