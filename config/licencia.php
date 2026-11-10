<?php

return [
    /*
    | Clave pública Ed25519 de Serialo.pe. Solo verifica licencias; no permite emitirlas.
    | La privada vive en el generador de licencias, fuera de este repositorio.
    */
    'clave_publica' => 'r6Z60sW3MKK6KJAAbX3SnKaaIk83fD49abqLmINecm8=',

    /*
    | En desarrollo, en tests y en modo SaaS (los límites salen del plan del estudio) no se verifica;
    | se puede forzar con LICENCIA_OMITIR.
    */
    'omitir' => (bool) env('LICENCIA_OMITIR', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) || env('PLANILLAS_MODO') === 'saas'),

    // Archivo donde queda la clave del cliente (junto a la marca de instalado).
    'archivo' => storage_path('app/licencia.txt'),

    /*
    | Prueba sin licencia: días desde la instalación y límites (los del plan Empresa). null = ilimitado.
    */
    'prueba' => [
        'dias' => 15,
        'max_empresas' => 1,
        'max_usuarios' => 2,
        'max_trabajadores' => 50,
    ],
];
