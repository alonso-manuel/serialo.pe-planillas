<?php

namespace App\Services\Trabajadores;

use App\Domain\Licencia\Recurso;
use App\Enums\ClaveParametro;
use App\Enums\TipoDocumento;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Trabajador;
use App\Services\Importacion\LectorHojaCalculo;
use App\Services\Licencia\LicenciaActual;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Carga masiva de trabajadores (solo altas) en la empresa activa:
 * 1) `previsualizar()`: lee la plantilla, traduce cada fila a los datos de la ficha y la valida
 *    con las MISMAS reglas que el formulario (ReglasTrabajador).
 * 2) `confirmar()`: crea solo las filas válidas, en la misma empresa de la vista previa.
 */
class ImportadorTrabajadores
{
    public const COLUMNAS = [
        'tipo_documento', 'numero_documento', 'apellido_paterno', 'apellido_materno', 'nombres',
        'fecha_nacimiento', 'sexo', 'fecha_ingreso', 'cargo', 'establecimiento', 'tipo_contrato',
        'remuneracion_basica', 'asignacion_familiar', 'sistema_pension', 'afp', 'cuspp', 'comision_afp',
        'seguro_salud', 'cuenta_sueldo', 'cuenta_cts', 'email', 'telefono',
    ];

    public const MAXIMO_FILAS = 500;

    private const CLAVE_SESION = 'importacion_trabajadores';

    public function __construct(
        private readonly LectorHojaCalculo $lector,
        private readonly Session $session,
        private readonly LicenciaActual $licencia,
    ) {}

    /** @return array{validas: int, con_errores: int} */
    public function previsualizar(Empresa $empresa, string $ruta, string $extension, string $nombreArchivo): array
    {
        $filas = $this->validar($empresa, $this->lector->leer($ruta, $extension, self::COLUMNAS, self::MAXIMO_FILAS));

        $this->session->put(self::CLAVE_SESION, [
            'empresa_id' => $empresa->id,
            'archivo' => $nombreArchivo,
            'filas' => $filas,
        ]);

        return $this->resumen($filas);
    }

    /**
     * @return array{archivo: string, empresa_id: int, validas: int, con_errores: int, filas: list<array<string, mixed>>}|null
     */
    public function pendiente(): ?array
    {
        /** @var array{empresa_id: int, archivo: string, filas: list<array<string, mixed>>}|null $datos */
        $datos = $this->session->get(self::CLAVE_SESION);

        if ($datos === null) {
            return null;
        }

        return [
            'archivo' => $datos['archivo'],
            'empresa_id' => $datos['empresa_id'],
            ...$this->resumen($datos['filas']),
            'filas' => array_map(fn (array $f) => collect($f)->except('datos')->all(), $datos['filas']),
        ];
    }

    /**
     * Crea las filas válidas. Si la empresa activa cambió desde la vista previa, no importa nada.
     *
     * @return array{creados: int, omitidos: int}
     */
    public function confirmar(Empresa $empresaActiva): array
    {
        /** @var array{empresa_id: int, filas: list<array<string, mixed>>}|null $datos */
        $datos = $this->session->get(self::CLAVE_SESION);

        if ($datos === null) {
            return ['creados' => 0, 'omitidos' => 0];
        }

        if ($datos['empresa_id'] !== $empresaActiva->id) {
            throw new \DomainException('La empresa activa cambió desde la vista previa. Vuelve a subir el archivo.');
        }

        $validas = array_values(array_filter($datos['filas'], fn (array $f) => $f['errores'] === []));

        // Antes de consumir la vista previa: si no alcanza el cupo, se puede corregir el archivo y reintentar.
        $this->licencia->asegurarCupo(Recurso::Trabajadores, count($validas));
        $this->session->forget(self::CLAVE_SESION);
        $creados = 0;

        DB::transaction(function () use ($validas, $empresaActiva, &$creados) {
            foreach ($validas as $fila) {
                /** @var array<string, mixed> $guardar */
                $guardar = $fila['datos'];

                // Pudo registrarse a mano entre la vista previa y la confirmación.
                $existe = Trabajador::withTrashed()->deEmpresa($empresaActiva->id)
                    ->where('tipo_documento', $guardar['tipo_documento'])
                    ->where('numero_documento', $guardar['numero_documento'])
                    ->exists();

                if (! $existe) {
                    $empresaActiva->trabajadores()->create($guardar);
                    $creados++;
                }
            }
        });

        return ['creados' => $creados, 'omitidos' => count($datos['filas']) - $creados];
    }

    public function cancelar(): void
    {
        $this->session->forget(self::CLAVE_SESION);
    }

    /**
     * @param  list<array{linea: int, valores: array<string, string>}>  $filas
     * @return list<array<string, mixed>>
     */
    private function validar(Empresa $empresa, array $filas): array
    {
        $establecimientos = $empresa->establecimientos()->pluck('id', 'codigo');
        $principal = $establecimientos['0000'] ?? $establecimientos->first();
        $afps = Afp::query()->where('activo', true)->get();
        $rmv = $this->rmvVigente();
        $vistos = [];
        $resultado = [];

        foreach ($filas as ['linea' => $linea, 'valores' => $v]) {
            $errores = [];
            $avisos = [];
            $marcados = []; // campos con error de traducción: no se repite el error del validador

            $tipo = $this->tipoDocumento($v['tipo_documento']);
            $numero = mb_strtoupper(str_replace(' ', '', $v['numero_documento']));
            if ($tipo === TipoDocumento::Dni->value && preg_match('/^\d{6,7}$/', $numero) === 1) {
                $numero = str_pad($numero, 8, '0', STR_PAD_LEFT);
                $avisos[] = 'El DNI tenía menos de 8 dígitos (Excel quita los ceros a la izquierda): se completó con ceros.';
            }

            $datos = [
                'tipo_documento' => $tipo,
                'numero_documento' => $numero,
                'apellido_paterno' => $this->texto($v['apellido_paterno']),
                'apellido_materno' => $this->texto($v['apellido_materno']),
                'nombres' => $this->texto($v['nombres']),
                'fecha_nacimiento' => $this->fecha($v['fecha_nacimiento'], 'Fecha de nacimiento', 'fecha_nacimiento', $errores, $marcados),
                'sexo' => $this->sexo($v['sexo']),
                'email' => $this->texto($v['email']),
                'telefono' => $this->texto($v['telefono']),
                'establecimiento_id' => null,
                'cargo' => $this->texto($v['cargo']),
                'fecha_ingreso' => $this->fecha($v['fecha_ingreso'], 'Fecha de ingreso', 'fecha_ingreso', $errores, $marcados),
                'tipo_contrato' => $this->clave($v['tipo_contrato'], [
                    'indeterminado' => 'indeterminado', 'plazo_indeterminado' => 'indeterminado',
                    'plazo_fijo' => 'plazo_fijo', 'fijo' => 'plazo_fijo',
                    'tiempo_parcial' => 'tiempo_parcial', 'parcial' => 'tiempo_parcial',
                ]) ?? ($v['tipo_contrato'] === '' ? 'indeterminado' : $v['tipo_contrato']),
                'remuneracion_basica' => $this->monto($v['remuneracion_basica']),
                'asignacion_familiar' => in_array($this->normal($v['asignacion_familiar']), ['si', 's', '1', 'x', 'true', 'verdadero'], true),
                'sistema_pension' => $this->clave($v['sistema_pension'], ['onp' => 'onp', 'snp' => 'onp', 'afp' => 'afp', 'spp' => 'afp', 'ninguno' => 'ninguno']) ?? $v['sistema_pension'],
                'afp_id' => null,
                'cuspp' => $this->texto(mb_strtoupper($v['cuspp'])),
                'tipo_comision_afp' => $this->clave($v['comision_afp'], ['flujo' => 'flujo', 'mixta' => 'mixta']),
                'seguro_salud' => $this->clave($v['seguro_salud'], ['essalud' => 'essalud', 'sis' => 'sis']) ?? ($v['seguro_salud'] === '' ? $empresa->seguro_salud->value : $v['seguro_salud']),
                'cuenta_sueldo' => $this->texto($v['cuenta_sueldo']),
                'cuenta_cts' => $this->texto($v['cuenta_cts']),
            ];

            // Establecimiento por código (vacío = principal)
            $codigo = trim($v['establecimiento']);
            if ($codigo === '') {
                $datos['establecimiento_id'] = $principal;
            } else {
                $codigo = str_pad($codigo, 4, '0', STR_PAD_LEFT);
                $datos['establecimiento_id'] = $establecimientos[$codigo] ?? null;
                if ($datos['establecimiento_id'] === null) {
                    $errores[] = "El establecimiento «{$codigo}» no existe en esta empresa.";
                    $marcados[] = 'establecimiento_id';
                }
            }

            // AFP por código o nombre
            if (trim($v['afp']) !== '') {
                $buscada = $this->normal($v['afp']);
                $afp = $afps->first(fn (Afp $a) => $this->normal($a->codigo) === $buscada || str_contains($this->normal($a->nombre), $buscada));
                if ($afp === null) {
                    $errores[] = 'AFP «'.mb_strtoupper(trim($v['afp'])).'» no reconocida.';
                    $marcados[] = 'afp_id';
                } else {
                    $datos['afp_id'] = $afp->id;
                }
            }

            // Filas de ejemplo de la plantilla que no se borraron
            if (str_contains(mb_strtoupper($v['nombres']), 'BORRAR ESTA FILA')) {
                $errores[] = 'Es una fila de ejemplo de la plantilla: bórrala antes de subir el archivo.';
            }

            // Repetido dentro del archivo
            $clave = "{$datos['tipo_documento']}|{$numero}";
            if ($numero !== '' && isset($vistos[$clave])) {
                $errores[] = "Documento repetido: ya aparece en la línea {$vistos[$clave]}.";
                $marcados[] = 'numero_documento';
            } elseif ($numero !== '') {
                $vistos[$clave] = $linea;
            }

            // Mismas reglas que la ficha
            $validador = Validator::make($datos, ReglasTrabajador::reglas($empresa, $datos), ReglasTrabajador::mensajes(), ReglasTrabajador::atributos());
            $validador->after(fn ($val) => ReglasTrabajador::validarExtras($val, $empresa, $datos));
            foreach ($validador->errors()->messages() as $campo => $mensajes) {
                if (! in_array($campo, $marcados, true)) {
                    array_push($errores, ...$mensajes);
                }
            }

            if ($errores === []) {
                $avisos = [...$avisos, ...$this->avisos($datos, $rmv)];
            }

            $resultado[] = [
                'linea' => $linea,
                'documento' => (TipoDocumento::tryFrom((string) $datos['tipo_documento'])?->abreviatura() ?? $v['tipo_documento'])." {$numero}",
                'nombre' => trim("{$datos['apellido_paterno']} {$datos['apellido_materno']}, {$datos['nombres']}", ' ,'),
                'cargo' => $datos['cargo'],
                'remuneracion_basica' => $datos['remuneracion_basica'],
                'pension' => mb_strtoupper((string) $datos['sistema_pension']),
                'errores' => array_values(array_unique($errores)),
                'avisos' => $avisos,
                'datos' => $errores === [] ? ReglasTrabajador::paraGuardar($validador->validated(), $datos['asignacion_familiar']) : null,
            ];
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<string>
     */
    private function avisos(array $datos, ?string $rmv): array
    {
        $avisos = [];

        if ($rmv !== null && BigDecimal::of((string) $datos['remuneracion_basica'])->isLessThan($rmv)) {
            $avisos[] = 'Básico menor que la RMV vigente: revisa si es tiempo parcial.';
        }

        if ($datos['fecha_nacimiento'] !== null && CarbonImmutable::parse((string) $datos['fecha_nacimiento'])->age < 18) {
            $avisos[] = 'Es menor de edad: requiere autorización de trabajo.';
        }

        return $avisos;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{validas: int, con_errores: int}
     */
    private function resumen(array $filas): array
    {
        $validas = count(array_filter($filas, fn (array $f) => $f['errores'] === []));

        return ['validas' => $validas, 'con_errores' => count($filas) - $validas];
    }

    private function rmvVigente(): ?string
    {
        try {
            return Parametro::vigente(ClaveParametro::Rmv, CarbonImmutable::today()->toDateString());
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function tipoDocumento(string $valor): ?string
    {
        return $this->clave($valor, [
            'dni' => '01', '01' => '01', '1' => '01',
            'ce' => '04', 'carne_de_extranjeria' => '04', 'carne_extranjeria' => '04', '04' => '04', '4' => '04',
            'pas' => '07', 'pasaporte' => '07', '07' => '07', '7' => '07',
        ]) ?? ($valor === '' ? null : $valor);
    }

    private function sexo(string $valor): ?string
    {
        return $this->clave($valor, ['m' => 'M', 'masculino' => 'M', 'h' => 'M', 'f' => 'F', 'femenino' => 'F'])
            ?? ($valor === '' ? null : $valor);
    }

    /**
     * Fecha de Excel (AAAA-MM-DD) o dd/mm/aaaa → AAAA-MM-DD. Si no se entiende, la marca con error.
     *
     * @param  list<string>  $errores
     * @param  list<string>  $marcados
     */
    private function fecha(string $valor, string $etiqueta, string $campo, array &$errores, array &$marcados): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        // Número de serie de Excel (días desde el 30/12/1899) cuando la celda no tiene formato de fecha.
        if (preg_match('/^(\d{5})(\.\d+)?$/', $valor, $m) === 1 && (int) $m[1] >= 10000 && (int) $m[1] <= 80000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $m[1])->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j/n/Y'] as $formato) {
            try {
                $fecha = CarbonImmutable::createFromFormat("!{$formato}", $valor);
            } catch (\Throwable) {
                continue;
            }

            // Se descarta si PHP "desbordó" la fecha (31/02 → 03/03).
            if ($fecha !== null && $fecha->format($formato) === $valor) {
                return $fecha->toDateString();
            }
        }

        $errores[] = "{$etiqueta} no válida: usa dd/mm/aaaa.";
        $marcados[] = $campo;

        return null;
    }

    /** "1,800.50" / "1800,50" / "1800.5" → "1800.50"; si no es número, se deja tal cual para que falle la regla. */
    private function monto(string $valor): string
    {
        $valor = str_replace([' ', 'S/', 's/'], '', trim($valor));

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace(',', '', $valor);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $valor) === 1) {
            $valor = str_replace(',', '.', $valor);
        }

        if (preg_match('/^\d+(\.\d+)?$/', $valor) === 1 && preg_match('/^\d+(\.\d{1,2})?$/', $valor) === 1) {
            return (string) BigDecimal::of($valor)->toScale(2);
        }

        return $valor;
    }

    /** @param array<int|string, string> $mapa  (PHP convierte claves como "01" o "1" a enteros) */
    private function clave(string $valor, array $mapa): ?string
    {
        return $mapa[$this->normal($valor)] ?? null;
    }

    /** minúsculas, sin tildes, espacios → guion bajo. */
    private function normal(string $valor): string
    {
        return str_replace([' ', '-'], '_', mb_strtolower(Str::ascii(trim($valor))));
    }

    private function texto(string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? '');

        return $valor === '' ? null : $valor;
    }
}
