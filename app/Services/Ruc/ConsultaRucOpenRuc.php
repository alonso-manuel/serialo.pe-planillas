<?php

namespace App\Services\Ruc;

use App\Domain\Sunat\Ruc;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenRUC (https://openruc.com): gratis y sin token, solo personas jurídicas (RUC 20).
 * GET /api/ruc/{ruc} → {ruc, razon_social, estado, condicion, direccion, ubigeo, source, as_of}
 * 404 = no existe; 429 = límite de uso.
 */
final class ConsultaRucOpenRuc implements ConsultaRuc
{
    public function __construct(private readonly string $urlBase = 'https://openruc.com') {}

    public function consultar(string $ruc): DatosRuc
    {
        if (! Ruc::esPersonaJuridica($ruc)) {
            throw ConsultaRucNoDisponible::soloJuridicas();
        }

        try {
            $respuesta = Http::acceptJson()
                ->timeout(8)
                ->get(rtrim($this->urlBase, '/')."/api/ruc/{$ruc}");
        } catch (ConnectionException $e) {
            throw ConsultaRucNoDisponible::caida($e);
        }

        if ($respuesta->notFound()) {
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
