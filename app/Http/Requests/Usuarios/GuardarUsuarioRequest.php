<?php

namespace App\Http\Requests\Usuarios;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta y edición de usuarios. La contraseña es obligatoria al crear y opcional al
 * editar (vacía = no se cambia). Las empresas solo aplican al rol asistente.
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->usuarioEditado();

        return $usuario === null
            ? (bool) $this->user()?->can('create', User::class)
            : (bool) $this->user()?->can('update', $usuario);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usuario = $this->usuarioEditado();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class)->ignore($usuario?->id),
            ],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'password' => [$usuario === null ? 'required' : 'nullable', 'string', Password::default()],
            'empresas' => ['array'],
            'empresas.*' => [
                'integer',
                'distinct',
                Rule::exists('empresas', 'id')->where('activo', true)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'empresas.*' => 'empresa',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'empresas.*.exists' => 'La empresa seleccionada no existe o no está activa.',
        ];
    }

    private function usuarioEditado(): ?User
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario : null;
    }
}
