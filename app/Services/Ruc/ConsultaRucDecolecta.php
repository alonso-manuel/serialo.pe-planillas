<?php

namespace App\Services\Ruc;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Decolecta (antes apis.net.pe, https://decolecta.com): plan gratuito con token
 * (registro en decolecta.com/profile). Cubre personas naturales y jurídicas.
 * GET /v1/sunat/ruc?numero={ruc} con `Authorization: Bearer <token>`
 * → {razon_social, numero_documento, estado, condicion, direccion, ubigeo, distrito, ...}
 * 422 = RUC no válido; 404 = no existe.
 */
final class ConsultaRucDecolecta implements ConsultaRuc
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $urlBase = 'https://api.decolecta.com',
    ) {}

    public function consultar(string $ruc): DatosRuc
    {
        if ($this->token === null || $this->token === '') {
            throw ConsultaRucNoDisponible::sinConfigurar();
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($this->token)
                ->timeout(8)
                ->get(rtrim($this->urlBase, '/').'/v1/sunat/ruc', ['numero' => $ruc]);
        } catch (ConnectionException $e) {
            throw ConsultaRucNoDisponible::caida($e);
        }

        if ($respuesta->notFound() || $respuesta->status() === 422) {
            throw new RucNoEncontrado;
        }

        $razonSocial = DatosRuc::limpiar($respuesta->json('razon_social'));

        if (! $respuesta->successful() || $razonSocial === null) {
            throw ConsultaRucNoDisponible::caida();
        }

        return new DatosRuc(
            ruc: $ruc,
            razonSocial: $razonSocial,
            direccion: DatosRuc::limpiar($respuesta->json('direccion')),
            ubigeo: DatosRuc::ubigeo($respuesta->json('ubigeo')),
            estado: DatosRuc::limpiar($respuesta->json('estado')),
            condicion: DatosRuc::limpiar($respuesta->json('condicion')),
        );
    }
}
