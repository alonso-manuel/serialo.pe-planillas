<?php

namespace App\Enums;

/** Tabla 19 de SUNAT: vínculo familiar del derechohabiente, y los documentos de la tabla 27 que lo sustentan. */
enum VinculoFamiliar: string
{
    case Conyuge = '02';
    case Concubino = '03';
    case Gestante = '04';
    case HijoMenor = '05';
    case HijoMayorIncapacitado = '06';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Conyuge => 'Cónyuge',
            self::Concubino => 'Concubina(o)',
            self::Gestante => 'Gestante',
            self::HijoMenor => 'Hijo(a) menor de edad',
            self::HijoMayorIncapacitado => 'Hijo(a) mayor de edad incapacitado permanente',
        };
    }

    /**
     * Documentos de la tabla 27 que acreditan este vínculo (notas de la tabla).
     *
     * @return list<DocumentoVinculo>
     */
    public function documentos(): array
    {
        return match ($this) {
            self::Gestante => [DocumentoVinculo::EscrituraPublica, DocumentoVinculo::SentenciaPaternidad, DocumentoVinculo::Testamento],
            self::HijoMayorIncapacitado => [DocumentoVinculo::ResolucionIncapacidad],
            self::Conyuge => [DocumentoVinculo::PartidaMatrimonio, DocumentoVinculo::PartidaMatrimonioConsular, DocumentoVinculo::PartidaMatrimonioExterior],
            self::Concubino => [DocumentoVinculo::EscrituraUnionHecho, DocumentoVinculo::ResolucionUnionHecho, DocumentoVinculo::DeclaracionUnionHecho],
            self::HijoMenor => [DocumentoVinculo::ActaNacimiento],
        };
    }

    /** Estructura 13: el número del documento es obligatorio para concubino(a) e hijo mayor incapacitado. */
    public function exigeNumeroDocumento(): bool
    {
        return $this === self::Concubino || $this === self::HijoMayorIncapacitado;
    }
}
