<?php

namespace App\Services\Planilla;

use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\TipoSuspension;
use App\Services\Importacion\LectorHojaCalculo;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asistencia del mes desde Excel/CSV: faltas, licencias sin goce, días de subsidio y horas extra por trabajador.
 * Todo o nada: si una fila tiene error, no se guarda ninguna y se devuelven todos los errores.
 * Cada fila se guarda como en la grilla (recalcula los días laborados; las novedades en montos no se tocan).
 */
class ImportacionAsistencia
{
    public const COLUMNAS = ['numero_documento', 'faltas', 'licencia_sin_goce', 'dias_subsidio', 'tipo_subsidio', 'horas_extra_25', 'horas_extra_35'];

    /** Columnas de la plantilla, en orden (la del nombre es solo de referencia). */
    public const PLANTILLA = ['numero_documento', 'trabajador', 'faltas', 'licencia_sin_goce', 'dias_subsidio', 'tipo_subsidio', 'horas_extra_25', 'horas_extra_35'];

    public function __construct(
        private readonly LectorHojaCalculo $lector,
        private readonly NovedadesPlanilla $novedades,
    ) {}

    /**
     * @return int filas actualizadas
     *
     * @throws ValidationException con todos los errores en `archivo`
     */
    public function importar(Periodo $periodo, string $ruta, string $extension): int
    {
        $filas = $this->lector->leer($ruta, $extension, self::COLUMNAS, 1000);
        $lineas = $periodo->lineas()->with('trabajador')->get()->keyBy(fn (PlanillaLinea $l) => $l->trabajador->numero_documento);
        $subsidios = TipoSuspension::query()->subsidios()->pluck('codigo')->map(fn ($c) => (string) $c)->values()->all();

        /** @var list<string> $errores */
        $errores = [];
        $cambios = [];
        $vistos = [];

        foreach ($filas as ['linea' => $n, 'valores' => $v]) {
            $documento = trim($v['numero_documento']);
            $linea = $lineas->get($documento);

            $error = match (true) {
                ! $linea instanceof PlanillaLinea => "el documento {$documento} no está en la planilla de {$periodo->etiqueta()}.",
                isset($vistos[$documento]) => "el documento {$documento} está repetido (también en la fila {$vistos[$documento]}).",
                default => null,
            };
            if ($error !== null) {
                $errores[] = "Fila {$n}: {$error}";

                continue;
            }
            $vistos[$documento] = $n;

            [$datos, $error] = $this->datosFila($v, $linea, $periodo, $subsidios);
            if ($error !== null || $datos === null) {
                $errores[] = "Fila {$n}: {$error}";

                continue;
            }

            $cambios[] = [$linea, $datos];
        }

        if ($errores !== []) {
            // Un solo mensaje con una fila por línea: Inertia muestra solo el primer mensaje de cada campo.
            $resto = count($errores) - 50;
            throw ValidationException::withMessages([
                'archivo' => implode("\n", array_slice($errores, 0, 50)).($resto > 0 ? "\n… y {$resto} errores más." : ''),
            ]);
        }
        if ($cambios === []) {
            throw ValidationException::withMessages(['archivo' => ['El archivo no tiene filas con datos.']]);
        }

        DB::transaction(function () use ($periodo, $cambios) {
            foreach ($cambios as [$linea, $datos]) {
                $this->novedades->guardarLinea($periodo, $linea, $datos);
            }
        });

        return count($cambios);
    }

    /**
     * Valida una fila y la convierte en los datos de `guardarLinea`.
     *
     * @param  array<string, string>  $v
     * @param  array<int, string>  $subsidios
     * @return array{0: array{dias_no_laborados: int, dias_subsidiados: int, tipo_subsidio: string|null, dias_licencia: int, horas_extra_25: string, horas_extra_35: string, novedades: array<int, string|null>}|null, 1: string|null}
     */
    private function datosFila(array $v, PlanillaLinea $linea, Periodo $periodo, array $subsidios): array
    {
        $dias = [];
        foreach (['faltas', 'licencia_sin_goce', 'dias_subsidio'] as $campo) {
            $valor = trim($v[$campo]) === '' ? '0' : trim($v[$campo]);
            if (preg_match('/^\d{1,2}(\.0+)?$/', $valor) !== 1) {
                return [null, "«{$campo}» debe ser un número entero de días."];
            }
            $dias[$campo] = (int) $valor;
        }

        $disponibles = $this->novedades->diasDelMes($linea, $periodo) - $linea->dias_vacaciones;
        if (array_sum($dias) > $disponibles) {
            return [null, 'faltas, licencias y subsidios suman '.array_sum($dias)." días y el trabajador solo tiene {$disponibles} en el mes."];
        }

        $tipo = trim($v['tipo_subsidio']);
        if ($tipo !== '' && ! in_array($tipo, $subsidios, true)) {
            return [null, '«tipo_subsidio» debe ser uno de: '.implode(', ', $subsidios).'.'];
        }

        $horas = [];
        foreach (['horas_extra_25', 'horas_extra_35'] as $campo) {
            $valor = trim($v[$campo]) === '' ? '0' : str_replace(',', '.', trim($v[$campo]));
            if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $valor) !== 1) {
                return [null, "«{$campo}» debe ser un número de horas con hasta 2 decimales."];
            }
            $horas[$campo] = (string) BigDecimal::of($valor)->toScale(2);
        }

        return [[
            'dias_no_laborados' => $dias['faltas'],
            'dias_licencia' => $dias['licencia_sin_goce'],
            'dias_subsidiados' => $dias['dias_subsidio'],
            'tipo_subsidio' => $tipo === '' ? null : $tipo,
            'horas_extra_25' => $horas['horas_extra_25'],
            'horas_extra_35' => $horas['horas_extra_35'],
            'novedades' => [],
        ], null];
    }

    /** @return array<int, array<int, string|int>> filas de la plantilla con los valores actuales del periodo */
    public function filasPlantilla(Periodo $periodo): array
    {
        return $periodo->lineas()->with('trabajador')->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)
            ->map(fn (PlanillaLinea $l) => [
                $l->trabajador->numero_documento,
                $l->trabajador->nombre_completo,
                $l->dias_no_laborados,
                $l->dias_licencia,
                $l->dias_subsidiados,
                (string) $l->tipo_subsidio,
                (string) $l->horas_extra_25,
                (string) $l->horas_extra_35,
            ])->values()->all();
    }
}
