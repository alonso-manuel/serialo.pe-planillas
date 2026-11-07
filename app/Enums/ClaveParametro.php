<?php

namespace App\Enums;

/**
 * Claves de la tabla `parametros`. Los valores se cargan con fecha de vigencia;
 * el motor de cálculo siempre usa el valor vigente al último día del periodo.
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
}
