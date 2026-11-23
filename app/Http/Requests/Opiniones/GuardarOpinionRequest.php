<?php

namespace App\Http\Requests\Opiniones;

use App\Models\User;
use App\Services\Opiniones\PedidoOpinion;
use Illuminate\Foundation\Http\FormRequest;

/** Comentario de un usuario sobre el sistema (modo SaaS). */
class GuardarOpinionRequest extends FormRequest
{
    public function authorize(PedidoOpinion $pedido): bool
    {
        $usuario = $this->user('web');

        return $usuario instanceof User && $pedido->habilitada($usuario);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'calificacion' => ['nullable', 'integer', 'between:1,5'],
            'comentario' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'comentario.required' => 'Escribe tu comentario antes de enviarlo.',
            'comentario.min' => 'Cuéntanos un poco más (al menos 5 caracteres).',
        ];
    }
}
