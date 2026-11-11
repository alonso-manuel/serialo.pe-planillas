<?php

namespace App\Http\Requests\Empresas;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Models\Empresa;
use App\Rules\RucValido;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de empresas. El RUC solo se indica al crear: después no cambia
 * (identifica los archivos PLAME y AFPnet ya presentados).
 */
class GuardarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $empresa = $this->empresaEditada();

        return $empresa === null
            ? (bool) $this->user()?->can('create', Empresa::class)
            : (bool) $this->user()?->can('update', $empresa);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'digits:6'],
            'regimen_laboral' => ['required', Rule::enum(RegimenLaboral::class)],
            'essalud_agrario_mayor' => ['sometimes', 'boolean'],
            'seguro_salud' => ['required', Rule::enum(SeguroSalud::class)],
            'representante_legal' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->empresaEditada() === null) {
            $reglas['ruc'] = ['required', 'string', new RucValido, Rule::unique('empresas', 'ruc')];
        }

        return $reglas;
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // ⚠ Validado con la norma REMYPE: solo la microempresa puede afiliar a sus trabajadores al SIS.
                if ($this->input('seguro_salud') === SeguroSalud::Sis->value
                    && $this->input('regimen_laboral') !== RegimenLaboral::Microempresa->value) {
                    $validator->errors()->add('seguro_salud', 'El SIS solo aplica a microempresas acogidas al REMYPE.');
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
            'ruc.unique' => 'Esta empresa ya está registrada en el sistema.',
        ];
    }

    private function empresaEditada(): ?Empresa
    {
        $empresa = $this->route('empresa');

        return $empresa instanceof Empresa ? $empresa : null;
    }
}
