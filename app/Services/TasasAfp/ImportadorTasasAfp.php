<?php

namespace App\Services\TasasAfp;

use App\Services\Importacion\LectorHojaCalculo;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Session\Session;

/**
 * Importa tasas AFP desde la plantilla (CSV o XLSX) en dos pasos:
 * 1) `previsualizar()`: lee, valida fila por fila y guarda el resultado en sesión.
 * 2) `confirmar()`: guarda solo las filas válidas (revalidando el bloqueo por periodo cerrado).
 */
class ImportadorTasasAfp
{
    public const COLUMNAS = ['anio', 'mes', 'afp', 'aporte', 'prima', 'comision_flujo', 'comision_mixta', 'rma'];

    private const CLAVE_SESION = 'importacion_tasas_afp';

    private const MAXIMO_FILAS = 500;

    /** Columna de la plantilla → campo de la tabla. */
    private const CAMPOS = [
        'aporte' => 'aporte_obligatorio',
        'prima' => 'prima_seguro',
        'comision_flujo' => 'comision_flujo',
        'comision_mixta' => 'comision_mixta',
    ];

    public function __construct(
        private readonly TasasAfpMensuales $tasas,
        private readonly Session $session,
        private readonly LectorHojaCalculo $lector,
    ) {}

    /**
     * @return array{validas: int, con_errores: int, primera: array{anio: int, mes: int}|null}
     */
    public function previsualizar(string $ruta, string $extension, string $nombreArchivo): array
    {
        $filas = $this->validar($this->lector->leer($ruta, $extension, self::COLUMNAS, self::MAXIMO_FILAS));

        $this->session->put(self::CLAVE_SESION, ['archivo' => $nombreArchivo, 'filas' => $filas]);

        $primeraValida = collect($filas)->first(fn (array $f) => $f['errores'] === []);

        return [
            'validas' => collect($filas)->where('errores', [])->count(),
            'con_errores' => collect($filas)->where('errores', '!=', [])->count(),
            'primera' => $primeraValida ? ['anio' => $primeraValida['anio'], 'mes' => $primeraValida['mes']] : null,
        ];
    }

    /**
     * Vista previa guardada en sesión, para la pantalla.
     *
     * @return array{archivo: string, validas: int, con_errores: int, filas: array<int, array<string, mixed>>}|null
     */
    public function pendiente(): ?array
    {
        /** @var array{archivo: string, filas: list<array<string, mixed>>}|null $datos */
        $datos = $this->session->get(self::CLAVE_SESION);

        if ($datos === null) {
            return null;
        }

        $filas = collect($datos['filas']);

        return [
            'archivo' => $datos['archivo'],
            'validas' => $filas->where('errores', [])->count(),
            'con_errores' => $filas->where('errores', '!=', [])->count(),
            'filas' => $filas->map(fn (array $f) => collect($f)->except('afp_id')->all())->values()->all(),
        ];
    }

    /**
     * Guarda las filas válidas de la vista previa. Devuelve cuántas se guardaron.
     *
     * @return array{guardadas: int, primera: array{anio: int, mes: int}|null}
     */
    public function confirmar(): array
    {
        /** @var array{filas: list<array<string, mixed>>}|null $datos */
        $datos = $this->session->pull(self::CLAVE_SESION);

        $validas = collect($datos['filas'] ?? [])
            ->where('errores', [])
            // El estado pudo cambiar entre la vista previa y la confirmación.
            ->reject(fn (array $f) => $this->tasas->mesBloqueado($f['anio'], $f['mes']))
            ->values();

        $guardadas = $this->tasas->guardar($validas->map(fn (array $f) => [
            'afp_id' => (int) $f['afp_id'],
            'anio' => (int) $f['anio'],
            'mes' => (int) $f['mes'],
            'aporte_obligatorio' => (string) $f['aporte_obligatorio'],
            'prima_seguro' => (string) $f['prima_seguro'],
            'comision_flujo' => (string) $f['comision_flujo'],
            'comision_mixta' => (string) $f['comision_mixta'],
            'remuneracion_maxima_asegurable' => (string) $f['remuneracion_maxima_asegurable'],
        ])->values()->all());

        $primera = $validas->first();

        return [
            'guardadas' => $guardadas,
            'primera' => $primera ? ['anio' => $primera['anio'], 'mes' => $primera['mes']] : null,
        ];
    }

    public function cancelar(): void
    {
        $this->session->forget(self::CLAVE_SESION);
    }

    /**
     * @param  list<array{linea: int, valores: array<string, string>}>  $filas
     * @return list<array<string, mixed>>
     */
    private function validar(array $filas): array
    {
        $afps = $this->tasas->afps()->keyBy('codigo');
        $vistas = [];
        $resultado = [];

        foreach ($filas as ['linea' => $linea, 'valores' => $fila]) {
            $errores = [];
            $avisos = [];

            $anio = filter_var($fila['anio'], FILTER_VALIDATE_INT);
            $mes = filter_var($fila['mes'], FILTER_VALIDATE_INT);
            $fechaValida = $anio !== false && $mes !== false && $anio >= 2000 && $anio <= 2100 && $mes >= 1 && $mes <= 12;
            if (! $fechaValida) {
                $errores[] = 'Año o mes no válido.';
            }

            $codigo = mb_strtoupper($fila['afp']);
            $afp = $afps->get($codigo);
            if ($afp === null) {
                $errores[] = "AFP «{$codigo}» no reconocida (usa ".implode(', ', $afps->keys()->slice(0, -1)->all()).' o '.$afps->keys()->last().').';
            }

            // Decimales con coma ("1,37") → punto.
            $valores = [];
            foreach (self::CAMPOS as $columna => $campo) {
                $valores[$campo] = str_replace(',', '.', $fila[$columna]);
            }
            $rma = str_replace(',', '.', $fila['rma']);

            // Tasas escritas como fracción (0.10 en vez de 10): el aporte obligatorio ronda el 10 %.
            if (preg_match('/^\d+(\.\d+)?$/', $valores['aporte_obligatorio']) === 1
                && BigDecimal::of($valores['aporte_obligatorio'])->isLessThan(1)
                && ! BigDecimal::of($valores['aporte_obligatorio'])->isZero()) {
                foreach ($valores as $campo => $valor) {
                    if (preg_match('/^\d+(\.\d+)?$/', $valor) === 1) {
                        $valores[$campo] = (string) BigDecimal::of($valor)->multipliedBy(100)->strippedOfTrailingZeros();
                    }
                }
                $avisos[] = 'Las tasas venían como fracción (0.10); se interpretaron como porcentaje (10 %).';
            }

            foreach ($valores as $campo => $valor) {
                $error = $valor === ''
                    ? TasasAfpMensuales::TASAS[$campo][0].' está vacío.'
                    : $this->tasas->errorTasa($campo, $valor);
                if ($error !== null) {
                    $errores[] = $error;
                }
            }

            if ($rma === '') {
                $errores[] = 'Falta la RMA.';
            } elseif (($error = $this->tasas->errorRma($rma)) !== null) {
                $errores[] = $error;
            }

            if ($fechaValida && $this->tasas->mesBloqueado((int) $anio, (int) $mes)) {
                $errores[] = sprintf('Hay periodos cerrados de %02d/%d: sus tasas ya no se pueden modificar.', $mes, $anio);
            }

            if ($fechaValida && $afp !== null) {
                $clave = "{$codigo}-{$anio}-{$mes}";
                if (isset($vistas[$clave])) {
                    $errores[] = sprintf('Fila repetida: %s %02d/%d ya aparece en la línea %d.', $codigo, $mes, $anio, $vistas[$clave]);
                } else {
                    $vistas[$clave] = $linea;
                }
            }

            $resultado[] = [
                'linea' => $linea,
                'anio' => $fechaValida ? (int) $anio : null,
                'mes' => $fechaValida ? (int) $mes : null,
                'afp' => $codigo,
                'afp_id' => $afp?->id,
                ...$valores,
                'remuneracion_maxima_asegurable' => $rma,
                'errores' => $errores,
                'avisos' => $avisos,
            ];
        }

        return $resultado;
    }
}
