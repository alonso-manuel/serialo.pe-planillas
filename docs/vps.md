# VPS (modo SaaS)

Cómo montar Serialo Planillas en el VPS de Serialo.pe para las demos y los clientes de plan mensual. Cada estudio
entra por su subdominio (`garcia.serialo.pe`) y el superadmin por `admin.serialo.pe` (ver [multitenant.md](multitenant.md)).

Servidor de referencia: Ubuntu 24.04, 4 vCPU, 8 GB de RAM, 100 GB SSD. Alcanza con holgura para decenas de estudios.

| Pieza       | Para qué                                                                      | Archivo                           |
| ----------- | ----------------------------------------------------------------------------- | --------------------------------- |
| Nginx       | HTTPS y `*.serialo.pe` → la app                                               | `deploy/vps/nginx.conf`           |
| PHP 8.3-FPM | La aplicación (memoria 512 MB, subidas de 20 MB, opcache, hora de Lima)       | `deploy/vps/instalar.sh`          |
| MySQL 8     | `serialo_central` + una base `planillas_<estudio>` por cuenta                 | `deploy/vps/instalar.sh`          |
| Redis       | Caché separada por estudio (el modo SaaS la exige)                            | `deploy/vps/instalar.sh`          |
| Cola        | Servicio permanente `serialo-cola` (cálculo masivo, correos)                  | `deploy/vps/serialo-cola.service` |
| Cron        | `schedule:run` cada minuto: respaldos 03:00, avisos de vencimiento 08:00      | `deploy/vps/instalar.sh`          |
| `.env`      | Modo SaaS, dominios, base, Redis, correo                                      | `deploy/vps/env.saas.ejemplo`     |
| Actualizar  | Respaldo → mantenimiento → código → migraciones de todas las bases → en línea | `deploy/vps/actualizar.sh`        |

## 1. DNS

En el administrador del dominio `serialo.pe`, un registro **comodín**:

| Tipo | Nombre | Valor      |
| ---- | ------ | ---------- |
| A    | `*`    | IP del VPS |

El dominio raíz `serialo.pe` (la web) no cambia: puede seguir en su hosting. `admin.serialo.pe` y cada estudio quedan
cubiertos por el comodín.

⚠ El certificado HTTPS comodín (`*.serialo.pe`) **solo** se obtiene probando que controlas el DNS (reto DNS-01). Los
scripts usan **Cloudflare** (gratis; se cambian los nameservers del dominio a Cloudflare y se renueva solo). Con otro
proveedor sin API habría que renovar a mano cada 90 días.

## 2. Primer ingreso al VPS

```bash
ssh root@IP_DEL_VPS
apt-get update && apt-get install -y git
```

Recomendado antes de seguir: entrar con llave SSH y desactivar la contraseña (`PasswordAuthentication no` en
`/etc/ssh/sshd_config`). Hazlo con una segunda sesión abierta para no quedarte fuera.

## 3. Llave de despliegue (el repositorio es privado)

```bash
install -d -o www-data -g www-data -m 700 /var/www/.ssh
sudo -u www-data ssh-keygen -t ed25519 -N "" -f /var/www/.ssh/id_ed25519 -C "vps-serialo"
cat /var/www/.ssh/id_ed25519.pub
```

Copia esa línea en GitHub → repositorio → **Settings → Deploy keys → Add deploy key** (solo lectura, sin «Allow write
access»). Luego:

```bash
sudo -u www-data ssh -o StrictHostKeyChecking=accept-new -T git@github.com   # «successfully authenticated»
install -d -o www-data -g www-data /var/www/serialo
sudo -u www-data git clone git@github.com:alonso-manuel/serialo.pe-planillas.git /var/www/serialo
```

## 4. Instalar todo

```bash
sudo bash /var/www/serialo/deploy/vps/instalar.sh
```

Instala paquetes, cortafuegos (solo 22, 80 y 443), fail2ban, actualizaciones de seguridad automáticas, PHP, Redis,
MySQL (usuario `serialo` con permiso **solo** sobre `serialo_central` y `planillas_*`; su clave queda en
`/root/.serialo-db`), crea el `.env`, compila, migra la central, configura Nginx, la cola y el cron. Se puede volver a
correr sin romper nada. Otro dominio: `DOMINIO=midominio.pe sudo -E bash …`.

## 5. Certificado HTTPS comodín

En Cloudflare: **My Profile → API Tokens → Create Token → «Edit zone DNS»**, limitado a la zona `serialo.pe`.

```bash
install -d -m 700 /root/.secrets
echo "dns_cloudflare_api_token = EL_TOKEN" > /root/.secrets/cloudflare.ini
chmod 600 /root/.secrets/cloudflare.ini

certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d '*.serialo.pe' --cert-name serialo.pe -m tu-correo@serialo.pe --agree-tos -n \
  --deploy-hook "systemctl reload nginx"

nginx -t && systemctl reload nginx
```

Se renueva solo (`systemctl list-timers | grep certbot`).

## 6. Correo y superadmin

Completa `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` en `/var/www/serialo/.env` (sin correo el panel muestra el
enlace de invitación para entregarlo a mano). Luego:

```bash
cd /var/www/serialo
sudo -u www-data php artisan optimize
sudo -u www-data php artisan superadmin:crear tu-correo@serialo.pe --nombre="Tu nombre"
```

Entra a `https://admin.serialo.pe`, configura el doble factor y crea la primera cuenta.

⚠ No correr `EstudiosPruebaSeeder` en el VPS: crea cuentas con una contraseña conocida.

## 7. Respaldos fuera del servidor

Los respaldos diarios quedan en el VPS ([respaldos.md](respaldos.md)). Para tener copia en otro proveedor
(Backblaze B2, S3, Google Drive):

```bash
rclone config                       # crea el remoto, p. ej. «b2»
crontab -e                          # como root:
30 3 * * * rclone copy /var/www/serialo/storage b2:serialo-respaldos --include "**/respaldos/**" --max-age 2d
```

## 8. Actualizar a una versión nueva

```bash
sudo bash /var/www/serialo/deploy/vps/actualizar.sh
```

Respalda todas las bases, pone el sitio en mantenimiento, baja `main`, instala dependencias, compila, corre
`estudios:migrar` (central + cada estudio), limpia cachés, reinicia la cola y vuelve a ponerlo en línea. Si algo falla,
el sitio queda en mantenimiento y el script dice cómo volver a la versión anterior.

⚠ Nunca `php artisan migrate` solo en el VPS: crearía las tablas de planilla en la base central.

## Comprobaciones

| Qué                   | Cómo                                                                    |
| --------------------- | ----------------------------------------------------------------------- |
| Sitio                 | `curl -I https://admin.serialo.pe/up` → 200                             |
| Cola                  | `systemctl status serialo-cola`                                         |
| Cron                  | `crontab -u www-data -l` y `sudo -u www-data php artisan schedule:list` |
| Respaldos             | `ls /var/www/serialo/storage/estudio-*/app/private/respaldos/`          |
| Errores               | `tail -f /var/www/serialo/storage/logs/laravel-*.log`                   |
| Subdominio sin cuenta | `https://noexiste.serialo.pe` → 404                                     |
