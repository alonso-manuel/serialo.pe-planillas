<?php

namespace App\Domain\Afp;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Archivo de texto de la planilla de AFPnet (Guía rápida para Empleadores, act. 20.08.26, ítem 5.1).
 * Ancho fijo: 141 caracteres por línea, una línea por trabajador, todas las AFP en un mismo archivo.
 *
 * | Ítem | Campo                               | Posición | Long | Formato   |
 * | 1    | Número de secuencia                 | 1–5      | 5    | 9(5)      |
 * | 2    | CUSPP                               | 6–17     | 12   | A(12)     |
 * | 3    | Tipo de documento (0 DNI, 1 CE…)    | 18       | 1    | 9(1)      |
 * | 4    | Número de documento                 | 19–38    | 20   | A(20)     |
 * | 5–7  | Apellido paterno, materno, nombres  | 39–98    | 3×20 | A(20)     |
 * | 8    | Relación laboral vigente (S/N)      | 99       | 1    | A(1)      |
 * | 9    | Inicio de la RL en el mes (S/N)     | 100      | 1    | A(1)      |
 * | 10   | Cese de la RL en el mes (S/N)       | 101      | 1    | A(1)      |
 * | 11   | Excepción de aportar (blanco/L/U/J/I/P/O) | 102 | 1    | A(1)      |
 * | 12   | Remuneración asegurable             | 103–111  | 9    | 9(7).9(2) |
 * | 13–15| Aportes voluntarios (con fin, sin fin, empleador) | 112–138 | 3×9 | 9(7).9(2) |
 * | 16   | Tipo de trabajo (N normal)          | 139      | 1    | A(1)      |
 * | 17   | AFP (HA, IN, PR, RI; blanco si no se conoce) | 140–141 | 2 | A(2)  |
 *
 * Montos sin punto decimal, con ceros a la izquierda (600.20 → 000060020).
 * Texto: MAYÚSCULAS sin tildes (se conserva la Ñ), recortado y completado con espacios.
 * El archivo se entrega en Windows-1252 (ANSI) para que cada carácter ocupe 1 byte ⚠ (la guía no indica codificación).
 */
final class GeneradorAfpnet
{
    public const LARGO_LINEA = 141;

    public const FIN_DE_LINEA = "\r\n";

    /**
     * @param  list<array{cuspp: string|null, tipo_documento: string, numero_documento: string, apellido_paterno: string,
     *     apellido_materno: string|null, nombres: string, inicia: bool, cesa: bool, excepcion: string|null,
     *     remuneracion: string, afp: string|null}>  $trabajadores
     * @return string contenido en Windows-1252
     */
    public function generar(array $trabajadores): string
    {
        $lineas = [];
        foreach ($trabajadores as $i => $t) {
            $linea = sprintf('%05d', $i + 1)
                .self::texto($t['cuspp'] ?? '', 12)
                .self::texto($t['tipo_documento'], 1)
                .self::texto($t['numero_documento'], 20)
                .self::texto(self::nombre($t['apellido_paterno']), 20)
                .self::texto(self::nombre($t['apellido_materno'] ?? ''), 20)
                .self::texto(self::nombre($t['nombres']), 20)
                .'S'
                .($t['inicia'] ? 'S' : 'N')
                .($t['cesa'] ? 'S' : 'N')
                .self::texto($t['excepcion'] ?? '', 1)
                .self::monto($t['remuneracion'])
                .self::monto('0')
                .self::monto('0')
                .self::monto('0')
                .'N'
                .self::texto($t['afp'] ?? '', 2);

            if (mb_strlen($linea) !== self::LARGO_LINEA) {
                throw new \LogicException('Línea de AFPnet con largo '.mb_strlen($linea).' (se esperaba '.self::LARGO_LINEA.').');
            }
            $lineas[] = $linea;
        }

        $texto = $lineas === [] ? '' : implode(self::FIN_DE_LINEA, $lineas).self::FIN_DE_LINEA;

        return (string) mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    /** MAYÚSCULAS, sin tildes ni diéresis, conservando la Ñ; solo letras, espacios, apóstrofo y guion. */
    public static function nombre(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = strtr($texto, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C']);

        return (string) preg_replace(['/[^A-ZÑ \'-]/u', '/\s+/u'], ['', ' '], $texto);
    }

    /** Alfanumérico alineado a la izquierda, recortado o completado con espacios. */
    private static function texto(string $valor, int $largo): string
    {
        $valor = mb_substr($valor, 0, $largo);

        return $valor.str_repeat(' ', $largo - mb_strlen($valor));
    }

    /** 9(7).9(2) sin punto: 600.2 → 000060020. */
    private static function monto(string $valor): string
    {
        $centimos = BigDecimal::of($valor)->toScale(2, RoundingMode::HalfUp)->multipliedBy(100)->toBigInteger();
        if ($centimos->isNegative() || $centimos->isGreaterThan(999_999_999)) {
            throw new \InvalidArgumentException("Monto fuera del formato de AFPnet: {$valor}.");
        }

        return str_pad((string) $centimos, 9, '0', STR_PAD_LEFT);
    }
}
