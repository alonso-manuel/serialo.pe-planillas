<?php

namespace App\Http\Requests\Empresas;

use App\Models\Empresa;
use App\Models\Establecimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Establecimiento SUNAT: código de 4 dígitos único dentro de la empresa. */
class GuardarEstablecimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->empresa());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'digits:4',
                Rule::unique('establecimientos', 'codigo')
                    ->where('empresa_id', $this->empresa()->id)
                    ->ignore($this->establecimiento()?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->establecimiento()?->esPrincipal() && $this->input('codigo') !== Establecimiento::CODIGO_PRINCIPAL) {
                    $validator->errors()->add('codigo', 'El establecimiento principal siempre tiene el código 0000.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya hay un establecimiento con ese código en esta empresa.',
        ];
    }

    private function empresa(): Empresa
    {
        /** @var Empresa */
        return $this->route('empresa');
    }

    private function establecimiento(): ?Establecimiento
    {
        $establecimiento = $this->route('establecimiento');

        return $establecimiento instanceof Establecimiento ? $establecimiento : null;
    }
}
