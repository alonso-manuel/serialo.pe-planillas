<?php

namespace App\Domain\Sunat\TRegistro;

/**
 * Datos de un trabajador para los archivos de T-Registro, ya en códigos de las tablas de SUNAT.
 * Fechas en dd/mm/aaaa. Los vacíos van como cadena vacía.
 */
final readonly class PrestadorTRegistro
{
    public function __construct(
        public string $tipoDocumento,          // tabla 3: 01 DNI, 04 CE, 07 pasaporte
        public string $numeroDocumento,
        public string $paisEmisor,             // tabla 26 (604 para 01/04/09)
        public string $fechaNacimiento = '',
        public string $apellidoPaterno = '',
        public string $apellidoMaterno = '',
        public string $nombres = '',
        public string $sexo = '',              // 1 masculino, 2 femenino
        public string $nacionalidad = '',      // tabla 4 (solo extranjeros)
        public string $telefono = '',          // celular de 9 dígitos
        public string $correo = '',
        public string $regimenLaboral = '',    // tabla 33
        public string $situacionEducativa = '', // tabla 9
        public string $ocupacion = '',         // tabla 30
        public bool $discapacidad = false,
        public string $cuspp = '',
        public string $tipoContrato = '',      // tabla 12
        public bool $sindicalizado = false,
        public string $periodicidad = '1',     // tabla 13: mensual
        public string $remuneracionBasica = '',
        public string $situacionEspecial = '0', // tabla 35
        public string $tipoPago = '',          // tabla 16
        public string $categoriaOcupacional = '', // tabla 24
        public string $tipoTrabajador = '',    // tabla 8
        public string $regimenSalud = '',      // tabla 32
        public string $regimenPensionario = '', // tabla 11
        public string $fechaIngreso = '',
        public string $fechaCese = '',
        public string $motivoCese = '',        // tabla 17
        public string $ruc = '',               // RUC del empleador (estructura 17)
        public string $establecimiento = '',   // código de 4 dígitos del establecimiento
    ) {}
}
