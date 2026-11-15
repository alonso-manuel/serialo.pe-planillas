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
        'demo' => ['nombre' => 'Demo', 'max_empresas' => 40, 'max_usuarios' => 10, 'max_trabajadores' => null, 'dias' => 15],
        'empresa' => ['nombre' => 'Empresa', 'max_empresas' => 1, 'max_usuarios' => 2, 'max_trabajadores' => 50, 'dias' => null],
        'estudio' => ['nombre' => 'Estudio', 'max_empresas' => 40, 'max_usuarios' => 10, 'max_trabajadores' => null, 'dias' => null],
        'estudio_pro' => ['nombre' => 'Estudio Pro', 'max_empresas' => null, 'max_usuarios' => 20, 'max_trabajadores' => null, 'dias' => null],
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
    | Versión del sistema (se muestra al pie de cada página). Subirla con cada etiqueta de git (vX.Y.Z).
    */
    'version' => '1.0.0',

    /*
    | Beta pública: etiqueta «Beta» junto al nombre del sistema y sección de beta en los términos. Terminó con la
    | versión 1.0.0 (15/11/2026); queda apagada salvo PLANILLAS_BETA=true (p. ej. un servidor de pruebas).
    */
    'beta' => (bool) env('PLANILLAS_BETA', false),

    'legal' => [
        'version' => '12/11/2026',
        'responsable' => env('PLANILLAS_LEGAL_RESPONSABLE'),
        'correo' => env('PLANILLAS_LEGAL_CORREO'),
    ],

    /*
    | Guía (menú Ayuda): videos tutoriales `<clave>.mp4` (catálogo en App\Support\GuiaVideos) y el manual en PDF.
    | directorio: dónde están los videos (carpeta guia/ junto al sistema, fuera del repositorio; la llena
    | tools/videos/preparar-guia.mjs).
    | x_accel: con Nginx delante (Docker), PHP solo valida la sesión y Nginx entrega el archivo (rápido y con saltos
    | en el video) desde su ubicación interna /_guia/. Sin Nginx (hosting compartido), lo entrega PHP.
    | Solo los usuarios con sesión iniciada ven los videos y el manual.
    */
    'guia' => [
        'directorio' => env('PLANILLAS_GUIA_DIRECTORIO', base_path('guia')),
        'x_accel' => (bool) env('PLANILLAS_GUIA_X_ACCEL', false),
    ],

];
