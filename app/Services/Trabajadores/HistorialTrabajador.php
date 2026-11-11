<?php

namespace App\Services\Trabajadores;

use App\Enums\EstadoPeriodo;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Models\Afp;
use App\Models\CentroCosto;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\TrabajadorHistorial;
use App\Models\User;
use BackedEnum;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Historial de condiciones del trabajador con vigencia mensual (regla 3).
 *
 * - La ficha (`trabajadores`) guarda el ÚLTIMO valor, aunque rija en el futuro.
 * - El valor en una fecha = `valor_anterior` del primer cambio posterior a esa fecha;
 *   si no hay cambios posteriores, el de la ficha.
 * - Por grupo, los cambios solo se agregan hacia adelante y solo se anula el último:
 *   así la cadena antes → después siempre es coherente.
 * - Ningún cambio puede alterar un mes con periodo CERRADO (regla 5).
 */
class HistorialTrabajador
{
    /** Grupo => [etiqueta, campos]. Los campos de un grupo cambian juntos. */
    public const GRUPOS = [
        'remuneracion' => ['Remuneración básica', ['remuneracion_basica']],
        'cargo' => ['Cargo', ['cargo']],
        'asignacion_familiar' => ['Asignación familiar', ['asignacion_familiar']],
        'pension' => ['Sistema de pensiones', ['sistema_pension', 'afp_id', 'tipo_comision_afp', 'cuspp']],
        'seguro_salud' => ['Seguro de salud', ['seguro_salud']],
        'tipo_contrato' => ['Tipo de contrato', ['tipo_contrato']],
        'centro_costo' => ['Centro de costo', ['centro_costo_id']],
    ];

    /** @return list<string> todos los campos con historial */
    public static function campos(): array
    {
        return array_merge(...array_map(fn (array $g) => $g[1], array_values(self::GRUPOS)));
    }

    /**
     * Valores (texto normalizado) de los campos con historial vigentes en `$fecha`.
     *
     * @return array<string, string|null>
     */
    public function valoresAl(Trabajador $trabajador, CarbonImmutable $fecha): array
    {
        $cambios = $this->cambios($trabajador);
        $valores = [];

        foreach (self::campos() as $campo) {
            $siguiente = $cambios
                ->where('campo', $campo)
                ->filter(fn (TrabajadorHistorial $h) => $h->vigente_desde->gt($fecha))
                ->sortBy(fn (TrabajadorHistorial $h) => $h->vigente_desde->timestamp)
                ->first();

            $valores[$campo] = $siguiente !== null
                ? $siguiente->valor_anterior
                : $this->valorActual($trabajador, $campo);
        }

        return $valores;
    }

    /** Copia en memoria del trabajador con los valores vigentes en `$fecha` (no se guarda). */
    public function vistaAl(Trabajador $trabajador, CarbonImmutable $fecha): Trabajador
    {
        $vista = clone $trabajador;
        $vista->forceFill($this->valoresAl($trabajador, $fecha));

        return $vista;
    }

    /**
     * Registra un cambio del grupo con vigencia desde el día 1 de `$mes` (AAAA-MM)
     * y actualiza la ficha con el nuevo valor.
     *
     * @param  array<string, string|null>  $nuevos  campo => valor normalizado
     */
    public function registrar(Trabajador $trabajador, string $grupo, string $mes, array $nuevos, ?User $usuario): void
    {
        $desde = CarbonImmutable::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay();
        $etiqueta = self::GRUPOS[$grupo][0];
        $ingreso = $trabajador->fecha_ingreso->startOfMonth();

        if ($desde->lt($ingreso)) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'El cambio no puede regir antes del mes de ingreso ('.$ingreso->format('m/Y').').',
            ]);
        }

        if ($trabajador->fecha_cese !== null && $desde->gt($trabajador->fecha_cese->startOfMonth())) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'El trabajador cesó el '.$trabajador->fecha_cese->format('d/m/Y').': no se registran cambios posteriores.',
            ]);
        }

        $ultimo = $this->ultimoCambio($trabajador, $grupo);
        if ($ultimo !== null && $desde->lte($ultimo->vigente_desde)) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'Ya hay un cambio de '.mb_strtolower($etiqueta).' desde '
                    .$ultimo->vigente_desde->format('m/Y').': registra uno posterior o anula ese.',
            ]);
        }

        if (($cerrado = $this->primerCierreDesde($trabajador, $desde)) !== null) {
            throw ValidationException::withMessages([
                'vigente_desde' => sprintf(
                    'Hay un periodo cerrado en %02d/%d o después: el cambio debe regir desde %s.',
                    $cerrado->mes, $cerrado->anio,
                    CarbonImmutable::create($cerrado->anio, $cerrado->mes, 1)->addMonth()->format('m/Y'),
                ),
            ]);
        }

        $diferencias = [];
        foreach (self::GRUPOS[$grupo][1] as $campo) {
            $anterior = $this->valorActual($trabajador, $campo);
            $nuevo = $nuevos[$campo] ?? null;
            if ($anterior !== $nuevo) {
                $diferencias[$campo] = [$anterior, $nuevo];
            }
        }

        if ($diferencias === []) {
            $primerCampo = self::GRUPOS[$grupo][1][0];
            throw ValidationException::withMessages([
                $primerCampo => 'Es el mismo valor que ya tiene; no hay cambio que registrar.',
            ]);
        }

        DB::transaction(function () use ($trabajador, $grupo, $desde, $diferencias, $usuario) {
            foreach ($diferencias as $campo => [$anterior, $nuevo]) {
                $trabajador->historial()->create([
                    'grupo' => $grupo,
                    'campo' => $campo,
                    'valor_anterior' => $anterior,
                    'valor_nuevo' => $nuevo,
                    'vigente_desde' => $desde,
                    'user_id' => $usuario?->id,
                ]);
            }

            $trabajador->forceFill(array_map(fn (array $d) => $d[1], $diferencias))->save();
        });
    }

    /** Anula el cambio (todo su grupo) si es el último del grupo y no toca un mes cerrado. */
    public function anular(TrabajadorHistorial $fila): void
    {
        $trabajador = $fila->trabajador;
        $ultimo = $this->ultimoCambio($trabajador, $fila->grupo);

        if ($ultimo === null || ! $ultimo->vigente_desde->equalTo($fila->vigente_desde)) {
            throw new \DomainException('Solo se puede anular el cambio más reciente de cada dato.');
        }

        if ($this->primerCierreDesde($trabajador, $fila->vigente_desde) !== null) {
            throw new \DomainException('Este cambio ya se usó en un periodo cerrado y no se puede anular.');
        }

        DB::transaction(function () use ($trabajador, $fila) {
            $filas = $trabajador->historial()
                ->where('grupo', $fila->grupo)
                ->whereDate('vigente_desde', $fila->vigente_desde->toDateString())
                ->get();

            $trabajador->forceFill($filas->mapWithKeys(fn (TrabajadorHistorial $h) => [$h->campo => $h->valor_anterior])->all())->save();
            $filas->each(fn (TrabajadorHistorial $h) => $h->delete());
        });
    }

    /**
     * Línea de tiempo para la ficha: un elemento por cambio (grupo + mes), del más reciente al más antiguo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lineaDeTiempo(Trabajador $trabajador): array
    {
        $cambios = $trabajador->historial()->with('usuario:id,name')->get();
        $ultimos = $cambios->groupBy('grupo')->map(fn (Collection $c) => $c->max(fn (TrabajadorHistorial $h) => $h->vigente_desde->timestamp));
        $afps = Afp::query()->pluck('nombre', 'id');

        return $cambios
            ->groupBy(fn (TrabajadorHistorial $h) => $h->grupo.'|'.$h->vigente_desde->toDateString())
            ->map(function (Collection $filas) use ($trabajador, $ultimos, $afps) {
                /** @var TrabajadorHistorial $primera */
                $primera = $filas->first();
                $esUltimo = $ultimos[$primera->grupo] === $primera->vigente_desde->timestamp;

                return [
                    'id' => $primera->id,
                    'grupo' => $primera->grupo,
                    'grupo_etiqueta' => self::GRUPOS[$primera->grupo][0] ?? $primera->grupo,
                    'desde' => $primera->vigente_desde->format('m/Y'),
                    'orden' => $primera->vigente_desde->toDateString(),
                    'usuario' => $primera->usuario->name ?? 'Sistema',
                    'registrado' => $primera->created_at?->format('d/m/Y'),
                    'cambios' => $filas->map(fn (TrabajadorHistorial $h) => [
                        'campo' => (string) __("validation.attributes.{$h->campo}"),
                        'antes' => $this->legible($h->campo, $h->valor_anterior, $afps),
                        'despues' => $this->legible($h->campo, $h->valor_nuevo, $afps),
                    ])->values()->all(),
                    'anulable' => $esUltimo && $this->primerCierreDesde($trabajador, $primera->vigente_desde) === null,
                ];
            })
            ->sortByDesc('orden')
            ->values()
            ->all();
    }

    /** Valor actual de un campo de la ficha como texto normalizado. */
    public function valorActual(Trabajador $trabajador, string $campo): ?string
    {
        return $this->normalizar($trabajador->getAttribute($campo));
    }

    public function normalizar(mixed $valor): ?string
    {
        return match (true) {
            $valor === null, $valor === '' => null,
            $valor instanceof BackedEnum => (string) $valor->value,
            is_bool($valor) => $valor ? '1' : '0',
            default => (string) $valor,
        };
    }

    /** @return Collection<int, TrabajadorHistorial> */
    private function cambios(Trabajador $trabajador): Collection
    {
        return $trabajador->relationLoaded('historial')
            ? $trabajador->historial
            : $trabajador->historial()->get();
    }

    private function ultimoCambio(Trabajador $trabajador, string $grupo): ?TrabajadorHistorial
    {
        return $trabajador->historial()->where('grupo', $grupo)->orderByDesc('vigente_desde')->first();
    }

    private function primerCierreDesde(Trabajador $trabajador, CarbonImmutable $desde): ?Periodo
    {
        return Periodo::query()
            ->deEmpresa($trabajador->empresa_id)
            ->where('estado', EstadoPeriodo::Cerrado)
            ->whereRaw('anio * 100 + mes >= ?', [$desde->year * 100 + $desde->month])
            ->orderBy('anio')->orderBy('mes')
            ->first();
    }

    /**
     * @param  Collection<int|string, mixed>  $afps
     */
    private function legible(string $campo, ?string $valor, Collection $afps): string
    {
        if ($valor === null) {
            return '—';
        }

        if ($campo === 'centro_costo_id') {
            $centro = CentroCosto::query()->find((int) $valor);

            return $centro === null ? $valor : "{$centro->codigo} {$centro->nombre}";
        }

        if ($campo === 'remuneracion_basica') {
            [$entero, $decimales] = explode('.', (string) BigDecimal::of($valor)->toScale(2));

            return 'S/ '.number_format((int) $entero).'.'.$decimales;
        }

        return match ($campo) {
            'asignacion_familiar' => $valor === '1' ? 'Sí' : 'No',
            'sistema_pension' => match (SistemaPension::from($valor)) {
                SistemaPension::Afp => 'AFP',
                SistemaPension::Onp => 'ONP',
                SistemaPension::Ninguno => 'Ninguno',
            },
            'afp_id' => (string) ($afps[(int) $valor] ?? $valor),
            'tipo_comision_afp' => TipoComisionAfp::from($valor)->etiqueta(),
            'seguro_salud' => SeguroSalud::from($valor)->etiqueta(),
            'tipo_contrato' => TipoContrato::from($valor)->etiqueta(),
            default => $valor,
        };
    }
}
