<?php

namespace App\Enums;

enum SistemaPension: string
{
    case Afp = 'afp';   // Sistema Privado de Pensiones (SPP)
    case Onp = 'onp';   // Sistema Nacional de Pensiones (SNP)
    case Ninguno = 'ninguno'; // casos excepcionales: queda marcado para revisión
}
