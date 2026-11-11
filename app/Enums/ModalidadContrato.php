<?php

namespace App\Enums;

/** Tabla 12 de SUNAT: modalidades de contrato sujeto a modalidad (plazo fijo) del sector privado. */
enum ModalidadContrato: string
{
    case InicioActividad = '3';
    case NecesidadesMercado = '4';
    case ReconversionEmpresarial = '5';
    case Ocasional = '6';
    case Suplencia = '7';
    case Emergencia = '8';
    case ObraDeterminada = '9';
    case Intermitente = '10';
    case Temporada = '11';
    case ExportacionNoTradicional = '12';
    case Extranjero = '13';
    case ADomicilio = '18';
    case Futbolistas = '19';
    case MigranteAndino = '21';
    case Otros = '99';

    public function etiqueta(): string
    {
        return match ($this) {
            self::InicioActividad => 'Por inicio o incremento de actividad',
            self::NecesidadesMercado => 'Por necesidades del mercado',
            self::ReconversionEmpresarial => 'Por reconversión empresarial',
            self::Ocasional => 'Ocasional',
            self::Suplencia => 'De suplencia',
            self::Emergencia => 'De emergencia',
            self::ObraDeterminada => 'Para obra determinada o servicio específico',
            self::Intermitente => 'Intermitente',
            self::Temporada => 'De temporada',
            self::ExportacionNoTradicional => 'Exportación no tradicional (D. Ley 22342)',
            self::Extranjero => 'De extranjero (D. Leg. 689)',
            self::ADomicilio => 'A domicilio',
            self::Futbolistas => 'Futbolistas profesionales',
            self::MigranteAndino => 'Migrante andino (Decisión 545)',
            self::Otros => 'Otros no previstos',
        };
    }
}
