<?php

namespace App\Models;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraIndemnizacionDespido;
use App\Domain\Planilla\CalculadoraLiquidacion;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liquidación de beneficios sociales de un trabajador al cese. Sus conceptos se pagan en la planilla del mes
 * del cese (boleta y PLAME). Cerrada = inmutable.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $trabajador_id
 * @property CarbonImmutable $fecha_cese
 * @property string $estado borrador | cerrada
 * @property bool $despido_arbitrario se paga indemnización por despido (0501) o por resolución del plazo fijo (0503)
 * @property CarbonImmutable|null $fin_contrato vencimiento del contrato a plazo fijo (para el 0503)
 * @property string $gratificacion_pendiente
 * @property string $bonificacion_pendiente
 * @property string $gratificacion_trunca
 * @property string $bonificacion_trunca
 * @property string $cts
 * @property string $vacaciones_no_gozadas
 * @property string $indemnizacion_vacacional
 * @property string $vacaciones_truncas
 * @property string $indemnizacion_despido
 * @property string $indemnizacion_plazo_fijo
 * @property string $total
 * @property array<string, mixed>|null $detalle
 * @property list<string>|null $avisos
 * @property CarbonImmutable|null $calculado_en
 * @property int|null $cerrado_por
 * @property CarbonImmutable|null $cerrado_en
 * @property-read Trabajador|null $trabajador
 * @property-read User|null $cerradoPor
 */
class Liquidacion extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const BORRADOR = 'borrador';

    public const CERRADA = 'cerrada';

    /** Columna => código de la tabla 22 con que se paga en la planilla. */
    public const CONCEPTOS = [
        'gratificacion_pendiente' => CalculadoraGratificacion::GRATIFICACION,
        'bonificacion_pendiente' => CalculadoraGratificacion::BONIFICACION,
        'gratificacion_trunca' => CalculadoraLiquidacion::GRATIFICACION_TRUNCA,
        'bonificacion_trunca' => CalculadoraLiquidacion::BONIFICACION_TRUNCA,
        'cts' => CalculadoraLiquidacion::CTS,
        'vacaciones_no_gozadas' => CalculadoraLiquidacion::VACACIONES_NO_GOZADAS,
        'indemnizacion_vacacional' => CalculadoraLiquidacion::INDEMNIZACION_VACACIONAL,
        'vacaciones_truncas' => CalculadoraLiquidacion::VACACIONES_TRUNCAS,
        'indemnizacion_despido' => CalculadoraIndemnizacionDespido::DESPIDO,
        'indemnizacion_plazo_fijo' => CalculadoraIndemnizacionDespido::PLAZO_FIJO,
    ];

    protected $table = 'liquidaciones';

    protected $fillable = [
        'empresa_id', 'trabajador_id', 'fecha_cese', 'estado', 'despido_arbitrario', 'fin_contrato', ...self::COLUMNAS_MONTO, 'total',
        'detalle', 'avisos', 'calculado_en', 'cerrado_por', 'cerrado_en',
    ];

    private const COLUMNAS_MONTO = [
        'gratificacion_pendiente', 'bonificacion_pendiente', 'gratificacion_trunca', 'bonificacion_trunca',
        'cts', 'vacaciones_no_gozadas', 'indemnizacion_vacacional', 'vacaciones_truncas',
        'indemnizacion_despido', 'indemnizacion_plazo_fijo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_cese' => 'immutable_date',
            'despido_arbitrario' => 'boolean',
            'fin_contrato' => 'immutable_date',
            ...array_fill_keys([...self::COLUMNAS_MONTO, 'total'], 'decimal:2'),
            'detalle' => 'array',
            'avisos' => 'array',
            'calculado_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function estaCerrada(): bool
    {
        return $this->estado === self::CERRADA;
    }

    /**
     * Conceptos a pagar en la planilla del mes del cese (código => monto), solo los positivos.
     *
     * @return array<string, string>
     */
    public function conceptos(): array
    {
        $conceptos = [];
        foreach (self::CONCEPTOS as $columna => $codigo) {
            $monto = (string) $this->getAttribute($columna);
            if ($monto !== '' && BigDecimal::of($monto)->isPositive()) {
                $conceptos[$codigo] = $monto;
            }
        }

        return $conceptos;
    }

    public function descripcionAuditoria(): string
    {
        $trabajador = $this->trabajador;

        return 'Liquidación de '.($trabajador === null ? "trabajador {$this->trabajador_id}" : $trabajador->nombre_completo)
            .' (cese '.$this->fecha_cese->format('d/m/Y').')';
    }
}
