<?php

namespace App\Services\Importacion;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as OpcionesCsv;
use OpenSpout\Reader\CSV\Reader as LectorCsv;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\RowIteratorInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\SheetIteratorInterface;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;

/**
 * Lee la primera hoja de un .xlsx o un .csv (separador `,` o `;`) con encabezado en la fila 1.
 * Devuelve cada fila como texto, con su número de línea real en el archivo.
 * Celdas de Excel: números sin notación científica y fechas como AAAA-MM-DD.
 * Los errores de archivo se lanzan como ValidationException sobre el campo `archivo`.
 */
class LectorHojaCalculo
{
    /**
     * @param  list<string>  $columnas  encabezados obligatorios (en minúsculas)
     * @return list<array{linea: int, valores: array<string, string>}>
     */
    public function leer(string $ruta, string $extension, array $columnas, int $maximoFilas = 500): array
    {
        $lector = $this->lector($ruta, $extension);

        try {
            $lector->open($ruta);
            $encabezado = null;
            $filas = [];
            $linea = 0;

            foreach ($lector->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    $linea++;
                    $valores = array_map(fn ($v) => $this->texto($v), $fila->toArray());

                    if ($encabezado === null) {
                        $encabezado = array_map(fn (string $v) => mb_strtolower(trim($v)), $valores);
                        if (array_diff($columnas, $encabezado) !== []) {
                            throw $this->error('El archivo no tiene las columnas de la plantilla: '.implode(', ', $columnas).'.');
                        }

                        continue;
                    }

                    if (implode('', $valores) === '') {
                        continue;
                    }

                    if (count($filas) >= $maximoFilas) {
                        throw $this->error("El archivo tiene más de {$maximoFilas} filas. Divídelo en varios archivos.");
                    }

                    $filas[] = [
                        'linea' => $linea,
                        'valores' => array_combine(
                            $encabezado,
                            array_pad(array_slice($valores, 0, count($encabezado)), count($encabezado), ''),
                        ),
                    ];
                }

                break; // solo la primera hoja
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw $this->error('No se pudo leer el archivo. Usa la plantilla en formato .xlsx o .csv.');
        } finally {
            $lector->close();
        }

        if ($encabezado === null) {
            throw $this->error('El archivo está vacío.');
        }

        return $filas;
    }

    /** @return ReaderInterface<covariant SheetIteratorInterface<covariant SheetInterface<covariant RowIteratorInterface>>> */
    private function lector(string $ruta, string $extension): ReaderInterface
    {
        if ($extension === 'xlsx') {
            return new LectorXlsx;
        }

        // CSV: separador coma o punto y coma (Excel en español suele usar ';').
        $archivo = fopen($ruta, 'r') ?: throw $this->error('No se pudo abrir el archivo.');
        $primeraLinea = (string) fgets($archivo);
        fclose($archivo);
        $separador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';

        return new LectorCsv(new OpcionesCsv(FIELD_DELIMITER: $separador));
    }

    /** Valor de celda → texto. */
    private function texto(mixed $valor): string
    {
        return match (true) {
            $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
            is_float($valor) => rtrim(rtrim(sprintf('%.10F', $valor), '0'), '.'),
            is_int($valor) => (string) $valor,
            is_bool($valor) => $valor ? '1' : '0',
            is_string($valor) => trim($valor),
            default => '',
        };
    }

    private function error(string $mensaje): ValidationException
    {
        return ValidationException::withMessages(['archivo' => $mensaje]);
    }
}
