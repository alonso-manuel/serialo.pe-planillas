<?php

namespace App\Http\Requests\Parametros;

use App\Enums\ClaveParametro;
use App\Models\Parametro;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nueva vigencia de un parámetro legal.
 * - `vigente_desde` llega como mes (AAAA-MM) y se guarda como día 1.
 * - Montos en soles con 2 decimales; tasas en porcentaje con 2 decimales (se guardan como fracción).
 */
class NuevaVigenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Parametro::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clave' => ['required', Rule::enum(ClaveParametro::class)],
            'valor' => ['required', 'string'],
            'vigente_desde' => ['required', 'date_format:Y-m'],
            'base_legal' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clave = ClaveParametro::tryFrom($this->string('clave')->toString());

                if ($clave === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $valor = $this->string('valor')->toString();

                if ($clave->tipo() === 'tasa') {
                    if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $valor) !== 1 || BigDecimal::of($valor)->isGreaterThan(100)) {
                        $validator->errors()->add('valor', 'La tasa debe estar entre 0 % y 100 %, con hasta 2 decimales.');
                    }
                } elseif (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $valor) !== 1 || preg_match('/^0+(\.0+)?$/', $valor) === 1) {
                    $validator->errors()->add('valor', 'El monto debe ser mayor que cero y tener como máximo 2 decimales.');
                }

                if (Parametro::query()->where('clave', $clave)->whereDate('vigente_desde', $this->fechaDesde())->exists()) {
                    $validator->errors()->add('vigente_desde', 'Ya existe una vigencia de este parámetro desde esa fecha.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clave' => 'parámetro',
            'valor' => 'valor',
            'vigente_desde' => 'vigente desde',
            'base_legal' => 'base legal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vigente_desde.date_format' => 'Indica el mes desde el que rige (AAAA-MM).',
        ];
    }

    /** Día 1 del mes indicado, AAAA-MM-01. */
    public function fechaDesde(): string
    {
        return $this->string('vigente_desde')->toString().'-01';
    }

    /**
     * @return array{clave: ClaveParametro, valor: string, vigente_desde: string, base_legal: string}
     */
    public function datos(): array
    {
        $clave = ClaveParametro::from($this->string('clave')->toString());

        return [
            'clave' => $clave,
            'valor' => $clave->normalizar($this->string('valor')->toString()),
            'vigente_desde' => $this->fechaDesde(),
            'base_legal' => trim($this->string('base_legal')->toString()),
        ];
    }
}
