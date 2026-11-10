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

Un subdominio desconocido o un dominio central (`serialo.pe`, `admin.serialo.pe`) responde 404, salvo `/up`.
El panel central llega en la siguiente tarea.

## Comandos (solo en modo `saas`)

```bash
# Crear un estudio (cliente o demo): base limpia con catálogos legales + su administrador
php artisan estudio:crear garcia "Estudio García" admin@garcia.pe --admin="Ana García"
#   → https://garcia.serialo.pe · contraseña generada (o --clave=...)

# Después de cada actualización: base central y la de todos los estudios
php artisan estudios:migrar
```

⚠ En el VPS **nunca** correr `php artisan migrate` solo: crearía las tablas de planilla en la base central.

Subdominios reservados: `www`, `admin`, `app`, `api`, `mail`, `demo`… (ver `CrearEstudio::RESERVADOS`).

## `.env` del VPS

```dotenv
PLANILLAS_MODO=saas
PLANILLAS_DOMINIOS_CENTRALES=serialo.pe,admin.serialo.pe
PLANILLAS_PREFIJO_BD=planillas_
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
php artisan serve --port=8002        # http://garcia.localhost:8002
```

## Tests

`tests/Concerns/ConEstudios.php` activa el modo SaaS con bases SQLite reales por estudio y simula peticiones como en
producción (`nuevaPeticion()`: sin estudio, sesión ni usuario en memoria). `tests/Feature/Multitenant` prueba que un
estudio no ve empresas, usuarios, sesión, caché, archivos ni trabajos en cola de otro. La suite de siempre corre en
modo `unica`.
