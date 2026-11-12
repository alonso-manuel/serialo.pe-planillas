<?php

return [

    /*
    | «unica»: instalación en el hosting del cliente (por defecto). «saas»: VPS de Serialo.pe,
    | un estudio por subdominio con su propia base (ver docs/multitenant.md y config/tenancy.php).
    */
    'modo' => env('PLANILLAS_MODO', 'unica'),

    // Solo SaaS: dirección de cada estudio y dominio del panel central (superadmin).
    'url_estudio' => env('PLANILLAS_URL_ESTUDIO', 'https://{estudio}.impulso.cfd'),
    'dominio_panel' => env('PLANILLAS_DOMINIO_PANEL', 'admin.impulso.cfd'),

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
    /*
    | true cuando un proceso permanente atiende la cola (servicio serialo-cola del VPS):
    | el cron de cada minuto deja de lanzar queue:work.
    */
    'cola_permanente' => (bool) env('PLANILLAS_COLA_PERMANENTE', false),

    'correos_por_minuto' => (int) env('PLANILLAS_CORREOS_POR_MINUTO', 20),

    /*
    | Cuenta contable (PCGE) del neto a pagar en la centralización de la planilla.
    | Las demás cuentas están en cada concepto (pantalla Conceptos).
    */
    'cuenta_remuneraciones_por_pagar' => env('PLANILLAS_CUENTA_REMUNERACIONES', '4111'),

    /*
    | Respaldos automáticos de la base (php artisan respaldos:crear, cada día a la hora indicada).
    | dias: cuántos días se conservan (el más reciente nunca se borra).
    | disco_externo: nombre de un disco de config/filesystems.php para guardar una copia fuera
    | del servidor (vacío = solo en el servidor). En el VPS se recomienda además rclone (docs/respaldos.md).
    */
    'respaldos' => [
        'dias' => (int) env('PLANILLAS_RESPALDO_DIAS', 14),
        'hora' => env('PLANILLAS_RESPALDO_HORA', '03:00'),
        'disco_externo' => env('PLANILLAS_RESPALDO_DISCO'),
    ],

    /*
    | Términos del servicio y política de privacidad (páginas públicas /terminos y /privacidad).
    | responsable: empresa o estudio que opera esta instalación y es titular de los datos de sus trabajadores
    |   (vacío = texto genérico; en modo saas se usa el nombre del estudio).
    | correo: dónde se ejercen los derechos ARCO y se hacen consultas de privacidad.
    | ⚠ Textos por revisar con un abogado antes de vender (docs/manual-usuario.md, sección 12).
    */
    /*
    | Beta pública: muestra la etiqueta «Beta» junto al nombre del sistema y la sección de beta en los términos.
    | Se apaga con PLANILLAS_BETA=false al publicar la versión 1.0.
    */
    'beta' => (bool) env('PLANILLAS_BETA', true),

    'legal' => [
        'version' => '12/11/2026',
        'responsable' => env('PLANILLAS_LEGAL_RESPONSABLE'),
        'correo' => env('PLANILLAS_LEGAL_CORREO'),
    ],

];
