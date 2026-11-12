# Despliegue con Docker (VPS, modo SaaS)

Serialo Planillas en contenedores, en un VPS que ya tiene otras aplicaciones (Contabo). Todo vive en su propia red de
Docker; solo el contenedor `web` se publica, y únicamente en `127.0.0.1:8090`. El **proxy que ya tiene el servidor**
atiende HTTPS y los subdominios `*.impulso.cfd` y reenvía a ese puerto: no se toman los puertos 80/443 ni se instalan
PHP, MySQL o Redis en el sistema.

```
Internet ──HTTPS──► proxy del servidor (Nginx/Caddy/Traefik, ya existe)
                         │  *.impulso.cfd → 127.0.0.1:8090 (conserva Host, X-Forwarded-*)
                         ▼
                  ┌─ web (Nginx) ──fastcgi──► app (PHP-FPM) ─┐
   red «serialo»  │                cola (queue:work)          ├─► mysql 8.4 · redis 7.4
                  └─               programador (schedule:work)┘
   volúmenes: storage (respaldos, logos, licencias) · mysql (bases)
```

| Archivo                                 | Para qué                                                             |
| --------------------------------------- | -------------------------------------------------------------------- |
| `Dockerfile`                            | Imagen `app` (PHP-FPM con el código y los assets) e imagen `web`     |
| `docker-compose.yml`                    | Servicios, red, volúmenes y el puerto `127.0.0.1:8090`               |
| `deploy/docker/serialo.env.ejemplo`     | Variables (copiar a `serialo.env`, que no va al repositorio)         |
| `deploy/docker/nginx.conf`              | Nginx interno                                                        |
| `deploy/docker/php.ini`, `php-fpm.conf` | Límites de PHP y del pool (ajustar `pm.max_children` a la RAM)       |
| `deploy/docker/entrypoint.sh`           | Crea carpetas del volumen y cachea configuración y rutas al arrancar |
| `deploy/docker/mysql-permisos.sh`       | Permiso para crear las bases `planillas_*` de cada estudio           |
| `.dockerignore`                         | Deja fuera de la imagen docs, videos, herramientas y tests           |

## 0. Probar en la PC (Docker Desktop)

Antes de tocar el VPS se puede levantar todo en la PC. `*.localhost` apunta a la propia PC en el navegador.

```bash
cp deploy/docker/serialo.env.ejemplo serialo.env
# En serialo.env: APP_KEY y claves de MySQL, y para local:
#   APP_URL=http://admin.localhost:8090
#   PLANILLAS_DOMINIOS_CENTRALES=localhost,admin.localhost
#   PLANILLAS_DOMINIO_PANEL=admin.localhost
#   PLANILLAS_URL_ESTUDIO=http://{estudio}.localhost:8090
#   SESSION_SECURE_COOKIE=false   MAIL_MAILER=log
docker compose build && docker compose up -d
docker compose exec app php artisan estudios:migrar
docker compose exec app php artisan estudio:crear prueba "Estudio de Prueba" admin@prueba.test --clave='Clave-Segura-2026!'
# Datos de demo (solo en pruebas): catálogos y empresas ficticias
docker compose exec app php artisan tenants:seed --tenants=prueba --class=CatalogosSeeder
docker compose exec app php artisan tenants:seed --tenants=prueba --class=DemoSeeder
```

Abrir `http://prueba.localhost:8090` (demo@serialo.pe / demo1234). Al terminar: `docker compose down` (conserva los
volúmenes) o `docker compose down -v` para borrarlo todo **solo en la PC**.

Verificado el 12/11/2026 en Docker Desktop 29.9: ingreso, cálculo de planilla, PDF de boletas, respaldo de la base
central y del estudio, cola y programador. La cola muestra errores «Table jobs doesn't exist» solo hasta que se corre
`estudios:migrar` la primera vez.

## 1. Antes de empezar (reconocimiento, sin cambiar nada)

```bash
cat /etc/os-release | head -3; nproc; free -h; df -h /
docker --version; docker compose version
sudo ss -tlnp | grep -E ':(80|443|3306|6379|8090)\b'
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}'
ls /etc/nginx/sites-enabled /etc/caddy /etc/traefik 2>/dev/null
```

- Quién atiende 80/443 define el paso 4. Si el 8090 está ocupado, usar otro con `SERIALO_PUERTO` (paso 3).
- RAM: la app con MySQL y Redis usa ~1.5 GB con pocos estudios.

## 2. DNS y certificado

En el DNS de `impulso.cfd`: registro **A** `*` → IP del VPS (cubre `admin.` y cada estudio). El certificado comodín
`*.impulso.cfd` solo se obtiene con el reto DNS-01, que necesita la API del proveedor de DNS: lo más simple es
**Cloudflare** (gratis). Ver el paso 4 según el proxy.

## 3. Primer despliegue

```bash
# Código (repositorio privado: llave de despliegue de solo lectura, ver docs/vps.md §3)
sudo mkdir -p /opt/serialo && sudo chown "$USER" /opt/serialo
git clone git@github.com:<usuario>/serialo-planillas.git /opt/serialo
cd /opt/serialo

# Variables: copiar la plantilla y completar claves, correo y dominio
cp deploy/docker/serialo.env.ejemplo serialo.env
chmod 600 serialo.env
nano serialo.env      # APP_KEY, DB_PASSWORD = MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD, MAIL_*

# Construir y levantar (la primera vez tarda unos minutos)
docker compose build
docker compose up -d
docker compose ps     # todos «running»; mysql «healthy»

# Base central, superadmin y prueba
docker compose exec app php artisan estudios:migrar
docker compose exec app php artisan superadmin:crear tu-correo@dominio.pe --nombre="Tu nombre"
curl -sI -H 'Host: admin.impulso.cfd' http://127.0.0.1:8090 | head -1   # 200 o 302
```

- `APP_KEY`: `echo "base64:$(openssl rand -base64 32)"`. **No cambiarla nunca después**: cifra los certificados de
  firma digital y las sesiones.
- Otro puerto: `SERIALO_PUERTO=8091 docker compose up -d` (o ponerlo en un archivo `.env` junto al compose).

## 4. Conectar el proxy del servidor

El proxy debe **conservar el Host** y enviar `X-Forwarded-Proto` y `X-Forwarded-For` (la app confía en ellos por
`TRUSTED_PROXIES`, que en la plantilla son las redes internas de Docker).

**Si el servidor usa Nginx** (certificado con `certbot` + `python3-certbot-dns-cloudflare`):

```nginx
server {
    listen 443 ssl; listen [::]:443 ssl; http2 on;
    server_name *.impulso.cfd;
    ssl_certificate     /etc/letsencrypt/live/impulso.cfd/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/impulso.cfd/privkey.pem;
    client_max_body_size 20M;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_read_timeout 120s;
        proxy_buffer_size 32k;          # cabeceras grandes de Laravel (si no: 502 en el login)
        proxy_buffers 16 32k;
    }
}
server { listen 80; listen [::]:80; server_name *.impulso.cfd; return 301 https://$host$request_uri; }
```

**Si el servidor usa Caddy** (necesita el módulo `caddy-dns/cloudflare` para el comodín):

```caddyfile
*.impulso.cfd {
    tls { dns cloudflare {env.CLOUDFLARE_API_TOKEN} }
    reverse_proxy 127.0.0.1:8090
}
```

Probar: `https://admin.impulso.cfd` muestra el ingreso del panel central.

## 5. Actualizar a una versión nueva

```bash
cd /opt/serialo
docker compose exec app php artisan respaldos:crear     # respaldo de todas las bases antes de tocar nada
git pull
docker compose build
docker compose up -d                                    # reemplaza los contenedores (segundos sin servicio)
docker compose exec app php artisan estudios:migrar     # base central y la de cada estudio
```

Nunca correr `php artisan migrate` solo: crearía las tablas de planilla en la base central. Más adelante, con CI/CD,
GitHub Actions construirá las imágenes y el servidor solo hará `docker compose pull && up -d` y las migraciones.

## 6. Operación

| Tarea                    | Comando                                                                                               |
| ------------------------ | ----------------------------------------------------------------------------------------------------- |
| Ver registros            | `docker compose logs -f app cola programador`                                                         |
| Crear un estudio         | `docker compose exec app php artisan estudio:crear garcia "Estudio García" admin@garcia.pe --invitar` |
| Respaldo ahora           | `docker compose exec app php artisan respaldos:crear`                                                 |
| Copiar respaldos al host | `docker compose cp app:/var/www/html/storage/app/private/respaldos ./respaldos`                       |
| Consola de MySQL         | `docker compose exec mysql mysql -uroot -p`                                                           |
| Reiniciar todo           | `docker compose restart`                                                                              |

- **Respaldos fuera del servidor:** los diarios quedan en el volumen `storage`. Copiarlos a otro lugar (rclone a un
  almacenamiento externo o `PLANILLAS_RESPALDO_DISCO`, ver [respaldos.md](respaldos.md)); el volumen `mysql` no
  reemplaza a un respaldo.
- **Nunca** `docker compose down -v`: borra los volúmenes (bases y respaldos).
