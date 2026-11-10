<?php

namespace App\Domain\Licencia;

enum TipoEstadoLicencia: string
{
    case Valida = 'valida';
    case Prueba = 'prueba';
    case SinLicencia = 'sin_licencia';
    case Invalida = 'invalida';
    case OtroDominio = 'otro_dominio';
    case Omitida = 'omitida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Valida => 'Activa',
            self::Prueba => 'Prueba',
            self::SinLicencia => 'Sin licencia',
            self::Invalida => 'Clave no válida',
            self::OtroDominio => 'Otro dominio',
            self::Omitida => 'Desarrollo',
        };
    }
}
