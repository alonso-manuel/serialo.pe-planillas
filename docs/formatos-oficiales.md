# Formatos oficiales (SUNAT y AFP)

> Los archivos se generan en `app/Domain/Sunat/*` y `app/Domain/Afp/*`. Cada formato tiene tests con el **texto exacto** esperado.
> Antes de producción, cada archivo debe importarse sin errores en el aplicativo oficial (PLAME en SOL, AFPnet).

## PLAME (formulario 0601)

Nombre de archivo: `0601` + `AAAA` + `MM` + `RUC` + `.extensión` → `060120261020000000001.rem`
(`NombreArchivoPlame::para`). Campos separados por `|` y la línea termina en `|`. Fin de línea CRLF ⚠ (verificar si el aplicativo exige CRLF).

| Archivo         | Contenido                                 | Formato de línea                                                        | Estado                                    |
| --------------- | ----------------------------------------- | ----------------------------------------------------------------------- | ----------------------------------------- |
| `.rem`          | Remuneraciones por concepto               | `tipoDoc\|numDoc\|codConcepto\|montoDevengado\|montoPagado\|`           | ✔ verificado con exportación real         |
| `.jor`          | Jornada                                   | `tipoDoc\|numDoc\|horasOrd\|minOrd\|horasSobretiempo\|minSobretiempo\|` | ✔ verificado con exportación real         |
| `.snl`          | Días subsidiados y no laborados (est. 15) | `tipoDoc\|numDoc\|tipoSuspension\|dias\|`                               | estructura confirmada; ⚠ códigos tabla 21 |
| `.toc`          | Otras condiciones (est. 26)               | `tipoDoc\|numDoc\|aseguraTuPension\|masVida\|fdsa\|domiciliado\|`       | ✔ anexo SUNAT 223-2017                    |
| `.4ta` / `.ps4` | Cuarta categoría (v1.1)                   | ⚠                                                                       | pendiente                                 |

Ejemplos (`.rem`):

```
01|12345678|0121|1230.00|1230.00|
01|12345678|0606|16.85|16.85|
```

Tipos de documento (tabla 3): `01` DNI, `04` carné de extranjería, `07` pasaporte ⚠ verificar lista completa.
Tipos de suspensión `.snl` (tabla 21): catálogo `tipos_suspension` (cargado en la migración). La columna `uso` indica
el código de las faltas (`07` S.P. Falta no justificada) y los elegibles para días subsidiados (`21` incapacidad temporal,
`22` maternidad; se elige por trabajador en la grilla) y la licencia sin goce (`05`, columna «Lic. s/g» de la grilla).
⚠ Verificar códigos contra la tabla 21 vigente.

Contenido que genera `App\Services\Sunat\ExportacionPlame` (solo periodos **cerrados**):

- `.rem`: todos los conceptos calculados (ingresos, aportes y descuentos), devengado = pagado. Los montos en cero no se declaran ⚠.
- `.jor`: horas ordinarias = días laborados × 8 ⚠ (jornada de 48 h); sobretiempo = HE 25 % + HE 35 % en horas y minutos.
- `.snl`: faltas (07), licencias sin goce (05) y días subsidiados (21/22) solo de quien los tiene. Si nadie tiene, el archivo no se genera.
- `.toc`: una línea por trabajador con Asegura tu Pensión = 0 (SUNAT lo recaudó hasta mayo 2017), +Vida = 0,
  FDSA vacío (solo artistas, tipo 56, y tipo 98) y Domiciliado = 1.

Pantalla **Archivos oficiales → PLAME**: zip de una empresa o de todas las visibles (una carpeta `RUC Razón social/` por empresa).
Cada descarga queda en `exportaciones` con nombre, tamaño y SHA-256 de cada archivo.

Fuentes: anexo SUNAT 223-2017 (estructura 26, `.toc`), "Guía de importación de archivos PLAME" y tablas paramétricas publicadas por SUNAT (descargar la versión vigente y guardarla fuera del repo; solo se versionan los catálogos ya transcritos como seeders).

## AFPnet (semana 5)

Fuente: «Guía rápida para Empleadores» de AFPnet, actualización 20.08.26, ítem 5.1 (la guía recomienda la carga en TXT).
Generador: `App\Domain\Afp\GeneradorAfpnet`; servicio: `App\Services\Sunat\ExportacionAfpnet`.

**Un solo archivo por empresa y mes con todas las AFP** (AFPnet arma una planilla por AFP). Texto de ancho fijo,
**141 caracteres por línea**, una línea por trabajador en AFP (los de ONP no van). Nombre: `AFPNET AAAA-MM RUC.txt`.

| Ítem  | Campo                              | Posición | Formato       | Valor que genera el sistema                                                      |
| ----- | ---------------------------------- | -------- | ------------- | -------------------------------------------------------------------------------- |
| 1     | Secuencia                          | 1–5      | 9(5)          | correlativo                                                                      |
| 2     | CUSPP                              | 6–17     | A(12)         | de la ficha (opcional)                                                           |
| 3     | Tipo de documento                  | 18       | 9(1)          | 0 DNI, 1 carné de extranjería, 4 pasaporte                                       |
| 4     | Número de documento                | 19–38    | A(20)         |                                                                                  |
| 5–7   | Apellido paterno, materno, nombres | 39–98    | A(20) c/u     | MAYÚSCULAS sin tildes, con Ñ ⚠                                                   |
| 8     | Relación laboral vigente           | 99       | A(1)          | S                                                                                |
| 9     | Inicio de RL en el mes             | 100      | A(1)          | S si ingresó en el mes                                                           |
| 10    | Cese de RL en el mes               | 101      | A(1)          | S si cesó en el mes                                                              |
| 11    | Excepción de aportar               | 102      | A(1)          | blanco = aporta; sin remuneración: U (subsidio), L (licencia sin goce), O (otro) |
| 12    | Remuneración asegurable            | 103–111  | 9(7).9(2)     | suma de ingresos afectos a AFP                                                   |
| 13–15 | Aportes voluntarios                | 112–138  | 9(7).9(2) c/u | 0                                                                                |
| 16    | Tipo de trabajo                    | 139      | A(1)          | N (dependiente normal)                                                           |
| 17    | AFP                                | 140–141  | A(2)          | `afps.codigo_afpnet`: HA, IN, PR, RI (blanco si no se conoce)                    |

- Montos sin punto decimal: 600.20 → `000060020`. Fin de línea CRLF ⚠.
- **Codificación Windows-1252 (ANSI)** ⚠: la guía no la indica, pero en un archivo de ancho fijo cada carácter debe
  ocupar 1 byte (en UTF-8 la Ñ ocupa 2 y correría las posiciones). Si AFPnet rechazara la Ñ, cambiarla por N en
  `GeneradorAfpnet::nombre()`.
- Excepciones J/I (pensionado) y P (inicio después del cierre) aún no se generan.

## T-Registro (v1.1)

**SUNAT → T-Registro** (empresa activa, rango de fechas): altas = ingreso dentro del rango, bajas = cese dentro del
rango. Revisa los datos que exige SUNAT por trabajador y genera un zip para validar en el **PVS T-Registro** y subir
en SOL → T-Registro → Carga masiva. Código: `App\Domain\Sunat\TRegistro\GeneradorTRegistro` (puro) y
`App\Services\Sunat\ExportacionTRegistro`.

Fuentes: [manual del PVS T-Registro](https://www.sunat.gob.pe/orientacion/pvs/registro/manual/mu-0441-pvs-T-Registro_RM_170_2023_TR.pdf)
(actualizado al 18/09/2023) y el [Excel de tablas paramétricas](https://orientacion.sunat.gob.pe/node/1444) (09/11/2026).

| Archivo        | Estructura | Contenido                                                                                                                                                                                                               |
| -------------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `RP_<RUC>.ide` | E04 (41)   | Datos personales: documento, nacimiento, nombres, sexo, nacionalidad, celular, correo; dirección vacía (opcional)                                                                                                       |
| `RP_<RUC>.tra` | E05 (23)   | Régimen laboral (t. 33), situación educativa (t. 9), ocupación (t. 30), discapacidad, CUSPP, contrato (t. 12), sindicalizado, periodicidad, sueldo, situación especial (t. 35), tipo de pago (t. 16), categoría (t. 24) |
| `RP_<RUC>.per` | E11 (9)    | Alta: periodo laboral, tipo de trabajador (t. 8), régimen de salud (t. 32) y pensionario (t. 11). Baja: periodo laboral con fecha de fin y motivo (t. 17)                                                               |
| `RP_<RUC>.est` | E17 (5)    | RUC y código del establecimiento donde labora                                                                                                                                                                           |

- Campos separados por «|», un «|» al final de cada línea, sin espacios; un campo que no aplica va vacío («||»).
  Fechas `dd/mm/aaaa`. Fin de línea CRLF. Texto en **Windows-1252** (tildes y eñes de los nombres) ⚠.
- Códigos: régimen laboral 01 general / 17 pequeña / 16 micro; tipo de trabajador 19 ejecutivo / 20 obrero / 21
  empleado (según la categoría ocupacional); régimen de salud 00 EsSalud / 21 SIS; pensionario 02 ONP / 21 Integra /
  22 Horizonte / 23 Profuturo / 24 Prima / 25 Habitat / 99 sin régimen; contrato 1 indeterminado / 2 tiempo parcial /
  modalidad de la tabla 12 para plazo fijo; tipo de pago 2 si tiene cuenta de sueldo, 1 efectivo.
- Datos obligatorios del alta que se revisan antes de generar: fecha de nacimiento, sexo, celular (9 dígitos), correo,
  categoría, ocupación, situación educativa, modalidad del plazo fijo, nacionalidad (extranjeros), país emisor
  (pasaporte), código SUNAT de la AFP, establecimiento. Baja: motivo del cese.
- Catálogo de ocupaciones (tabla 30, 4,644 códigos con su habilitación por categoría) en
  `database/data/sunat/ocupaciones-tabla30.csv` → tabla `ocupaciones_sunat`.
- ⚠ Pendiente: dirección (E04 13–41), SCTR, cuenta de abono (`.cta`), estudios (`.edu`), personal en formación y
  modificaciones de datos (solo altas y bajas por ahora). Verificar en el PVS la baja solo con el periodo laboral.

## Reglas

- Los catálogos (tabla 3, 21, 22, ocupaciones, etc.) se cargan como **datos** (seeders/tablas), nunca como `match` en el código.
- Cada exportación se registra en `exportaciones` con hash del archivo, usuario y fecha (semana 5).
- Montos con punto decimal y 2 decimales, sin separador de miles.
