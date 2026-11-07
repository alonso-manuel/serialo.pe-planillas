<?php

namespace App\Services\Dni;

final readonly class DatosDni
{
    public function __construct(
        public string $dni,
        public string $apellidoPaterno,
        public ?string $apellidoMaterno,
        public string $nombres,
    ) {}

    /** @return array{dni: string, apellido_paterno: string, apellido_materno: string|null, nombres: string} */
    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'nombres' => $this->nombres,
        ];
    }
}
