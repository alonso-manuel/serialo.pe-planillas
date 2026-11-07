<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEmpresaActivaRequest extends FormRequest
{
    /** Un asistente no puede activar una empresa que no tiene asignada (403). */
    public function authorize(): bool
    {
        $empresa = $this->empresa();

        return $empresa === null || $this->usuario()->puedeVerEmpresa($empresa);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'empresa_id' => [
                'required',
                'integer',
                Rule::exists('empresas', 'id')->where('activo', true)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'empresa_id.required' => 'Selecciona una empresa.',
            'empresa_id.exists' => 'La empresa no existe o no está activa.',
        ];
    }

    public function empresa(): ?Empresa
    {
        $id = $this->input('empresa_id');

        return is_numeric($id) ? Empresa::query()->find((int) $id) : null;
    }

    private function usuario(): User
    {
        /** @var User */
        return $this->user();
    }
}
