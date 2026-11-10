# Respaldos

Cada respaldo es la base de datos completa (empresas, trabajadores, planillas, boletas calculadas, auditoría) en un
archivo `.sql.gz`. Lo genera PHP sin `mysqldump` (`App\Services\Respaldo\VolcadoBaseDatos`), así que funciona en
hosting compartido aunque `exec()` esté deshabilitado.

| Qué                | Detalle                                                                                                  |
| ------------------ | -------------------------------------------------------------------------------------------------------- |
| Automático         | `php artisan respaldos:crear` cada día a las 03:00 (hora de Lima), con el cron de `schedule:run`         |
| Manual             | **Administración → Respaldos → Crear respaldo ahora** (solo admin; funciona aunque esté en solo lectura) |
| Dónde              | `storage/app/private/respaldos/respaldo-AAAAMMDD-HHMMSS.sql.gz`                                          |
| Cuántos            | Los de los últimos 14 días (`PLANILLAS_RESPALDO_DIAS`); el más reciente nunca se borra                   |
| Descarga           | Misma pantalla, solo admin. Crear y descargar quedan en la **auditoría** (tipo «Respaldo»)               |
| Fuera del servidor | Opcional: `PLANILLAS_RESPALDO_DISCO` = un disco de `config/filesystems.php` (ver abajo)                  |
| Tablas sin filas   | `sessions`, `sesiones_panel`, `cache`, `cache_locks` (solo estructura: son temporales)                   |

Los logos de las empresas no van en el archivo (están en `storage/app/public/logos`); se vuelven a subir si hiciera falta.

## Instalación única (hosting del cliente)

Los respaldos quedan en el mismo hosting, así que protegen de errores (un periodo borrado, una carga masiva mal hecha),
no de perder el servidor. La pantalla lo dice: **descargar uno cada semana** y guardarlo en otro lugar. Muchos cPanel
tienen además su propia copia diaria.

Para copiar cada respaldo a otro servidor automáticamente, se define un disco y se nombra en el `.env`:

```php
// config/filesystems.php → disks (requiere `composer require league/flysystem-ftp` o `-sftp-v3` o `-aws-s3-v3`)
'respaldos_externos' => ['driver' => 'ftp', 'host' => env('RESPALDO_FTP_HOST'), 'username' => env('RESPALDO_FTP_USUARIO'),
    'password' => env('RESPALDO_FTP_CLAVE'), 'root' => '/respaldos'],
```

```dotenv
PLANILLAS_RESPALDO_DISCO=respaldos_externos
```

Se guarda en `<dominio>/<archivo>` (p. ej. `planillas-estudio-pe/respaldo-20261110-030000.sql.gz`). Si la copia
externa falla, el respaldo local se conserva y el error queda en el log.

## Modo SaaS (VPS)

`respaldos:crear` respalda la **base central** y la de **cada estudio**, cada una en su carpeta:

| Base    | Carpeta local                                 | En el disco externo |
| ------- | --------------------------------------------- | ------------------- |
| Central | `storage/app/private/respaldos/`              | `central/`          |
| Estudio | `storage/estudio-<id>/app/private/respaldos/` | `estudio-<id>/`     |

- El administrador de cada estudio ve y descarga **solo los suyos** (Administración → Respaldos): su disco local es
  su carpeta.
- Un estudio: `php artisan respaldos:crear --estudio=garcia`.
- Si un estudio falla, se registra y se sigue con los demás (el comando termina con error para que el cron lo avise).
- **Panel central → Exportar**: descarga en el momento la base completa de una cuenta (para entregársela al cliente o
  pasarla a su hosting si se cambia a pago único). Queda en la actividad del panel.
- **Eliminar una cuenta** guarda antes un último respaldo en `storage/app/private/respaldos/eliminadas/<id>-<fecha>.sql.gz`.
  Si ese respaldo falla, la cuenta no se elimina. No se borra solo: eliminarlo a mano cuando ya no haga falta (Ley 29733:
  no conservar datos personales más de lo necesario).

**Fuera del servidor (recomendado en el VPS):** además del disco externo opcional, sincronizar la carpeta `storage`
con [rclone](https://rclone.org) a un almacenamiento de otro proveedor (Backblaze B2, S3, Google Drive), con un cron
después de las 03:00:

```cron
30 3 * * * rclone copy /var/www/serialo/storage remoto:serialo-respaldos --include "**/respaldos/**" --max-age 2d
```

## Restaurar

⚠ Restaurar **reemplaza** toda la base: lo que se hizo después del respaldo se pierde. Antes, crear un respaldo del
estado actual.

1. Descargar el `.sql.gz` (o tomarlo de la carpeta).
2. **cPanel / phpMyAdmin:** seleccionar la base → **Importar** → elegir el archivo `.sql.gz` (no hace falta
   descomprimirlo) → Continuar.
3. **Terminal:** `gunzip < respaldo-20261110-030000.sql.gz | mysql -u usuario -p nombre_base`
4. En modo SaaS, la base de un estudio se llama `planillas_<id>` (`PLANILLAS_PREFIJO_BD` + subdominio).
5. Después: `php artisan migrate --force` (instalación única) o `php artisan estudios:migrar` (SaaS) por si el
   respaldo es de una versión anterior, y `php artisan cache:clear`.

Probado: un respaldo de la base local en MariaDB 10.4 restaurado en una base vacía da las mismas filas en las 32 tablas
(los tests restauran uno en SQLite en cada ejecución).
