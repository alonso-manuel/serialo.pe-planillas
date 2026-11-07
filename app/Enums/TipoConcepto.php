<?php

namespace App\Enums;

enum TipoConcepto: string
{
    case Ingreso = 'ingreso';                     // 0100-0500, 0900, 1000
    case DescuentoTrabajador = 'descuento';       // 0700
    case AporteTrabajador = 'aporte_trab';        // 0600
    case AporteEmpleador = 'aporte_empl';         // 0800

    /** Nombre del grupo en la pantalla de conceptos. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingresos',
            self::DescuentoTrabajador => 'Descuentos',
            self::AporteTrabajador => 'Aportes del trabajador',
            self::AporteEmpleador => 'Aportes del empleador',
        };
    }

    /**
     * Tipo según la estructura de códigos de la tabla 22 (primeros dos dígitos).
     * Null si el código no corresponde a ningún grupo de la tabla.
     */
    public static function desdeCodigo(string $codigo): ?self
    {
        if (preg_match('/^\d{4}$/', $codigo) !== 1) {
            return null;
        }

        return match (substr($codigo, 0, 2)) {
            '01', '02', '03', '04', '05', '09', '10' => self::Ingreso,
            '06' => self::AporteTrabajador,
            '07' => self::DescuentoTrabajador,
            '08' => self::AporteEmpleador,
            default => null,
        };
    }

    /** Solo los ingresos usan afectaciones (entran o no a la base de cada aporte). */
    public function usaAfectaciones(): bool
    {
        return $this === self::Ingreso;
    }
}
