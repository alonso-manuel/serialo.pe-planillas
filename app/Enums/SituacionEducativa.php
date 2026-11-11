<?php

namespace App\Enums;

/** Tabla 9 de SUNAT (T-Registro): situación educativa del trabajador. */
enum SituacionEducativa: string
{
    case SinEducacion = '01';
    case EspecialIncompleta = '02';
    case EspecialCompleta = '03';
    case PrimariaIncompleta = '04';
    case PrimariaCompleta = '05';
    case SecundariaIncompleta = '06';
    case SecundariaCompleta = '07';
    case TecnicaIncompleta = '08';
    case TecnicaCompleta = '09';
    case SuperiorIncompleta = '10';
    case SuperiorCompleta = '11';
    case UniversitariaIncompleta = '12';
    case UniversitariaCompleta = '13';
    case Bachiller = '14';
    case Titulado = '15';
    case MaestriaIncompleta = '16';
    case MaestriaCompleta = '17';
    case GradoMaestria = '18';
    case DoctoradoIncompleto = '19';
    case DoctoradoCompleto = '20';
    case GradoDoctor = '21';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinEducacion => 'Sin educación formal',
            self::EspecialIncompleta => 'Educación especial incompleta',
            self::EspecialCompleta => 'Educación especial completa',
            self::PrimariaIncompleta => 'Primaria incompleta',
            self::PrimariaCompleta => 'Primaria completa',
            self::SecundariaIncompleta => 'Secundaria incompleta',
            self::SecundariaCompleta => 'Secundaria completa',
            self::TecnicaIncompleta => 'Técnica incompleta (CETPRO)',
            self::TecnicaCompleta => 'Técnica completa (CETPRO)',
            self::SuperiorIncompleta => 'Superior incompleta (instituto)',
            self::SuperiorCompleta => 'Superior completa (instituto)',
            self::UniversitariaIncompleta => 'Universitaria incompleta',
            self::UniversitariaCompleta => 'Universitaria completa',
            self::Bachiller => 'Grado de bachiller',
            self::Titulado => 'Titulado',
            self::MaestriaIncompleta => 'Maestría incompleta',
            self::MaestriaCompleta => 'Maestría completa',
            self::GradoMaestria => 'Grado de maestría',
            self::DoctoradoIncompleto => 'Doctorado incompleto',
            self::DoctoradoCompleto => 'Doctorado completo',
            self::GradoDoctor => 'Grado de doctor',
        };
    }
}
