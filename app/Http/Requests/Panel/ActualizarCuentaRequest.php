<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoEstudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Editar una cuenta en el panel central: nombre, tipo, plan, límites y vencimiento. */
class ActualizarCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('superadmin') !== null;
    }

    /** @return array<string, list<mixed>> */
    public static function reglasPlan(): array
    {
        return [
            'plan' => ['required', 'string', 'max:40'],
            'max_empresas' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_usuarios' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_trabajadores' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoEstudio::class)],
            ...self::reglasPlan(),
            'vence_en' => ['nullable', 'date_format:Y-m-d', Rule::requiredIf($this->input('tipo') === TipoEstudio::Demo->value)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['vence_en.required' => 'Una demo necesita fecha de vencimiento.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la cuenta', 'vence_en' => 'vencimiento', 'max_empresas' => 'máximo de empresas',
            'max_usuarios' => 'máximo de usuarios', 'max_trabajadores' => 'máximo de trabajadores',
        ];
    }
}
