<?php

namespace App\Rules;

use App\Domain\Sunat\Ruc;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RucValido implements ValidationRule
{
    public const MENSAJE = 'El RUC no es válido. Revisa los 11 dígitos.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Ruc::esValido($value)) {
            $fail(self::MENSAJE);
        }
    }
}
