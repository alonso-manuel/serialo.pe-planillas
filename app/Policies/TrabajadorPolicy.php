<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorEmpresa;

/** Trabajadores: cualquier usuario sobre los de sus empresas. */
class TrabajadorPolicy
{
    use AutorizaPorEmpresa;
}
