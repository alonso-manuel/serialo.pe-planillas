# Instalación en el hosting del cliente

Guía del instalador web (`/instalar`). Pensado para hosting compartido con cPanel.

## Antes de empezar

1. Subir y descomprimir el paquete del sistema en el hosting (la carpeta `public/` debe ser la raíz del dominio o subdominio).
2. En cPanel → «Bases de datos MySQL»: crear una base de datos **vacía** y un usuario con todos los privilegios sobre ella.
3. En cPanel → «Seleccionar versión de PHP»: PHP 8.3 o superior con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `dom`, `xml`, `fileinfo`, `ctype`, `tokenizer` y `curl`.

## Pasos del instalador

Al abrir el dominio, cualquier página lleva a `/instalar`.

| Paso                       | Qué hace                                                                                                                                                                                                                       |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1. Requisitos              | Revisa PHP, extensiones y permisos de escritura (`storage`, `bootstrap/cache`, `.env`). Pide el **código de instalación** del archivo `storage/app/codigo-instalacion.txt`, que solo puede leer quien tiene acceso al hosting. |
| 2. Base de datos           | Prueba la conexión antes de seguir. Rechaza una base que ya tenga tablas del sistema.                                                                                                                                          |
| 3. Sistema y administrador | Nombre del sistema, dirección web, primer administrador (contraseña: 12+ caracteres con mayúsculas, minúsculas, números y símbolos), SMTP opcional y la casilla de datos demo (desmarcada por defecto).                        |
| 4. Listo                   | Muestra el comando del cron para cPanel.                                                                                                                                                                                       |

Al instalar se ejecutan las migraciones, se cargan los catálogos legales (`CatalogosSeeder`: parámetros, AFP, tasas AFP, conceptos de la tabla 22), se crea el administrador (queda en la auditoría) y se escribe el `.env` definitivo (producción, sesión/caché/cola en base de datos). El `.env` solo se escribe si todo lo anterior salió bien.

Al terminar se crea `storage/app/instalado.json` y se borra el código. Desde ese momento `/instalar` responde 404.

## Cron (obligatorio)

En cPanel → «Trabajos de cron», una vez por minuto:

```
php /ruta/del/sistema/artisan schedule:run >> /dev/null 2>&1
```

Procesa la cola: cálculo masivo y envío de boletas por correo. Algunos hostings piden la ruta completa de PHP (`/usr/local/bin/php`).

## Desarrollo y tests

El instalador se omite cuando `APP_ENV` es `local` o `testing` (`config/instalador.php`). Para probarlo en local sin tocar el `.env`:

```bash
INSTALADOR_OMITIR=false php artisan serve --port=8001
```

No completes el último paso en local: escribiría el `.env` de desarrollo. Los tests (`tests/Feature/Instalador`) usan una carpeta temporal y una base SQLite aparte.

## Reinstalar

Solo con una base de datos vacía: borrar `storage/app/instalado.json` y el `.env`, y volver a abrir el dominio.
