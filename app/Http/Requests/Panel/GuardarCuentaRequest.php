<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoEstudio;
use App\Models\Central\Estudio;
use App\Services\Central\GestionEstudios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Nueva cuenta en el panel central. Solo llega aquí el superadmin autenticado con doble factor. */
class GuardarCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('superadmin') !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdominio' => mb_strtolower(trim((string) $this->input('subdominio'))),
            'correo_admin' => mb_strtolower(trim((string) $this->input('correo_admin'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subdominio' => [...GestionEstudios::reglasSubdominio(), Rule::unique(Estudio::class, 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_admin' => ['required', 'string', 'max:255'],
            'correo_admin' => ['required', 'email', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoEstudio::class)],
            'vence_en' => ['nullable', 'date_format:Y-m-d', 'after:today', Rule::requiredIf($this->input('tipo') === TipoEstudio::Demo->value)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...GestionEstudios::mensajesSubdominio(),
            'vence_en.required' => 'Una demo necesita fecha de vencimiento.',
            'vence_en.after' => 'El vencimiento debe ser una fecha futura.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la cuenta', 'nombre_admin' => 'nombre del administrador',
            'correo_admin' => 'correo del administrador', 'vence_en' => 'vencimiento',
        ];
    }
}
