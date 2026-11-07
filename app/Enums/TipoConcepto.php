<?php

namespace App\Enums;

enum TipoConcepto: string
{
    case Ingreso = 'ingreso';                     // 0100-0500, 0900, 1000
    case DescuentoTrabajador = 'descuento';       // 0700
    case AporteTrabajador = 'aporte_trab';        // 0600
    case AporteEmpleador = 'aporte_empl';         // 0800
}
