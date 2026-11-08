<?php

namespace App\Services\Planilla;

/** Datos que faltan para calcular un periodo (se informan todos juntos; no se calcula nada). */
final class ProblemasDeCalculo extends \DomainException
{
    /** @param list<string> $problemas */
    public function __construct(public readonly array $problemas)
    {
        parent::__construct(implode(' ', $problemas));
    }
}
