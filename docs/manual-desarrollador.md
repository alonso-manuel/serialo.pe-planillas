# Serialo Planillas — Manual del desarrollador

Punto de entrada para quien programa en el proyecto. Resume cómo levantarlo, cómo está organizado y cómo se agregan
cosas sin romper las reglas del producto. El detalle de cada tema está en los documentos enlazados; las reglas que
no se rompen están en [CLAUDE.md](../CLAUDE.md).

## Contenido

1. [Documentos del proyecto](#1-documentos-del-proyecto)
2. [Entorno local](#2-entorno-local)
3. [Estructura del código](#3-estructura-del-código)
4. [Cómo fluye un cálculo](#4-cómo-fluye-un-cálculo)
5. [Recetas](#5-recetas)
6. [Tests y calidad](#6-tests-y-calidad)
7. [Git, CI y versiones](#7-git-ci-y-versiones)
8. [Producción](#8-producción)
9. [Problemas conocidos en Windows](#9-problemas-conocidos-en-windows)

---

## 1. Documentos del proyecto

| Documento                                      | Qué responde                                                                                   |
| ---------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| [CLAUDE.md](../CLAUDE.md)                      | Stack, comandos, reglas que no se rompen, glosario                                             |
| [producto.md](producto.md)                     | Qué se vende, a quién, licencias y módulos por versión                                         |
| [roadmap.md](roadmap.md)                       | Qué está hecho y qué sigue                                                                     |
| [reglas-calculo.md](reglas-calculo.md)         | Fórmulas legales, fuentes y puntos ⚠ por validar con el contador                               |
| [formatos-oficiales.md](formatos-oficiales.md) | Formato exacto de PLAME, AFPnet y T-Registro                                                   |
| [convenciones.md](convenciones.md)             | Cómo se escribe el código, el checklist multiempresa y los tests                               |
| [arquitectura.md](arquitectura.md)             | Capas y decisiones por módulo                                                                  |
| [modelo-datos.md](modelo-datos.md)             | Tablas y relaciones                                                                            |
| [multitenant.md](multitenant.md)               | Modo SaaS: una base por estudio, panel central, soporte                                        |
| [licencias.md](licencias.md)                   | Clave Ed25519, estados, solo lectura y límites del plan                                        |
| [instalacion.md](instalacion.md)               | Instalador web en hosting compartido                                                           |
| [vps.md](vps.md)                               | Despliegue SaaS en el VPS                                                                      |
| [docker.md](docker.md)                         | Despliegue con Docker en el VPS (Contabo), detrás del proxy existente                          |
| [respaldos.md](respaldos.md)                   | Respaldos de la base                                                                           |
| [firma-digital.md](firma-digital.md)           | Firma PAdES de documentos                                                                      |
| [manual-usuario.md](manual-usuario.md)         | Cómo se usa el sistema. Su PDF se regenera con `cd tools/manual && npm install && npm run pdf` |
| [videos/guiones.md](videos/guiones.md)         | Videos de demo grabados con Playwright (`tools/videos/`)                                       |

> [arquitectura.md](arquitectura.md) tiene secciones de las primeras semanas que ya no aplican: el actualizador
> automático se descartó y los respaldos usan `VolcadoBaseDatos` propio (no `spatie/laravel-backup`). Ante una
> diferencia, manda el código y los documentos específicos (respaldos.md, licencias.md).

---

## 2. Entorno local

### Requisitos

- PHP 8.3+ con `bcmath` o `gmp`, `intl`, `mbstring`, `pdo_mysql`, `sodium`, `zip`, `gd`, `openssl`. El `composer.json`
  fija `config.platform.php = 8.3.0` (es lo que tienen los hostings de los clientes): no quitarlo.
- Composer 2, Node 22.
- MySQL 8 o MariaDB. En la PC de desarrollo actual, el puerto 3306 es **MariaDB 10.4 de XAMPP** (no MySQL 8): lo que
  funcione ahí debe funcionar también en MySQL 8, que es el de producción y el de CI.

### Primera vez

```bash
composer setup                       # install, .env, key, migrate, npm install, build
php artisan migrate:fresh --seed     # catálogos legales + datos demo ficticios
composer dev                         # servidor, cola y Vite
```

Usuarios demo (solo fuera de producción, `DemoSeeder`): `demo@serialo.pe` (admin) y `asistente@serialo.pe`,
contraseña `demo1234`. `HistorialDemoSeeder` agrega un año de planillas cerradas (lo usan los videos).

### Variables de entorno relevantes

| Variable                                                | Uso                                                                                      |
| ------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `PLANILLAS_MODO`                                        | `unica` (hosting del cliente) o `saas` (VPS, ver multitenant.md)                         |
| `QUEUE_CONNECTION=database`                             | Cálculo masivo y correos; en local lo procesa `composer dev`                             |
| `MAIL_MAILER=log`                                       | En local los correos quedan en `storage/logs`                                            |
| `PLANILLAS_CORREOS_POR_MINUTO`                          | Límite de envío de boletas                                                               |
| `RUC_DRIVER`, `DNI_DRIVER`, `DECOLECTA_TOKEN`           | Consulta de RUC y DNI (`ninguno` para trabajar sin red)                                  |
| `PLANILLAS_RESPALDO_*`                                  | Días de retención y disco externo de respaldos                                           |
| `PLANILLAS_BETA`                                        | Etiqueta «Beta» junto al nombre y sección de beta en los términos (true hasta la 1.0)    |
| `PLANILLAS_LEGAL_RESPONSABLE`, `PLANILLAS_LEGAL_CORREO` | Responsable de los datos y correo ARCO en `/privacidad` (en SaaS, el nombre del estudio) |

El instalador web se omite con `APP_ENV=local|testing` (ver instalacion.md para probarlo).

---

## 3. Estructura del código

```
app/
  Domain/               PHP puro, sin Laravel: Planilla (calculadoras + DTOs), Sunat (PLAME, T-Registro, RUC),
                        Afp (AFPnet), Licencia. Recibe strings y enums, devuelve resultados. 100 % unit test.
  Services/<Modulo>/    Orquestan BD ⇄ dominio, abren transacciones y auditan.
  Http/Controllers/     Delgados: FormRequest + Gate/Policy + Service + Inertia::render o redirect.
  Models/               Eloquent, tablas en español. Concerns: Auditable, PerteneceAEmpresa, TieneEmpresas.
  Enums/                RegimenLaboral, ClaveParametro, TipoConcepto, EstadoPeriodo, SistemaPension…
  Jobs/                 Cálculo masivo por empresa (Bus::batch) y envío de boletas.
database/
  migrations/           Nunca se edita una publicada: se crea otra (también para datos de catálogo).
  seeders/              CatalogosSeeder (parámetros, AFP, conceptos) y DemoSeeder (ficticio).
  data/sunat/           Catálogos oficiales (p. ej. ocupaciones de la tabla 30).
resources/
  js/pages/<modulo>/    Páginas Inertia (Vue 3 + TS). Menú en resources/js/lib/modulos.ts.
  js/components/        ui/ (shadcn-vue) y componentes por módulo.
  views/pdf/            Boletas, constancias, liquidaciones, certificados y reportes (dompdf).
tests/
  Unit/Planilla         Casos de cálculo; los validados por contador NO se modifican.
  Unit/Sunat            Formato exacto de archivos oficiales.
  Feature/<Modulo>      HTTP, permisos y aislamiento multiempresa.
tools/videos/           Grabación automática de los videos de demo (Playwright).
```

---

## 4. Cómo fluye un cálculo

`POST /periodos/{periodo}/calcular` → `CalcularPlanillaPeriodo::ejecutar()`:

1. **Periodo cerrado** → `DomainException` (regla 5).
2. **`VerificadorCalculo`** lista todo lo que falta (parámetros, tasas AFP, conceptos de sistema, AFP sin asignar).
   Si hay algo, `ProblemasDeCalculo` y no se calcula nada.
3. `ParametrosRepositorio::delPeriodo()` arma `ParametrosPeriodo` con los valores **vigentes al último día del mes**
   (y `ParametrosAgrario` si la empresa es agraria).
4. Dentro de una transacción, para el periodo:
    - `GratificacionPeriodo` (julio/diciembre), `LiquidacionesBeneficios::delPeriodo()` (ceses del mes),
      `RetencionQuinta::preparar()` y `PrestamosTrabajador::cuotasDelPeriodo()`.
    - Por cada línea: `HistorialTrabajador::vistaAl()` reconstruye la ficha vigente ese mes → `DatosTrabajador`;
      novedades, gratificación, vacaciones, liquidación y cuota → `EntradaPlanilla`.
    - `CalculadoraPlanilla::calcular()` (puro) → `ResultadoPlanilla`.
    - **Quinta en dos pasadas**: con el resultado se calcula la retención 0605 y se recalcula con ese descuento.
    - Se guardan los conceptos y totales de la línea, el centro de costo vigente y el detalle de la quinta.
5. El periodo pasa a **Calculado**.

Dinero: `BigDecimal` siempre; `Monto::redondear()` a 2 decimales `HalfUp` por concepto; los totales suman conceptos
ya redondeados. Los montos viajan como `string`.

---

## 5. Recetas

### 5.1 Una regla de cálculo nueva

1. Fuente oficial primero (El Peruano, SUNAT, MTPE). Nunca inventar códigos, tasas ni formatos.
2. **Test unitario primero** en `tests/Unit/Planilla` con el caso calculado a mano (y su fuente en el docblock).
3. Implementar en `app/Domain/Planilla` sin tocar BD ni Laravel. Lo que varía por ley va en parámetros (5.2).
4. Conectar en el Service y agregar un test Feature de punta a punta.
5. Documentar en `docs/reglas-calculo.md` con ⚠ lo que debe validar el contador.

### 5.2 Un parámetro legal nuevo

1. Caso en `App\Enums\ClaveParametro` + `etiqueta()`, `descripcion()` y, si no es tasa, `tipo()`.
2. Filas en `ParametroSeeder` (método estático por grupo, con `vigente_desde` y base legal).
3. Migración que inserte esas filas en instalaciones existentes (`firstOrCreate` por clave y vigencia, dentro de
   `Auditoria::sinRegistrar`; si no hay parámetros, la instalación es nueva y no hace nada). Ejemplo:
   `2027_02_18_000001_add_regimen_agrario.php`.
4. Leerlo en `ParametrosRepositorio` y pasarlo por un DTO. Nunca escribir el valor en el código.

### 5.3 Un concepto de la tabla 22

Verificarlo en la tabla 22 vigente (código, afectaciones). Agregarlo a `ConceptoSeeder` (con su cuenta PCGE en
`CUENTAS`) y a una migración para instalaciones existentes. Si el motor lo calcula, `sistema = true` y una constante
en la calculadora.

### 5.4 Un módulo nuevo

`/nuevo-modulo <nombre>` o, a mano, el checklist de [convenciones.md](convenciones.md#multiempresa-cómo-hacer-un-módulo-nuevo):
modelo con `PerteneceAEmpresa` y `Auditable` (+ morph map en `AppServiceProvider` y tipo en `AuditoriaController`),
Policy con `AutorizaPorEmpresa`, rutas con `empresa.activa`, FormRequest, páginas Vue, entrada en `modulos.ts` y
**test de aislamiento** (un asistente de la empresa A no ve, edita ni borra datos de la B → 403).

### 5.5 Un régimen laboral nuevo

Lo que hubo que tocar para el agrario (sirve de lista para construcción civil):

- `RegimenLaboral` (caso y etiqueta) y `resources/js/types/empresa.ts`.
- `ParametrosRepositorio`: `delPeriodo`, `indemnizacion`, `gratificacionPara`, `cts` (los `match` sin `default`
  hacen fallar PHPStan si falta un régimen: es intencional).
- `VacacionesTrabajador::diasDerecho`, `ExportacionTRegistro` (tablas 8, 32 y 33).
- `CalculadoraPlanilla` y los DTOs; servicios de gratificación, CTS, liquidación y costo laboral si cambian.
- Formularios de empresa y trabajador si hay datos propios; tests que cuentan regímenes (`GestionEmpresasTest`).

### 5.6 Un archivo oficial

Generador puro en `app/Domain/Sunat|Afp` (líneas con `|`, CRLF, codificación que pida la entidad) + test en
`tests/Unit/Sunat` con el formato **exacto** + servicio de exportación que registra la descarga y su SHA-256. Solo de
periodos cerrados. Documentar en `formatos-oficiales.md` con el enlace a la fuente.

---

## 6. Tests y calidad

```bash
php artisan test                         # SQLite en memoria
php artisan test --filter=RegimenAgrario
composer test                            # pint --test + phpstan + tests (lo que corre en CI)
composer lint                            # formatea con Pint
npm run check:fix                        # lint y formato del frontend
npm run types:check                      # vue-tsc
```

Antes de dar una tarea por terminada: `composer test` y `npm run types:check` en verde.

- Fechas: `Carbon::setTestNow()` en el `setUp`. Catálogos: `$this->seed([ParametroSeeder::class, ConceptoSeeder::class])`.
- Datos siempre ficticios (DNI 7000xxxx, RUC 200000000xx). Ley 29733: nada real en tests, seeders ni capturas.
- Si falla un caso validado por contador, el error está en el motor: no se toca el test.
- Si un valor esperado calculado a mano no coincide, revisar primero la cuenta a mano (pasó varias veces) y después
  el motor.

---

## 7. Git, CI y versiones

- Una rama por tarea (`feat/<tema>`), commits pequeños en español (`feat(trabajadores): …`, `fix(calculo): …`,
  `docs: …`), merge **fast-forward** a `main` y push. No se reescribe historia publicada sin acuerdo.
- Los commits no llevan líneas de coautoría.
- CI (`.github/workflows/tests.yml`): PHP 8.3, MySQL 8.4 y Node 22. `composer setup` migra contra MySQL y compila
  los assets; `composer ci:check` corre el lint del frontend, `vue-tsc`, Pint, PHPStan y los tests.
- Al terminar una tarea: casilla en `docs/roadmap.md` y, si cambió una regla legal, `docs/reglas-calculo.md`.
- Versiones semánticas (`v1.0.0`) con notas de cambio para clientes.

---

## 8. Producción

| Modo    | Dónde                       | Notas                                                                        |
| ------- | --------------------------- | ---------------------------------------------------------------------------- |
| `unica` | Hosting compartido (cPanel) | Instalador web, cron `schedule:run` cada minuto (cola y respaldos), sin Node |
| `saas`  | VPS (`impulso.cfd`)         | `stancl/tenancy`, una base por estudio, panel central. Despliegue con Docker |

- Tras cada actualización: `php artisan migrate --force` (en SaaS, también `tenants:migrate`).
- Los assets se compilan antes de empaquetar; producción no ejecuta Node.
- Respaldos diarios a las 03:00 y retención de 14 días (respaldos.md).
- Correo SMTP del hosting y `PLANILLAS_CORREOS_POR_MINUTO` según su límite.

---

### Documentos legales

`/terminos` y `/privacidad` (`LegalController`, páginas `resources/js/pages/legal/`) son públicas y se enlazan desde el
login y el pie de página. Los textos describen lo que el sistema hace de verdad (datos, destinatarios, medidas de
seguridad): si se agrega un dato personal nuevo o un servicio externo que lo reciba, actualizar la política y su
`planillas.legal.version`. ⚠ Revisar ambos textos con un abogado antes de vender.

## 9. Problemas conocidos en Windows

| Síntoma                                                                  | Causa y solución                                                                                        |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| PHPStan reporta cientos de `noEnvCallsOutsideOfConfig` en `config/*.php` | Se ejecutó desde `c:\…` en minúscula. Ejecutar desde `C:\Users\…` (la ruta del proyecto con mayúscula). |
| Barras invertidas perdidas en `use App\…` al generar archivos            | Heredocs y `node -e` las escapan: escribir el archivo con un editor o script y revisar los `use`.       |
| MariaDB: «Identifier name is too long» al crear un índice                | Límite de 64 caracteres: dar nombre corto al índice (`$table->unique([...], 'tabla_campo_unique')`).    |
| `Number::fileSize()` u otros helpers fallan                              | Falta la extensión `intl` en el PHP local; no depender de ella en código de producción.                 |
| XAMPP: «MySQL shutdown unexpectedly» (tablas Aria)                       | Mover los `aria_log.*` de `mysql/data` y reparar con `aria_chk -r` sobre el esquema `mysql`.            |
| `migrate` no conecta (2002)                                              | MySQL/MariaDB de XAMPP detenido: iniciarlo desde el panel de XAMPP.                                     |
