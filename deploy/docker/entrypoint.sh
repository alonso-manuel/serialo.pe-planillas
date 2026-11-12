#!/bin/sh
# Prepara la app antes de arrancar PHP-FPM, la cola o el programador. No migra: eso se hace a mano
# (docker compose exec app php artisan estudios:migrar), así una actualización nunca toca las bases sola.
set -e

cd /var/www/html

# Carpetas del volumen de storage (vacío la primera vez).
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

# Configuración, rutas, vistas y eventos en caché (las variables vienen del entorno del contenedor).
php artisan optimize --quiet

exec "$@"
