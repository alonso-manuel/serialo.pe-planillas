<?php

namespace App\Services\Dni;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Decolecta (https://decolecta.com), plan gratuito con token y cupo mensual.
 * GET /v1/reniec/dni?numero={dni} con `Authorization: Bearer <token>`
 * → {first_name, first_last_name, second_last_name, full_name, document_number}
 * 400/404/422 = sin datos; 429 = cupo agotado.
 */
final class ConsultaDniDecolecta implements ConsultaDni
{
    public function __construct(
        private readonly string $token,
        private readonly string $urlBase = 'https://api.decolecta.com',
    ) {}

    public function consultar(string $dni): DatosDni
    {
        try {
            $respuesta = Http::acceptJson()
                ->withToken($this->token)
                ->timeout(8)
                ->get(rtrim($this->urlBase, '/').'/v1/reniec/dni', ['numero' => $dni]);
        } catch (ConnectionException $e) {
            throw ConsultaDniNoDisponible::caida($e);
        }

        if ($respuesta->status() === 429) {
            throw ConsultaDniNoDisponible::sinCupo();
        }

        if (in_array($respuesta->status(), [400, 404, 422], true)) {
            throw new DniNoEncontrado;
        }

        $paterno = $this->texto($respuesta->json('first_last_name'));
        $nombres = $this->texto($respuesta->json('first_name'));

        if (! $respuesta->successful()) {
            throw ConsultaDniNoDisponible::caida();
        }

        if ($paterno === null || $nombres === null) {
            throw new DniNoEncontrado;
        }

        return new DatosDni(
            dni: $dni,
            apellidoPaterno: $paterno,
            apellidoMaterno: $this->texto($respuesta->json('second_last_name')),
            nombres: $nombres,
        );
    }

    public function disponible(): bool
    {
        return true;
    }

    private function texto(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim(preg_replace('/\s+/', ' ', $valor) ?? '') : '';

        return $valor === '' ? null : $valor;
    }
}
