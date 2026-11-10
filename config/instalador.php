<?php

return [
    /*
    | El instalador web solo existe en una copia recién subida al hosting (sin `.env` → producción).
    | En desarrollo y en tests se omite; se puede forzar con INSTALADOR_OMITIR.
    */
    'omitir' => (bool) env('INSTALADOR_OMITIR', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) || env('PLANILLAS_MODO') === 'saas'),

    // Dónde quedan la marca de «instalado» y el código de instalación.
    'directorio' => storage_path('app'),
];
