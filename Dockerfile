# Serialo Planillas en contenedores (modo SaaS en el VPS). Ver docs/docker.md.
#   app  → PHP-FPM con el código (también corre la cola y el programador)
#   web  → Nginx interno que sirve /public y pasa PHP a «app»
# El TLS y los subdominios *.impulso.cfd los atiende el proxy del servidor, delante de «web».

ARG PHP_VERSION=8.3

# ---------------------------------------------------------------- base PHP
FROM php:${PHP_VERSION}-fpm-bookworm AS php-base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath gd intl opcache pcntl pdo_mysql redis zip \
    && apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/90-serialo.ini
COPY deploy/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-serialo.conf
WORKDIR /var/www/html

# ---------------------------------------------------------------- dependencias y assets
FROM php-base AS build

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# Wayfinder (plugin de Vite) ejecuta artisan: el autoload debe existir antes de compilar.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

# ---------------------------------------------------------------- app (PHP-FPM, cola y programador)
FROM php-base AS app

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY deploy/docker/entrypoint.sh /usr/local/bin/serialo-entrypoint
RUN chmod +x /usr/local/bin/serialo-entrypoint

USER www-data
ENTRYPOINT ["serialo-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------- web (Nginx interno)
FROM nginx:1.27-alpine AS web

COPY deploy/docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public
