#!/usr/bin/env bash
# Publica la última versión de main en el VPS (ver docs/vps.md).   sudo bash deploy/vps/actualizar.sh
# Respalda todo antes de migrar; si algo falla, el sitio queda en mantenimiento y se puede volver atrás.
set -euo pipefail

DIR="${DIR:-/var/www/serialo}"
RAMA="${RAMA:-main}"
cd "$DIR"
como() { sudo -u www-data "$@"; }

ANTERIOR="$(git rev-parse --short HEAD)"
echo "Versión actual: $ANTERIOR"

echo "== Respaldo de la central y de todos los estudios"
como php artisan respaldos:crear

echo "== Modo mantenimiento"
como php artisan down --retry=30 --refresh=15

trap 'echo; echo "Falló la actualización. El sitio sigue en mantenimiento."; echo "Volver atrás: git -C $DIR checkout $ANTERIOR y correr de nuevo composer/npm/optimize, luego php artisan up"' ERR

como git fetch --quiet origin "$RAMA"
como git checkout --quiet "$RAMA"
como git reset --quiet --hard "origin/$RAMA"
echo "Nueva versión: $(git rev-parse --short HEAD)"

como composer install --no-dev --optimize-autoloader --no-interaction
como npm ci --no-audit --no-fund
como npm run build
como php artisan estudios:migrar   # nunca «migrate» solo: crearía las tablas de planilla en la central
como php artisan optimize:clear
como php artisan optimize

systemctl restart serialo-cola
systemctl reload php8.3-fpm
como php artisan up
trap - ERR
echo "Actualizado: $ANTERIOR → $(git rev-parse --short HEAD)"
