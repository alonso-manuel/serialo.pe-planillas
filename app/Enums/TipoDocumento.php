<?php

namespace App\Enums;

/**
 * Tipo de documento de identidad (tabla 3 de SUNAT). ⚠ Códigos a confirmar con el contador.
 * Se agregan otros tipos (PTP, carné de refugio...) cuando un cliente los necesite.
 */
enum TipoDocumento: string
{
    case Dni = '01';
    case CarneExtranjeria = '04';
    case Pasaporte = '07';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::CarneExtranjeria => 'Carné de extranjería',
            self::Pasaporte => 'Pasaporte',
        };
    }

    /** Código del tipo de documento en el archivo de planilla de AFPnet (0 DNI, 1 carné de extranjería, 4 pasaporte). */
    public function codigoAfpnet(): string
    {
        return match ($this) {
            self::Dni => '0',
            self::CarneExtranjeria => '1',
            self::Pasaporte => '4',
        };
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::CarneExtranjeria => 'CE',
            self::Pasaporte => 'PAS',
        };
    }

    /** Formato del número: DNI 8 dígitos; CE y pasaporte hasta 12 letras o dígitos. */
    public function esNumeroValido(string $numero): bool
    {
        return match ($this) {
            self::Dni => preg_match('/^\d{8}$/', $numero) === 1,
            default => preg_match('/^[A-Z0-9]{4,12}$/', $numero) === 1,
        };
    }

    public function mensajeFormato(): string
    {
        return match ($this) {
            self::Dni => 'El DNI debe tener 8 dígitos.',
            default => "El {$this->etiqueta()} debe tener entre 4 y 12 letras o dígitos.",
        };
    }
}
