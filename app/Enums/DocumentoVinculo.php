<?php

namespace App\Enums;

/** Tabla 27 de SUNAT: documento que sustenta el vínculo familiar. */
enum DocumentoVinculo: string
{
    case EscrituraPublica = '01';
    case SentenciaPaternidad = '02';
    case Testamento = '03';
    case ResolucionIncapacidad = '04';
    case PartidaMatrimonio = '05';
    case PartidaMatrimonioConsular = '06';
    case PartidaMatrimonioExterior = '07';
    case EscrituraUnionHecho = '08';
    case ResolucionUnionHecho = '09';
    case ActaNacimiento = '10';
    case DeclaracionUnionHecho = '11';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EscrituraPublica => 'Escritura pública',
            self::SentenciaPaternidad => 'Sentencia de declaratoria de paternidad',
            self::Testamento => 'Testamento',
            self::ResolucionIncapacidad => 'Resolución de incapacidad',
            self::PartidaMatrimonio => 'Acta o partida de matrimonio civil',
            self::PartidaMatrimonioConsular => 'Partida de matrimonio inscrita en registro consular peruano',
            self::PartidaMatrimonioExterior => 'Partida de matrimonio del exterior inscrita en RENIEC o municipalidad',
            self::EscrituraUnionHecho => 'Escritura pública de reconocimiento de unión de hecho (Ley 29560)',
            self::ResolucionUnionHecho => 'Resolución judicial de reconocimiento de unión de hecho',
            self::ActaNacimiento => 'Acta de nacimiento o documento análogo',
            self::DeclaracionUnionHecho => 'Declaración jurada de existencia de unión de hecho',
        };
    }
}
