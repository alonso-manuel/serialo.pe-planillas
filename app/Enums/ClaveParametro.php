<?php

namespace App\Enums;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Claves de la tabla `parametros`. Los valores se cargan con fecha de vigencia;
 * el motor de cálculo siempre usa el valor vigente al último día del periodo.
 * Las tasas se guardan como fracción (0.0900) y se muestran/ingresan en porcentaje (9 %).
 */
enum ClaveParametro: string
{
    case Rmv = 'rmv';                                   // Remuneración Mínima Vital (S/)
    case Uit = 'uit';                                   // Unidad Impositiva Tributaria (S/)
    case TasaEssalud = 'tasa_essalud';                  // ej. 0.09
    case AporteSis = 'aporte_sis';                      // monto fijo mensual (S/) microempresa
    case TasaOnp = 'tasa_onp';                          // ej. 0.13
    case PorcentajeAsignacionFamiliar = 'pct_asig_fam'; // ej. 0.10 de la RMV
    case SobretasaHoraExtra25 = 'sobretasa_he_25';      // ej. 0.25 (primeras 2 horas)
    case SobretasaHoraExtra35 = 'sobretasa_he_35';      // ej. 0.35 (horas siguientes)

    public function etiqueta(): string
    {
        return match ($this) {
            self::Rmv => 'Remuneración Mínima Vital (RMV)',
            self::Uit => 'Unidad Impositiva Tributaria (UIT)',
            self::TasaEssalud => 'Aporte a EsSalud',
            self::AporteSis => 'Aporte al SIS (microempresa)',
            self::TasaOnp => 'Aporte a la ONP',
            self::PorcentajeAsignacionFamiliar => 'Asignación familiar',
            self::SobretasaHoraExtra25 => 'Sobretasa horas extra (primeras 2 h)',
            self::SobretasaHoraExtra35 => 'Sobretasa horas extra (desde la 3.ª h)',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Rmv => 'Sueldo mínimo mensual. Base mínima de EsSalud y referencia de la asignación familiar.',
            self::Uit => 'Referencia para la renta de quinta categoría y otros topes.',
            self::TasaEssalud => 'Porcentaje sobre la remuneración, a cargo del empleador.',
            self::AporteSis => 'Monto fijo mensual por trabajador afiliado al SIS.',
            self::TasaOnp => 'Porcentaje sobre la remuneración, a cargo del trabajador.',
            self::PorcentajeAsignacionFamiliar => 'Porcentaje de la RMV para trabajadores con hijos.',
            self::SobretasaHoraExtra25 => 'Recargo sobre el valor hora para las dos primeras horas extra.',
            self::SobretasaHoraExtra35 => 'Recargo sobre el valor hora a partir de la tercera hora extra.',
        };
    }

    /** 'monto' en soles o 'tasa' (fracción guardada, porcentaje en pantalla). */
    public function tipo(): string
    {
        return match ($this) {
            self::Rmv, self::Uit, self::AporteSis => 'monto',
            default => 'tasa',
        };
    }

    /** Valor guardado → texto para mostrar: "S/ 1,130.00" o "9.00 %". */
    public function formatear(string $valor): string
    {
        $numero = BigDecimal::of($valor);

        if ($this->tipo() === 'tasa') {
            return $numero->multipliedBy(100)->toScale(2, RoundingMode::HalfUp).' %';
        }

        $texto = (string) $numero->toScale(2, RoundingMode::HalfUp);
        [$entero, $decimales] = explode('.', $texto);

        return 'S/ '.number_format((int) $entero).'.'.$decimales;
    }

    /** Valor ingresado por el usuario (soles o porcentaje) → valor a guardar (4 decimales). */
    public function normalizar(string $ingresado): string
    {
        $numero = BigDecimal::of($ingresado);

        if ($this->tipo() === 'tasa') {
            $numero = $numero->dividedBy(100, 4, RoundingMode::Unnecessary);
        }

        return (string) $numero->toScale(4, RoundingMode::Unnecessary);
    }
}
