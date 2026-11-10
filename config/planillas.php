<?php

return [

    /*
    | «unica»: instalación en el hosting del cliente (por defecto). «saas»: VPS de Serialo.pe,
    | un estudio por subdominio con su propia base (ver docs/multitenant.md y config/tenancy.php).
    */
    'modo' => env('PLANILLAS_MODO', 'unica'),

    // Solo SaaS: dirección de cada estudio y dominio del panel central (superadmin).
    'url_estudio' => env('PLANILLAS_URL_ESTUDIO', 'https://{estudio}.serialo.pe'),
    'dominio_panel' => env('PLANILLAS_DOMINIO_PANEL', 'admin.serialo.pe'),

    /*
    | Solo SaaS: planes que propone el panel al crear una cuenta (se pueden ajustar por cuenta).
    | Límites null = ilimitado. `dias` = vencimiento sugerido (demos).
    */
    'planes' => [
        'demo' => ['nombre' => 'Demo', 'max_empresas' => 40, 'max_usuarios' => 3, 'max_trabajadores' => null, 'dias' => 15],
        'empresa' => ['nombre' => 'Empresa', 'max_empresas' => 1, 'max_usuarios' => 2, 'max_trabajadores' => 50, 'dias' => null],
        'estudio' => ['nombre' => 'Estudio', 'max_empresas' => 40, 'max_usuarios' => 3, 'max_trabajadores' => null, 'dias' => null],
        'estudio_pro' => ['nombre' => 'Estudio Pro', 'max_empresas' => null, 'max_usuarios' => 10, 'max_trabajadores' => null, 'dias' => null],
    ],

    /*
    | Correos de boletas por minuto. Los hostings compartidos (cPanel) suelen limitar
    | entre 100 y 500 correos por hora; con 20/min un lote de 200 boletas tarda ~10 min.
    */
    'correos_por_minuto' => (int) env('PLANILLAS_CORREOS_POR_MINUTO', 20),

    /*
    | Cuenta contable (PCGE) del neto a pagar en la centralización de la planilla.
    | Las demás cuentas están en cada concepto (pantalla Conceptos).
    */
    'cuenta_remuneraciones_por_pagar' => env('PLANILLAS_CUENTA_REMUNERACIONES', '4111'),

];
