<?php

namespace App\Enums;

/** Tabla 20 de SUNAT: motivo de baja como derechohabiente. */
enum MotivoBajaDerechohabiente: string
{
    case Fallecimiento = '02';
    case Otros = '03';
    case Divorcio = '04';
    case FinConcubinato = '05';
    case FinGestacion = '06';
    case MayoriaEdad = '07';
    case ErrorRegistro = '08';
    case AseguradoRegular = '09';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fallecimiento => 'Fallecimiento',
            self::Otros => 'Otros motivos no previstos',
            self::Divorcio => 'Divorcio o disolución del vínculo matrimonial',
            self::FinConcubinato => 'Fin del concubinato',
            self::FinGestacion => 'Fin de la gestación',
            self::MayoriaEdad => 'El hijo cumplió la mayoría de edad',
            self::ErrorRegistro => 'Error en el registro',
            self::AseguradoRegular => 'Pasó a ser asegurado regular',
        };
    }
}
