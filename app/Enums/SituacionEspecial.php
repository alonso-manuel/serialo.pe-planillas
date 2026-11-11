<?php

namespace App\Enums;

/** Tabla 35 de SUNAT (T-Registro): trabajador de dirección o de confianza y teletrabajo. */
enum SituacionEspecial: string
{
    case Ninguna = '0';
    case DireccionPresencial = '1';
    case ConfianzaPresencial = '2';
    case DireccionTeletrabajoMixto = '3';
    case ConfianzaTeletrabajoMixto = '4';
    case DireccionTeletrabajoCompleto = '5';
    case ConfianzaTeletrabajoCompleto = '6';
    case TeletrabajoMixto = '7';
    case TeletrabajoCompleto = '8';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ninguna => 'Ninguna',
            self::DireccionPresencial => 'De dirección (presencial)',
            self::ConfianzaPresencial => 'De confianza (presencial)',
            self::DireccionTeletrabajoMixto => 'De dirección (teletrabajo mixto)',
            self::ConfianzaTeletrabajoMixto => 'De confianza (teletrabajo mixto)',
            self::DireccionTeletrabajoCompleto => 'De dirección (teletrabajo completo)',
            self::ConfianzaTeletrabajoCompleto => 'De confianza (teletrabajo completo)',
            self::TeletrabajoMixto => 'Teletrabajo mixto',
            self::TeletrabajoCompleto => 'Teletrabajo completo',
        };
    }
}
