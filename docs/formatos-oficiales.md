# Formatos oficiales (SUNAT y AFP)

> Los archivos se generan en `app/Domain/Sunat/*` y `app/Domain/Afp/*`. Cada formato tiene tests con el **texto exacto** esperado.
> Antes de producción, cada archivo debe importarse sin errores en el aplicativo oficial (PLAME en SOL, AFPnet).

## PLAME (formulario 0601)

Nombre de archivo: `0601` + `AAAA` + `MM` + `RUC` + `.extensión` → `060120261020000000001.rem`
(`NombreArchivoPlame::para`). Campos separados por `|` y la línea termina en `|`. Fin de línea CRLF ⚠ (verificar si el aplicativo exige CRLF).

| Archivo         | Contenido                               | Formato de línea                                                        | Estado                            |
| --------------- | --------------------------------------- | ----------------------------------------------------------------------- | --------------------------------- |
| `.rem`          | Remuneraciones por concepto             | `tipoDoc\|numDoc\|codConcepto\|montoDevengado\|montoPagado\|`           | ✔ verificado con exportación real |
| `.jor`          | Jornada                                 | `tipoDoc\|numDoc\|horasOrd\|minOrd\|horasSobretiempo\|minSobretiempo\|` | ✔ verificado con exportación real |
| `.snl`          | Días subsidiados y no laborados         | `tipoDoc\|numDoc\|tipoSuspension\|dias\|`                               | ⚠ verificar                       |
| `.toc`          | Otras condiciones (régimen, SCTR, etc.) | ⚠ ver guía PLAME                                                        | pendiente                         |
| `.4ta` / `.ps4` | Cuarta categoría (v1.1)                 | ⚠                                                                       | pendiente                         |

Ejemplos (`.rem`):

```
01|12345678|0121|1230.00|1230.00|
01|12345678|0606|16.85|16.85|
```

Tipos de documento (tabla 3): `01` DNI, `04` carné de extranjería, `07` pasaporte ⚠ verificar lista completa.
Tipos de suspensión `.snl` (tabla 21): faltas, licencias, subsidios por enfermedad/maternidad ⚠ cargar como catálogo.

Fuente: "Guía de importación de archivos PLAME" y tablas paramétricas publicadas por SUNAT (descargar la versión vigente y guardarla fuera del repo; solo se versionan los catálogos ya transcritos como seeders).

## AFPnet (semana 5)

Planilla de declaración/pago por AFP, en Excel o texto según la plantilla de carga de AFPnet ⚠ descargar la plantilla oficial.
Columnas típicas por trabajador: número de secuencia, CUSPP, tipo y número de documento, apellidos y nombres, relación laboral, inicio/cese, excepción de aportar, remuneración asegurable, aporte voluntario con/sin fin previsional, aporte del empleador, tipo de trabajo/rubro.
Un archivo por AFP y periodo.

## T-Registro (v1.1)

Carga masiva por archivos de texto con nombre `RP_` + RUC + `.ide/.tra/.per/.est/...` ⚠ verificar nomenclatura:

| Archivo | Contenido                                                                   |
| ------- | --------------------------------------------------------------------------- |
| `.ide`  | Datos personales del trabajador                                             |
| `.tra`  | Datos laborales (régimen, tipo de contrato, ocupación, sistema pensionario) |
| `.per`  | Periodos (alta/baja, categoría, régimen de salud y pensión)                 |
| `.est`  | Establecimiento donde labora                                                |
| `.edu`  | Situación educativa                                                         |

## Reglas

- Los catálogos (tabla 3, 21, 22, ocupaciones, etc.) se cargan como **datos** (seeders/tablas), nunca como `match` en el código.
- Cada exportación se registra en `exportaciones` con hash del archivo, usuario y fecha (semana 5).
- Montos con punto decimal y 2 decimales, sin separador de miles.
