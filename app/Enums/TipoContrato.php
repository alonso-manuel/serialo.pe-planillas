<?php

namespace App\Enums;

/**
 * Modalidad de contrato. Versión simplificada para v1.0; las modalidades y códigos
 * de la tabla 12 de SUNAT se completan con T-Registro (v1.1).
 */
enum TipoContrato: string
{
    case Indeterminado = 'indeterminado';
    case PlazoFijo = 'plazo_fijo';
    case TiempoParcial = 'tiempo_parcial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Indeterminado => 'Plazo indeterminado',
            self::PlazoFijo => 'Plazo fijo',
            self::TiempoParcial => 'Tiempo parcial',
        };
    }
}
