<?php

namespace App\Enums;

/**
 * Motivo de fin del periodo laboral (tabla 17 de T-Registro, SUNAT).
 * ⚠ Subconjunto del sector privado; códigos a confirmar con el contador.
 */
enum MotivoCese: string
{
    case Renuncia = '01';
    case RenunciaConIncentivos = '02';
    case Despido = '03';
    case CeseColectivo = '04';
    case Jubilacion = '05';
    case Invalidez = '06';
    case TerminoContrato = '07';
    case MutuoDisenso = '08';
    case Fallecimiento = '09';
    case ExtincionEmpleador = '16';
    case NoSeInicioRelacion = '18';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Renuncia => 'Renuncia',
            self::RenunciaConIncentivos => 'Renuncia con incentivos',
            self::Despido => 'Despido o destitución',
            self::CeseColectivo => 'Cese colectivo',
            self::Jubilacion => 'Jubilación',
            self::Invalidez => 'Invalidez absoluta permanente',
            self::TerminoContrato => 'Terminación de obra o servicio, o vencimiento del plazo',
            self::MutuoDisenso => 'Mutuo disenso',
            self::Fallecimiento => 'Fallecimiento',
            self::ExtincionEmpleador => 'Extinción o liquidación del empleador',
            self::NoSeInicioRelacion => 'No se inició la relación laboral',
        };
    }
}
