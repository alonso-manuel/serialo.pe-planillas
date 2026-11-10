#!/usr/bin/env bash
# Prepara un VPS Ubuntu 24.04 limpio para Serialo Planillas en modo SaaS (ver docs/vps.md).
# Se corre UNA vez, como root:   sudo bash deploy/vps/instalar.sh
# Es idempotente: si se vuelve a correr no rompe lo ya instalado.
set -euo pipefail

DOMINIO="${DOMINIO:-impulso.cfd}"          # los estudios serán <estudio>.$DOMINIO y el panel admin.$DOMINIO
DIR="${DIR:-/var/www/serialo}"            # carpeta de la aplicación
REPO="${REPO:-git@github.com:alonso-manuel/serialo.pe-planillas.git}"
DB_USUARIO="${DB_USUARIO:-serialo}"
PHP=8.3

[ "$(id -u)" -eq 0 ] || { echo "Correr como root (sudo)."; exit 1; }
paso() { printf '\n\033[1;34m== %s\033[0m\n' "$1"; }

paso "Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -yq software-properties-common curl git unzip ufw fail2ban unattended-upgrades \
    nginx mysql-server redis-server certbot python3-certbot-dns-cloudflare rclone \
    php$PHP-fpm php$PHP-cli php$PHP-mysql php$PHP-mbstring php$PHP-xml php$PHP-gd php$PHP-zip \
    php$PHP-curl php$PHP-intl php$PHP-bcmath php$PHP-redis php$PHP-sqlite3 php$PHP-opcache

if ! command -v node >/dev/null || [ "$(node -v | cut -c2- | cut -d. -f1)" -lt 22 ]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -yq nodejs
fi
command -v composer >/dev/null || curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

paso "Cortafuegos (solo SSH, HTTP y HTTPS) y actualizaciones de seguridad automáticas"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable
dpkg-reconfigure -f noninteractive unattended-upgrades
systemctl enable --now fail2ban

paso "PHP $PHP"
cat > /etc/php/$PHP/fpm/conf.d/90-serialo.ini <<'INI'
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 22M
max_execution_time = 120
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
date.timezone = America/Lima
INI
cp /etc/php/$PHP/fpm/conf.d/90-serialo.ini /etc/php/$PHP/cli/conf.d/90-serialo.ini
sed -i 's/^pm.max_children = .*/pm.max_children = 24/; s/^pm.start_servers = .*/pm.start_servers = 4/; s/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/; s/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' /etc/php/$PHP/fpm/pool.d/www.conf
systemctl restart php$PHP-fpm

paso "Redis (caché por estudio)"
sed -i 's/^# *maxmemory .*/maxmemory 512mb/; s/^# *maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
systemctl enable --now redis-server && systemctl restart redis-server

paso "MySQL: base central y usuario con permiso solo sobre serialo_central y planillas_*"
CLAVE_ARCHIVO=/root/.serialo-db
if [ ! -f "$CLAVE_ARCHIVO" ]; then
    openssl rand -base64 24 | tr -d '/+=' > "$CLAVE_ARCHIVO"
    chmod 600 "$CLAVE_ARCHIVO"
fi
DB_CLAVE="$(cat "$CLAVE_ARCHIVO")"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS serialo_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USUARIO'@'localhost' IDENTIFIED BY '$DB_CLAVE';
ALTER USER '$DB_USUARIO'@'localhost' IDENTIFIED BY '$DB_CLAVE';
GRANT ALL PRIVILEGES ON serialo_central.* TO '$DB_USUARIO'@'localhost';
GRANT ALL PRIVILEGES ON \`planillas\\_%\`.* TO '$DB_USUARIO'@'localhost';
FLUSH PRIVILEGES;
SQL

paso "Código en $DIR"
mkdir -p "$DIR"
# Carpetas del usuario www-data (home = /var/www) para la caché de composer/npm y la llave de GitHub.
install -d -o www-data -g www-data -m 700 /var/www/.ssh
install -d -o www-data -g www-data /var/www/.npm /var/www/.cache /var/www/.config
chown www-data:www-data "$DIR"
if [ ! -d "$DIR/.git" ]; then
    if ! sudo -u www-data git ls-remote "$REPO" >/dev/null 2>&1; then
        echo "www-data no puede leer $REPO. Crea la llave de despliegue (docs/vps.md, paso 3) y vuelve a correr el script."
        exit 1
    fi
    sudo -u www-data git clone "$REPO" "$DIR"
fi

if [ ! -f "$DIR/.env" ]; then
    sed -e "s/{DOMINIO}/$DOMINIO/g" -e "s/{DB_USUARIO}/$DB_USUARIO/" -e "s/{DB_CLAVE}/$DB_CLAVE/" \
        "$DIR/deploy/vps/env.saas.ejemplo" > "$DIR/.env"
    chown www-data:www-data "$DIR/.env"
    chmod 640 "$DIR/.env"
    NUEVO_ENV=1
fi

cd "$DIR"
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
[ "${NUEVO_ENV:-0}" = 1 ] && sudo -u www-data php artisan key:generate --force
sudo -u www-data npm ci --no-audit --no-fund
sudo -u www-data npm run build
sudo -u www-data php artisan estudios:migrar
sudo -u www-data php artisan storage:link || true
sudo -u www-data php artisan optimize

paso "Nginx"
sed "s/{DOMINIO}/$DOMINIO/g; s#{DIR}#$DIR#g; s/{PHP}/$PHP/g" deploy/vps/nginx.conf > /etc/nginx/sites-available/serialo
ln -sf /etc/nginx/sites-available/serialo /etc/nginx/sites-enabled/serialo
rm -f /etc/nginx/sites-enabled/default
if [ ! -f "/etc/letsencrypt/live/$DOMINIO/fullchain.pem" ]; then
    echo "Falta el certificado HTTPS: Nginx queda sin activar hasta el paso 5 de docs/vps.md (certbot)."
else
    nginx -t && systemctl reload nginx
fi

paso "Cola (servicio permanente) y cron del programador"
sed "s#{DIR}#$DIR#g" deploy/vps/serialo-cola.service > /etc/systemd/system/serialo-cola.service
systemctl daemon-reload
systemctl enable --now serialo-cola
echo "* * * * * cd $DIR && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

paso "Listo"
echo "Usuario MySQL: $DB_USUARIO  (clave en $CLAVE_ARCHIVO, ya escrita en $DIR/.env)"
echo "Siguiente: certificado comodín (paso 5) y superadmin (paso 6) — docs/vps.md"
