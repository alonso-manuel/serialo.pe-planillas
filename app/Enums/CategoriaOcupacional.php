<?php

namespace App\Enums;

/** Tabla 24 de SUNAT (sector privado) y el tipo de trabajador de la tabla 8 que le corresponde. */
enum CategoriaOcupacional: string
{
    case Ejecutivo = '01';
    case Obrero = '02';
    case Empleado = '03';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ejecutivo => 'Ejecutivo',
            self::Obrero => 'Obrero',
            self::Empleado => 'Empleado',
        };
    }

    /** Tabla 8: 19 ejecutivo, 20 obrero, 21 empleado. */
    public function tipoTrabajador(): string
    {
        return match ($this) {
            self::Ejecutivo => '19',
            self::Obrero => '20',
            self::Empleado => '21',
        };
    }

    /** Columna de la tabla 30 que indica si una ocupación aplica a esta categoría. */
    public function columnaOcupacion(): string
    {
        return match ($this) {
            self::Ejecutivo => 'ejecutivo',
            self::Obrero => 'obrero',
            self::Empleado => 'empleado',
        };
    }
}
