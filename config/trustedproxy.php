<?php

return [
    /*
    | Proxies de confianza (middleware TrustProxies): de ellos se leen X-Forwarded-Proto/-For/-Host para saber que
    | la petición llegó por HTTPS y cuál es la IP real del usuario (auditoría). Vacío en una instalación en hosting;
    | en Docker, las redes internas por donde llega el proxy del servidor (deploy/docker/serialo.env.ejemplo).
    | Valores: «*» (cualquiera) o una lista separada por comas de IP o rangos CIDR.
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
