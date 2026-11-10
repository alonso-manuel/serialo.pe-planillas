# Multitenant (modo SaaS)

El mismo código funciona de dos maneras, según `PLANILLAS_MODO` en el `.env`:

| Modo              | Dónde                                    | Cómo                                                                                |
| ----------------- | ---------------------------------------- | ----------------------------------------------------------------------------------- |
| `unica` (defecto) | Hosting del cliente (pago único)         | Una base de datos, instalador web y licencia firmada ([licencias.md](licencias.md)) |
| `saas`            | VPS de Serialo.pe (demos y plan mensual) | Una app para todos, **una base de datos por estudio**, elegido por subdominio       |

```
garcia.serialo.pe ──┐                      ┌── base central: estudios, dominios, cola
lopez.serialo.pe  ──┼── una sola app ──────┼── planillas_garcia: empresas, trabajadores, periodos…
demo-perez.…      ──┘                      └── planillas_lopez:  …
```

Se usa [stancl/tenancy](https://tenancyforlaravel.com) v3 (`config/tenancy.php`).

## Por qué una base por estudio (y no `tenant_id`)

- Imposible ver datos de otro estudio por un filtro olvidado: la app solo está conectada a la base del estudio.
- El código de planilla, PLAME, boletas y reportes no cambia: las migraciones de siempre son las de cada estudio.
- Un cliente que pasa a pago único se lleva su base completa; respaldar, restaurar o borrar una demo es una base.

## Qué se separa por estudio

| Recurso  | Cómo                                                                                                                    |
| -------- | ----------------------------------------------------------------------------------------------------------------------- |
| Base     | `DatabaseTenancyBootstrapper`: la conexión por defecto pasa a la base del estudio                                       |
| Sesión   | Vive en la base del estudio; además `VerificarSesionEstudio` cierra una sesión de otro estudio                          |
| Usuarios | Están en la base del estudio: un usuario no existe en otro                                                              |
| Caché    | Etiquetada por estudio (`CacheTenancyBootstrapper`): **requiere Redis** (`CACHE_STORE=redis`)                           |
| Archivos | `storage/estudio-<id>/` (logos)                                                                                         |
| Cola     | En la base central; cada trabajo lleva `tenant_id` y se ejecuta en la base de su estudio. Un solo cron y un solo worker |

`IdentificarEstudio` es un **middleware global**: identifica el estudio antes del enrutamiento. Debe ser así porque
Laravel crea el controlador al reunir los middlewares de la ruta, y el login de Fortify ya abre la sesión en ese
momento; con el middleware solo en el grupo `web`, la sesión nacía conectada a la base central (hay un test de regresión).

Un subdominio desconocido o el dominio central (`serialo.pe`) responde 404, salvo `/up`. Una cuenta suspendida muestra
«Cuenta suspendida» (403) y conserva sus datos.

## Panel central (`admin.serialo.pe`)

Donde el superadmin de Serialo.pe administra las cuentas. Vive en la base central y no es usuario de ningún estudio.

| Función               | Detalle                                                                                                                                                                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Ingreso               | Correo y contraseña + **doble factor obligatorio** (aplicación de autenticación). El primer ingreso obliga a configurarlo; 8 códigos de recuperación de un solo uso                                                                              |
| Cuentas               | Lista con tipo (demo / cliente), estado y vencimiento                                                                                                                                                                                            |
| Nueva cuenta          | Crea la base limpia (catálogos legales) y el administrador, y le **envía un correo con el enlace para crear su contraseña** (24 h). Nadie ve esa contraseña. Si el correo no está configurado, el panel muestra el enlace para entregarlo a mano |
| Acceso                | Reenvía el enlace (olvidó su contraseña o venció)                                                                                                                                                                                                |
| Suspender / reactivar | Suspendida: nadie entra, los datos se conservan                                                                                                                                                                                                  |
| Eliminar              | Borra la cuenta, su base y sus archivos; pide escribir el subdominio                                                                                                                                                                             |
| Actividad             | Auditoría central de todo lo anterior (`auditorias_centrales`, inmutable)                                                                                                                                                                        |

Separación: las rutas del panel (`routes/panel.php`, nombres `panel.*`) solo responden en su dominio y las de los estudios
nunca en el del panel (`SepararPanelYEstudios`). Las sesiones del panel están en `sesiones_panel` (base central). En modo
`unica` el panel no existe. Las páginas usan rutas relativas (`resources/js/lib/panel.ts`) para no fijar el dominio al compilar.

Pendiente (tareas siguientes): exportar la base de una cuenta (con los respaldos) y el vencimiento automático de demos (con los planes).

## Comandos (solo en modo `saas`)

```bash
# Una sola vez: el superadmin del panel central (pide la contraseña)
php artisan superadmin:crear tu-correo@serialo.pe --nombre="Tu nombre"

# Crear un estudio también por terminal (lo mismo que «Nueva cuenta» del panel)
php artisan estudio:crear garcia "Estudio García" admin@garcia.pe --admin="Ana García" --invitar
#   --invitar envía el correo; sin él muestra una contraseña generada (o --clave=...). --tipo=demo --vence=AAAA-MM-DD

# Después de cada actualización: base central y la de todos los estudios
php artisan estudios:migrar
```

⚠ En el VPS **nunca** correr `php artisan migrate` solo: crearía las tablas de planilla en la base central.

Subdominios reservados: `www`, `admin`, `app`, `api`, `mail`, `demo`, `panel`… (ver `GestionEstudios::RESERVADOS`).

## `.env` del VPS

```dotenv
PLANILLAS_MODO=saas
PLANILLAS_DOMINIOS_CENTRALES=serialo.pe,admin.serialo.pe
PLANILLAS_PREFIJO_BD=planillas_
PLANILLAS_DOMINIO_PANEL=admin.serialo.pe
PLANILLAS_URL_ESTUDIO=https://{estudio}.serialo.pe
DB_DATABASE=serialo_central        # base central
DB_USERNAME=...                    # con permiso para CREATE DATABASE
CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

En modo `saas` el instalador web y la licencia firmada se omiten; los límites vendrán del plan de cada estudio.
DNS: registro comodín `*.serialo.pe` al VPS y certificado HTTPS comodín.

## Probar en local

`*.localhost` apunta a la PC, así que sirve sin tocar el DNS:

```bash
mysql -u root -e "CREATE DATABASE serialo_central_prueba"
export PLANILLAS_MODO=saas DB_DATABASE=serialo_central_prueba CACHE_STORE=array PLANILLAS_PREFIJO_BD=prueba_saas_
php artisan estudios:migrar
php artisan estudio:crear garcia "Estudio García" admin@garcia.test --clave='Clave-Segura-2026!'
export PLANILLAS_DOMINIO_PANEL=admin.localhost PLANILLAS_URL_ESTUDIO='http://{estudio}.localhost:8002' SESSION_DRIVER=database MAIL_MAILER=log
php artisan superadmin:crear tu-correo@serialo.pe
php artisan serve --port=8002        # http://garcia.localhost:8002 · panel: http://admin.localhost:8002
```

## Cuentas de prueba

`EstudiosPruebaSeeder` crea cuatro estudios con datos ficticios **distintos** (dos empresas cada uno, sus trabajadores y
un año de planillas), para comprobar que ninguno ve lo de otro:

```bash
php artisan db:seed --class=EstudiosPruebaSeeder      # con PLANILLAS_MODO=saas
```

| Estudio                               | Dirección local              | Usuario             | Empresas                                        |
| ------------------------------------- | ---------------------------- | ------------------- | ----------------------------------------------- |
| Estudio Contable García (prueba)      | http://garcia.localhost:8002 | `admin@garcia.test` | Comercial Los Andes · Textiles Misti            |
| Contadores López & Asociados (prueba) | http://lopez.localhost:8002  | `admin@lopez.test`  | Servicios Huallaga · Agroexportadora Chira      |
| Asesoría Contable Quispe (prueba)     | http://quispe.localhost:8002 | `admin@quispe.test` | Distribuidora Pacífico · Panificadora La Espiga |
| Empresa Torres (prueba)               | http://torres.localhost:8002 | `admin@torres.test` | Transportes Ucayali · Clínica Dental Sonrisa    |

Contraseña de todas: `Demo-Serialo-2026!` (`EstudiosPruebaSeeder::CLAVE`). Solo para pruebas: no crearlas en el VPS
público sin cambiarla. Los datos vienen de `EstudioDemoSeeder` (catálogo de empresas ficticias) e `HistorialDemoSeeder`.

## Tests

`tests/Concerns/ConEstudios.php` activa el modo SaaS con bases SQLite reales por estudio y simula peticiones como en
producción (`nuevaPeticion()`: sin estudio, sesión ni usuario en memoria). `tests/Feature/Multitenant` prueba que un
estudio no ve empresas, usuarios, sesión, caché, archivos ni trabajos en cola de otro. La suite de siempre corre en
modo `unica`.
