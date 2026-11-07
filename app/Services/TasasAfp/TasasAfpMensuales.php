<?php

namespace App\Services\TasasAfp;

use App\Domain\Planilla\Porcentaje;
use App\Enums\EstadoPeriodo;
use App\Models\Afp;
use App\Models\Periodo;
use App\Models\TasaAfp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tasas que la SBS publica cada mes por AFP. El usuario las ve y escribe en porcentaje;
 * se guardan como fracción (4 decimales). Un mes con algún periodo CERRADO (de cualquier
 * empresa) ya no se modifica: esos cálculos deben poder repetirse igual (reglas 3 y 5).
 */
class TasasAfpMensuales
{
    /**
     * Campos de tasa: [etiqueta, mínimo %, máximo %]. Los rangos son de seguridad
     * (atrapan errores de digitación), no límites legales.
     */
    public const TASAS = [
        'aporte_obligatorio' => ['Aporte obligatorio', '0', '20'],
        'prima_seguro' => ['Prima de seguro', '0', '5'],
        'comision_flujo' => ['Comisión flujo', '0', '5'],
        'comision_mixta' => ['Comisión mixta', '0', '5'],
    ];

    /** @return Collection<int, Afp> */
    public function afps(): Collection
    {
        return Afp::query()->where('activo', true)->orderBy('codigo')->get();
    }

    public function mesBloqueado(int $anio, int $mes): bool
    {
        return Periodo::query()
            ->where('estado', EstadoPeriodo::Cerrado)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->exists();
    }

    public function mensajeBloqueo(int $anio, int $mes): string
    {
        return sprintf('Hay periodos cerrados de %02d/%d: sus tasas AFP ya no se pueden modificar.', $mes, $anio);
    }

    /**
     * Estado de cada mes del año: completo (todas las AFP), incompleto o vacío.
     *
     * @return list<array{mes: int, cargadas: int, estado: string, bloqueado: bool}>
     */
    public function resumen(int $anio): array
    {
        $totalAfps = $this->afps()->count();
        $cargadas = TasaAfp::query()
            ->where('anio', $anio)
            ->whereIn('afp_id', $this->afps()->pluck('id'))
            ->selectRaw('mes, count(*) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');
        $bloqueados = Periodo::query()
            ->where('estado', EstadoPeriodo::Cerrado)
            ->where('anio', $anio)
            ->distinct()
            ->pluck('mes')
            ->map(fn ($m) => (int) $m)
            ->all();

        $meses = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $n = (int) ($cargadas[$mes] ?? 0);
            $meses[] = [
                'mes' => $mes,
                'cargadas' => $n,
                'estado' => match (true) {
                    $n === 0 => 'vacio',
                    $n >= $totalAfps => 'completo',
                    default => 'incompleto',
                },
                'bloqueado' => in_array($mes, $bloqueados, true),
            ];
        }

        return $meses;
    }

    /**
     * Una fila por AFP activa con sus tasas del mes en porcentaje (null si no hay).
     *
     * @return array<int, array<string, mixed>>
     */
    public function grilla(int $anio, int $mes): array
    {
        $tasas = TasaAfp::query()->where(['anio' => $anio, 'mes' => $mes])->get()->keyBy('afp_id');

        return $this->afps()->map(function (Afp $afp) use ($tasas) {
            /** @var TasaAfp|null $tasa */
            $tasa = $tasas->get($afp->id);
            $fila = ['afp_id' => $afp->id, 'codigo' => $afp->codigo, 'nombre' => $afp->nombre];

            foreach (array_keys(self::TASAS) as $campo) {
                $fila[$campo] = $tasa ? Porcentaje::desdeFraccion((string) $tasa->getAttribute($campo)) : null;
            }
            $fila['remuneracion_maxima_asegurable'] = $tasa?->remuneracion_maxima_asegurable;

            return $fila;
        })->values()->all();
    }

    /** Error de validación de una tasa en porcentaje, o null si es válida. */
    public function errorTasa(string $campo, string $valor): ?string
    {
        [$etiqueta, $min, $max] = self::TASAS[$campo];

        return match (true) {
            preg_match('/^\d+(\.\d+)?$/', $valor) !== 1 => "{$etiqueta} no es un número.",
            ! Porcentaje::enRango($valor, $min, $max) => "{$etiqueta} fuera de rango ({$min} % a {$max} %).",
            ! Porcentaje::esValido($valor) => "{$etiqueta} debe tener como máximo 2 decimales.",
            default => null,
        };
    }

    public function errorRma(string $valor): ?string
    {
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $valor) !== 1 || preg_match('/^0+(\.0+)?$/', $valor) === 1) {
            return 'La RMA debe ser un monto mayor que cero con hasta 2 decimales.';
        }

        return null;
    }

    /**
     * Guarda (crea o actualiza) las tasas de varias AFP y meses en una transacción.
     *
     * @param  array<int, array{afp_id: int, anio: int, mes: int, aporte_obligatorio: string, prima_seguro: string, comision_flujo: string, comision_mixta: string, remuneracion_maxima_asegurable: string}>  $filas  tasas en porcentaje
     */
    public function guardar(array $filas): int
    {
        return DB::transaction(function () use ($filas) {
            foreach ($filas as $fila) {
                $valores = ['remuneracion_maxima_asegurable' => $fila['remuneracion_maxima_asegurable']];
                foreach (array_keys(self::TASAS) as $campo) {
                    $valores[$campo] = Porcentaje::aFraccion($fila[$campo]);
                }

                TasaAfp::updateOrCreate(
                    ['afp_id' => $fila['afp_id'], 'anio' => $fila['anio'], 'mes' => $fila['mes']],
                    $valores,
                );
            }

            return count($filas);
        });
    }
}
