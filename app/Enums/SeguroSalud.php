<?php

namespace App\Enums;

enum SeguroSalud: string
{
    case Essalud = 'essalud';
    case Sis = 'sis'; // solo microempresas acogidas al SIS
}
