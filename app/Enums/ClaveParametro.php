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
    case TasaBonificacionExtraordinaria = 'tasa_bonif_extra'; // ej. 0.09 (Ley 30334)
    case FactorGratificacionPequena = 'factor_grati_pequena';  // ej. 0.50 (REMYPE pequeña empresa)
    case FactorGratificacionMicro = 'factor_grati_micro';      // ej. 0.00 (REMYPE microempresa)
    case FactorCtsPequena = 'factor_cts_pequena';              // ej. 0.50 (15 remuneraciones diarias por año)
    case FactorCtsMicro = 'factor_cts_micro';                  // ej. 0.00 (no aplica)
    case DiasVacacionesGeneral = 'dias_vac_general';           // 30 días por año (D.Leg. 713)
    case DiasVacacionesPequena = 'dias_vac_pequena';           // 15 días (REMYPE)
    case DiasVacacionesMicro = 'dias_vac_micro';               // 15 días (REMYPE)

    // Renta de quinta categoría (LIR arts. 46 y 53): deducción y tramos en UIT, tasas como fracción.
    case QuintaDeduccionUit = 'quinta_deduccion_uit';          // 7 UIT
    case QuintaTramo1Uit = 'quinta_tramo1_uit';                // hasta 5 UIT
    case QuintaTramo2Uit = 'quinta_tramo2_uit';                // hasta 20 UIT
    case QuintaTramo3Uit = 'quinta_tramo3_uit';                // hasta 35 UIT
    case QuintaTramo4Uit = 'quinta_tramo4_uit';                // hasta 45 UIT
    case QuintaTasa1 = 'quinta_tasa1';                         // 8 %
    case QuintaTasa2 = 'quinta_tasa2';                         // 14 %
    case QuintaTasa3 = 'quinta_tasa3';                         // 17 %
    case QuintaTasa4 = 'quinta_tasa4';                         // 20 %
    case QuintaTasa5 = 'quinta_tasa5';

    // Indemnización por despido arbitrario, en remuneraciones diarias (D.S. 003-97-TR arts. 38 y 76; REMYPE).
    case IndemnizacionDiasAnioGeneral = 'indem_dias_anio_general';   // 45 (1.5 sueldos por año)
    case IndemnizacionTopeGeneral = 'indem_tope_general';            // 360 (12 sueldos)
    case IndemnizacionDiasMesPlazoFijo = 'indem_dias_mes_plazo_fijo'; // 45 (1.5 sueldos por mes faltante)
    case IndemnizacionDiasAnioPequena = 'indem_dias_anio_pequena';   // 20
    case IndemnizacionTopePequena = 'indem_tope_pequena';            // 120
    case IndemnizacionDiasAnioMicro = 'indem_dias_anio_micro';       // 10
    case IndemnizacionTopeMicro = 'indem_tope_micro';                // 90

    // Renta de cuarta categoría (LIR art. 74)
    case CuartaTasaRetencion = 'cuarta_tasa';                       // 8 %
    case CuartaMontoMinimo = 'cuarta_monto_minimo';                 // se retiene si el recibo supera S/ 1,500                         // 30 % (exceso del último tramo)

    // Régimen agrario (Ley 31110 y D.S. 005-2021-MIDAGRI)
    case TasaEssaludAgrarioMayor = 'tasa_essalud_agrario_mayor';     // 100 o más trabajadores o ventas ≥ 1,700 UIT
    case TasaEssaludAgrarioMenor = 'tasa_essalud_agrario_menor';     // menos de 100 trabajadores y ventas < 1,700 UIT
    case PorcentajeBeta = 'pct_beta';                                // 30 % de la RMV
    case PorcentajeGratificacionAgrario = 'pct_grati_agrario';      // 16.66 % de la RB
    case PorcentajeCtsAgrario = 'pct_cts_agrario';                  // 9.72 % de la RB

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
            self::TasaBonificacionExtraordinaria => 'Bonificación extraordinaria (gratificación)',
            self::FactorGratificacionPequena => 'Gratificación en pequeña empresa',
            self::FactorGratificacionMicro => 'Gratificación en microempresa',
            self::FactorCtsPequena => 'CTS en pequeña empresa',
            self::FactorCtsMicro => 'CTS en microempresa',
            self::DiasVacacionesGeneral => 'Vacaciones en régimen general',
            self::DiasVacacionesPequena => 'Vacaciones en pequeña empresa',
            self::DiasVacacionesMicro => 'Vacaciones en microempresa',
            self::QuintaDeduccionUit => 'Quinta: deducción anual',
            self::QuintaTramo1Uit => 'Quinta: fin del 1.er tramo',
            self::QuintaTramo2Uit => 'Quinta: fin del 2.º tramo',
            self::QuintaTramo3Uit => 'Quinta: fin del 3.er tramo',
            self::QuintaTramo4Uit => 'Quinta: fin del 4.º tramo',
            self::QuintaTasa1 => 'Quinta: tasa del 1.er tramo',
            self::QuintaTasa2 => 'Quinta: tasa del 2.º tramo',
            self::QuintaTasa3 => 'Quinta: tasa del 3.er tramo',
            self::QuintaTasa4 => 'Quinta: tasa del 4.º tramo',
            self::QuintaTasa5 => 'Quinta: tasa del exceso',
            self::IndemnizacionDiasAnioGeneral => 'Indemnización por despido: días por año (general)',
            self::IndemnizacionTopeGeneral => 'Indemnización por despido: tope (general)',
            self::IndemnizacionDiasMesPlazoFijo => 'Indemnización plazo fijo: días por mes faltante',
            self::IndemnizacionDiasAnioPequena => 'Indemnización por despido: días por año (pequeña)',
            self::IndemnizacionTopePequena => 'Indemnización por despido: tope (pequeña)',
            self::IndemnizacionDiasAnioMicro => 'Indemnización por despido: días por año (micro)',
            self::IndemnizacionTopeMicro => 'Indemnización por despido: tope (micro)',
            self::CuartaTasaRetencion => 'Cuarta: tasa de retención',
            self::CuartaMontoMinimo => 'Cuarta: monto desde el que se retiene',
            self::TasaEssaludAgrarioMayor => 'EsSalud agrario (100 o más trabajadores o ventas ≥ 1,700 UIT)',
            self::TasaEssaludAgrarioMenor => 'EsSalud agrario (menos de 100 trabajadores y ventas < 1,700 UIT)',
            self::PorcentajeBeta => 'BETA (bonificación especial por trabajo agrario)',
            self::PorcentajeGratificacionAgrario => 'Agrario: gratificación prorrateada',
            self::PorcentajeCtsAgrario => 'Agrario: CTS prorrateada',
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
            self::TasaBonificacionExtraordinaria => 'Porcentaje de la gratificación que se paga como bonificación (igual al aporte a EsSalud).',
            self::FactorGratificacionPequena => 'Proporción de la gratificación del régimen general que corresponde en pequeña empresa.',
            self::FactorGratificacionMicro => 'Proporción de la gratificación del régimen general que corresponde en microempresa.',
            self::FactorCtsPequena => 'Proporción de la CTS del régimen general que corresponde en pequeña empresa.',
            self::FactorCtsMicro => 'Proporción de la CTS del régimen general que corresponde en microempresa.',
            self::DiasVacacionesGeneral => 'Días calendario de descanso por cada año completo de servicios.',
            self::DiasVacacionesPequena => 'Días calendario de descanso por año en pequeña empresa.',
            self::DiasVacacionesMicro => 'Días calendario de descanso por año en microempresa.',
            self::QuintaDeduccionUit => 'UIT que se restan a la renta anual antes de aplicar los tramos.',
            self::QuintaTramo1Uit, self::QuintaTramo2Uit, self::QuintaTramo3Uit, self::QuintaTramo4Uit => 'Hasta cuántas UIT de renta neta llega el tramo.',
            self::QuintaTasa1, self::QuintaTasa2, self::QuintaTasa3, self::QuintaTasa4 => 'Impuesto sobre la parte de la renta neta dentro del tramo.',
            self::QuintaTasa5 => 'Impuesto sobre la renta neta que excede el último tramo.',
            self::IndemnizacionDiasAnioGeneral, self::IndemnizacionDiasAnioPequena, self::IndemnizacionDiasAnioMicro => 'Remuneraciones diarias por cada año de servicios (fracciones por dozavos y treintavos).',
            self::IndemnizacionTopeGeneral, self::IndemnizacionTopePequena, self::IndemnizacionTopeMicro => 'Máximo de remuneraciones diarias que se pagan.',
            self::IndemnizacionDiasMesPlazoFijo => 'Remuneraciones diarias por cada mes que faltaba para el vencimiento del contrato.',
            self::CuartaTasaRetencion => 'Retención sobre el recibo por honorarios.',
            self::CuartaMontoMinimo => 'Se retiene cuando el recibo supera este monto (salvo constancia de suspensión).',
            self::TasaEssaludAgrarioMayor, self::TasaEssaludAgrarioMenor => 'Aporte del empleador agrario según lo declarado el año anterior; sin base mínima (art. 9 de la Ley 31110).',
            self::PorcentajeBeta => 'Porcentaje de la RMV por mes completo; no remunerativo, proporcional a los días computables.',
            self::PorcentajeGratificacionAgrario => 'Proporción mensual de las gratificaciones sobre la remuneración básica.',
            self::PorcentajeCtsAgrario => 'Proporción mensual de la CTS sobre la remuneración básica.',
        };
    }

    /** 'monto' en soles, 'tasa' (fracción guardada, porcentaje en pantalla), 'dias' (entero) o 'uit' (cantidad de UIT). */
    public function tipo(): string
    {
        return match ($this) {
            self::Rmv, self::Uit, self::AporteSis, self::CuartaMontoMinimo => 'monto',
            self::DiasVacacionesGeneral, self::DiasVacacionesPequena, self::DiasVacacionesMicro,
            self::IndemnizacionDiasAnioGeneral, self::IndemnizacionTopeGeneral, self::IndemnizacionDiasMesPlazoFijo,
            self::IndemnizacionDiasAnioPequena, self::IndemnizacionTopePequena, self::IndemnizacionDiasAnioMicro, self::IndemnizacionTopeMicro => 'dias',
            self::QuintaDeduccionUit, self::QuintaTramo1Uit, self::QuintaTramo2Uit, self::QuintaTramo3Uit, self::QuintaTramo4Uit => 'uit',
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

        if ($this->tipo() === 'dias') {
            return $numero->toScale(0, RoundingMode::HalfUp).' días';
        }

        if ($this->tipo() === 'uit') {
            // 7.00 → «7 UIT», 7.50 → «7.5 UIT»
            return rtrim(rtrim((string) $numero->toScale(2, RoundingMode::HalfUp), '0'), '.').' UIT';
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
