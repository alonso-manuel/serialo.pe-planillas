<?php

return [

    /*
    | Correos de boletas por minuto. Los hostings compartidos (cPanel) suelen limitar
    | entre 100 y 500 correos por hora; con 20/min un lote de 200 boletas tarda ~10 min.
    */
    'correos_por_minuto' => (int) env('PLANILLAS_CORREOS_POR_MINUTO', 20),

];
