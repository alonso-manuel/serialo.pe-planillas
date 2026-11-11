<?php

namespace App\Domain\Sunat\TRegistro;

use App\Domain\Sunat\Plame\LineaPlame;

/**
 * Carga masiva del Registro de Derechohabientes (SOL → T-Registro → Registro de derechohabientes):
 * - RD_<RUC>_<ddmmaaaa>_ALTA.TXT — estructura 13 (46 campos; direcciones, teléfono y correo vacíos: opcionales).
 * - RD_<RUC>_<ddmmaaaa>_BAJA.TXT — estructura 24 (12 campos).
 * Campos separados por «|» (R.M. 121-2011-TR, Anexo 3; cartilla de instrucciones del Registro de Derechohabientes).
 */
final class GeneradorDerechohabientes
{
    /** @param  list<DerechohabienteTRegistro>  $altas */
    public function alta(array $altas): string
    {
        return LineaPlame::archivo(array_map(fn (DerechohabienteTRegistro $d) => LineaPlame::de([
            ...$this->identificacion($d),
            $d->sexo,
            $d->vinculo,
            $d->documentoVinculo,
            $d->numeroDocumentoVinculo,
            $d->mesConcepcion,
            ...array_fill(0, 32, ''), // 15–46 direcciones, indicador de centro asistencial, teléfono y correo
        ]), $altas));
    }

    /** @param  list<DerechohabienteTRegistro>  $bajas */
    public function baja(array $bajas): string
    {
        return LineaPlame::archivo(array_map(fn (DerechohabienteTRegistro $d) => LineaPlame::de([
            ...$this->identificacion($d),
            $d->vinculo,
            $d->fechaBaja,
            $d->motivoBaja,
        ]), $bajas));
    }

    /**
     * Campos 1 a 9, comunes a las dos estructuras.
     *
     * @return list<string>
     */
    private function identificacion(DerechohabienteTRegistro $d): array
    {
        return [
            $d->tipoDocumentoTitular, $d->numeroDocumentoTitular, $d->tipoDocumento, $d->numeroDocumento, $d->paisEmisor,
            $d->fechaNacimiento, $d->apellidoPaterno, $d->apellidoMaterno, $d->nombres,
        ];
    }
}
