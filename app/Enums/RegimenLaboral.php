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

    /** Micro o pequeña empresa (REMYPE): sus trabajadores antiguos pueden conservar el régimen general. */
    public function esMype(): bool
    {
        return $this === self::PequenaEmpresa || $this === self::Microempresa;
    }

    /** ¿Tiene gratificaciones y CTS? La microempresa no (factores con vigencia en parámetros; hoy 0). */
    public function tieneGratificacionYCts(): bool
    {
        return $this !== self::Microempresa;
    }
}
