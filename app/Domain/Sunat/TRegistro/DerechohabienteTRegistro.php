<?php

namespace App\Domain\Sunat\TRegistro;

/** Un derechohabiente para las estructuras 13 (alta) y 24 (baja), ya en códigos de SUNAT. Fechas dd/mm/aaaa. */
final readonly class DerechohabienteTRegistro
{
    public function __construct(
        public string $tipoDocumentoTitular,
        public string $numeroDocumentoTitular,
        public string $tipoDocumento,
        public string $numeroDocumento,
        public string $paisEmisor,
        public string $fechaNacimiento,
        public string $apellidoPaterno,
        public string $apellidoMaterno,
        public string $nombres,
        public string $sexo,                    // 1 masculino, 2 femenino
        public string $vinculo,                 // tabla 19
        public string $documentoVinculo = '',   // tabla 27
        public string $numeroDocumentoVinculo = '',
        public string $mesConcepcion = '',      // mmaaaa (gestante)
        public string $fechaBaja = '',
        public string $motivoBaja = '',         // tabla 20
    ) {}
}
