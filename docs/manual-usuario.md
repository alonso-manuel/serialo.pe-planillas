# Serialo Planillas — Manual de usuario

Guía para quienes usan el sistema en el día a día: el **administrador** del estudio contable o de la empresa y sus
**asistentes**. Explica qué hace cada módulo y en qué orden se trabaja el mes. Para instalar el sistema en un hosting,
ver [instalacion.md](instalacion.md).

> Los ejemplos usan datos ficticios. Los cálculos siguen la norma vigente, pero cada estudio es responsable de revisar
> lo que declara. Los puntos que la norma deja abiertos se indican en pantalla y en este manual con ⚠.

## Contenido

1. [Conceptos básicos](#1-conceptos-básicos)
2. [Ingreso y seguridad](#2-ingreso-y-seguridad)
3. [Configuración inicial (administrador)](#3-configuración-inicial-administrador)
4. [Trabajadores](#4-trabajadores)
5. [El ciclo del mes](#5-el-ciclo-del-mes)
6. [Planilla del mes paso a paso](#6-planilla-del-mes-paso-a-paso)
7. [Boletas y pagos](#7-boletas-y-pagos)
8. [Beneficios sociales](#8-beneficios-sociales)
9. [Archivos para SUNAT y AFP](#9-archivos-para-sunat-y-afp)
10. [Reportes y panel](#10-reportes-y-panel)
11. [Regímenes laborales](#11-regímenes-laborales)
12. [Administración](#12-administración)
13. [Preguntas frecuentes](#13-preguntas-frecuentes)

---

## 1. Conceptos básicos

| Término             | Qué significa en el sistema                                                                                                 |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **Empresa activa**  | La empresa con la que se trabaja en ese momento. Se elige arriba del menú lateral; todo lo que se ve y registra es de ella. |
| **Periodo**         | Un mes de planilla de una empresa. Pasa por tres estados: **Abierto** → **Calculado** → **Cerrado**.                        |
| **Novedad**         | Lo variable del mes: faltas, horas extra, subsidios, bonos, adelantos.                                                      |
| **Concepto**        | Cada pago o descuento, con su código de la tabla 22 de SUNAT (el mismo que pide el PLAME).                                  |
| **Parámetro legal** | RMV, UIT, tasas de EsSalud y ONP, etc. Se guardan con fecha de vigencia: un mes antiguo se recalcula igual.                 |
| **Periodo cerrado** | Ya revisado y declarado. Nadie lo modifica; solo el administrador puede reabrirlo indicando el motivo.                      |

### Roles

| Rol               | Puede                                                                                                            |
| ----------------- | ---------------------------------------------------------------------------------------------------------------- |
| **Administrador** | Todo: empresas, usuarios, parámetros, tasas AFP, conceptos, auditoría, respaldos, licencia, cerrar y reabrir.    |
| **Asistente**     | Trabajar solo las empresas que se le asignaron: trabajadores, novedades, cálculo, boletas, beneficios, archivos. |

El grupo **Administración** del menú solo lo ve el administrador.

---

## 2. Ingreso y seguridad

- No hay registro público: el administrador crea cada usuario y le entrega su contraseña inicial.
- Se ingresa con **correo y contraseña**, o con una **llave de acceso (passkey)**: la huella o el rostro de la
  computadora o del celular.
- En **menú de usuario → Configuración → Seguridad** (el sistema vuelve a pedir la contraseña) se puede cambiar la
  contraseña, activar el **doble factor** (código de una app autenticadora) y registrar llaves de acceso.
  Se recomienda activar el doble factor en todos los administradores.
- Guarde los **códigos de recuperación** del doble factor fuera de la computadora: sirven si pierde el celular.
- Un usuario desactivado no puede ingresar; si tenía la sesión abierta, se cierra en su siguiente clic.

---

## 3. Configuración inicial (administrador)

Se hace una sola vez, al empezar. Orden recomendado:

### 3.1 Empresas

**Administración → Empresas → Nueva empresa**

1. Escriba el RUC y pulse **Buscar en SUNAT**: completa razón social y domicilio (si el servicio responde; si no, se
   escriben a mano). El RUC no se puede cambiar después.
2. Elija el **régimen laboral** (general, pequeña empresa, microempresa o agrario) y el **seguro de salud por
   defecto**. Solo la microempresa puede usar el SIS. Si elige agrario, marque si el año anterior declaró 100 o más
   trabajadores o ventas de 1,700 UIT o más (ver [régimen agrario](#régimen-agrario-ley-31110)).
3. **Registrar empresa.** Se crea su establecimiento principal **0000**, el que pide SUNAT.

Al editar la empresa aparecen además:

| Sección              | Para qué                                                                                                                                      |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| **Logo**             | Sale en boletas, constancias y reportes PDF.                                                                                                  |
| **Establecimientos** | Locales o sucursales con su código SUNAT (se usan en T-Registro).                                                                             |
| **Centros de costo** | Áreas para repartir el costo laboral y el asiento contable (cada trabajador tiene uno, con vigencia).                                         |
| **Firma digital**    | Certificado `.pfx`/`.p12` del representante legal: firma boletas y documentos de periodos cerrados. Ver [firma-digital.md](firma-digital.md). |

Las empresas no se eliminan: se **desactivan** y salen del selector, pero su historia se conserva.

### 3.2 Usuarios

**Administración → Usuarios → Nuevo usuario**: nombre, correo y contraseña (el botón **Sugerir una contraseña segura**
genera una). Elija el rol; a un asistente márquele las empresas que atenderá. Los usuarios no se borran: se
desactivan, para que la auditoría siempre sepa quién hizo cada cosa.

### 3.3 Parámetros legales

**Administración → Parámetros**: RMV, UIT, EsSalud, ONP, SIS, asignación familiar, horas extra, gratificación,
vacaciones, quinta y cuarta categoría, indemnización y régimen agrario. Cada tarjeta muestra el valor vigente y su
historial.

- **No se editan.** Cuando cambia la ley, se pulsa **Nueva vigencia**: valor, mes desde el que rige y base legal.
- Una vigencia se puede anular solo si ningún periodo cerrado la usó.
- Los valores marcados **«Por verificar»** debe confirmarlos el contador.

### 3.4 Tasas AFP

**Administración → Tasas AFP.** Cada mes la SBS publica aporte, prima de seguro, comisiones y remuneración máxima
asegurable. El calendario marca qué meses están completos; **sin las tasas del mes no se calcula a los trabajadores
de AFP**.

- **Copiar de** (mes anterior) → ajustar lo que cambió → **Guardar**.
- O **Plantilla Excel** → llenar → **Importar** (con vista previa de errores).
- Un mes con algún periodo cerrado ya no se modifica.

### 3.5 Conceptos

**Administración → Conceptos.** Ingresos y descuentos con su código de la tabla 22 y sus afectaciones (EsSalud, ONP,
AFP, quinta). Los marcados **sistema** los usa el motor de cálculo: solo se les cambia el nombre.

Para un pago que no está (p. ej. 0107 trabajo en feriado): **Nuevo concepto** → código, nombre y afectaciones. El tipo
se deduce del código. Un concepto que ya se usó no se elimina: se desactiva.

---

## 4. Trabajadores

**Personal → Trabajadores** muestra el personal de la empresa activa, con búsqueda y filtros.

### 4.1 Ficha del trabajador

**Nuevo trabajador.** La ficha tiene pestañas:

| Pestaña             | Datos                                                                                                                                                                |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Personales**      | Documento (con DNI, un botón consulta RENIEC si está habilitado), nombres, nacimiento, sexo, correo, celular.                                                        |
| **Laborales**       | Establecimiento, centro de costo, cargo, ingreso, tipo de contrato, sueldo básico, asignación familiar. En empresas agrarias, la casilla de beneficios prorrateados. |
| **Pensión y salud** | ONP o AFP (con CUSPP y tipo de comisión) y seguro de salud.                                                                                                          |
| **Cuentas**         | Cuenta de sueldo y su banco; cuenta CTS y su entidad.                                                                                                                |
| **SUNAT**           | Datos de T-Registro: categoría, ocupación (tabla 30), situación educativa, modalidad, discapacidad, etc.                                                             |

Con la ficha guardada aparecen también:

| Pestaña              | Para qué                                                                                                 |
| -------------------- | -------------------------------------------------------------------------------------------------------- |
| **Historial**        | Cambios de sueldo, cargo, pensión, seguro, contrato o centro de costo **con el mes desde el que rigen**. |
| **Derechohabientes** | Cónyuge, hijos, etc., para el T-Registro. Se dan de baja con su motivo.                                  |
| **Préstamos**        | Préstamos en cuotas: la cuota de cada mes se descuenta sola en la planilla (código 0706).                |
| **Quinta**           | Rentas de otros empleadores del año (para la retención de quinta) y certificado anual de retenciones.    |

> Cuando un trabajador ya figura en alguna planilla, el sueldo, cargo, pensión y seguro **no se cambian en la ficha**:
> se usa **Historial → Registrar cambio** indicando desde qué mes rige. Así los meses anteriores no se alteran.

### 4.2 Cese

En la ficha, **Registrar cese**: fecha y motivo (tabla 17 de SUNAT). Después se calcula su
[liquidación](#84-liquidaciones). **Revertir cese** es solo para corregir un error.

### 4.3 Carga masiva desde Excel

**Importar desde Excel → Plantilla Excel**: llene una fila por trabajador (borre los dos ejemplos), súbala con
**Subir archivo**, revise la vista previa con los errores y avisos de cada fila y pulse **Registrar trabajadores**.
Solo se registran las filas válidas. Máximo 500 por archivo.

---

## 5. El ciclo del mes

Lista de verificación para cada empresa:

| #   | Paso                                              | Dónde                               |
| --- | ------------------------------------------------- | ----------------------------------- |
| 1   | Tasas AFP del mes cargadas                        | Administración → Tasas AFP          |
| 2   | Altas, ceses y cambios de sueldo registrados      | Trabajadores                        |
| 3   | Abrir el periodo                                  | Planilla → Periodos → Abrir periodo |
| 4   | Novedades (faltas, horas extra, bonos, adelantos) | Detalle del periodo (grilla)        |
| 5   | Recibos por honorarios del mes                    | Periodo → Honorarios (4ta)          |
| 6   | Calcular y revisar                                | Periodo → Calcular                  |
| 7   | Cerrar el periodo                                 | Periodo → Cerrar periodo            |
| 8   | Boletas (PDF, zip o correo) y archivo de pagos    | Periodo / Boletas                   |
| 9   | PLAME y AFPnet                                    | Archivos oficiales                  |
| 10  | Reportes y asiento contable                       | Reportes                            |

Además: **gratificación** en julio y diciembre (sale sola en la planilla), **CTS** en mayo y noviembre, **T-Registro**
dentro del día hábil siguiente a cada alta o baja. El [panel](#102-panel) avisa lo pendiente.

---

## 6. Planilla del mes paso a paso

### 6.1 Abrir el periodo

**Planilla → Periodos → Abrir periodo.** El primer periodo de una empresa puede ser cualquier mes; luego siempre el
siguiente. Se crea una fila por trabajador con los días propuestos (ingreso o cese en el mes, vacaciones registradas).
Si después da de alta o cesa a alguien, pulse **Actualizar trabajadores**.

### 6.2 Registrar novedades

La grilla funciona como una hoja de cálculo: **Faltas**, **Subsid.** (días de subsidio), **Lic. s/g** (licencia sin
goce), **HE 25 %**, **HE 35 %** y una columna por cada concepto usado (bonos, adelantos…; **Agregar columna** para
otro). Se mueve con las flechas, Enter y Tab, y **cada fila se guarda sola** al salir de ella.

Para muchas personas, **Importar asistencia → Plantilla Excel**: columnas documento, faltas, licencia sin goce, días y
tipo de subsidio y horas extra.

### 6.3 Honorarios (cuarta categoría)

**Honorarios (4ta)** en el periodo: registre cada recibo por honorarios del mes. El sistema retiene el 8 % cuando el
recibo supera el monto mínimo, salvo que el prestador tenga constancia de suspensión. Van al PLAME (.ps4 y .4ta).

### 6.4 Calcular

**Calcular.** Antes, el sistema revisa que no falten parámetros, tasas AFP o datos; si falta algo, lo lista y **no
calcula nada a medias**. Calcula ingresos, aportes (ONP/AFP, EsSalud o SIS), **renta de quinta** (retención 0605),
cuotas de préstamos, gratificación (julio/diciembre), remuneración vacacional y la liquidación de quien cesó en el mes.

Con el clic en un trabajador se ve su detalle (ingresos, aportes, descuentos, aportes del empleador, cálculo de la
quinta) y su boleta; con las flechas se pasa al siguiente.

Si edita algo después de calcular, el periodo vuelve a **Abierto**: calcule de nuevo.

**Calcular todas las empresas** (en Periodos) procesa en segundo plano todas las empresas con el mes abierto y
muestra el avance; las que tengan datos incompletos se informan sin detener a las demás.

### 6.5 Cerrar y reabrir

Revisado todo, el administrador pulsa **Cerrar periodo**: desde ese momento nadie puede modificarlo, las boletas salen
sin la marca «BORRADOR» (y firmadas, si la empresa tiene certificado) y se habilitan el PLAME, AFPnet y el envío por
correo. Para corregir, **Reabrir periodo** indicando el motivo (queda en la auditoría).

---

## 7. Boletas y pagos

- **Boletas PDF** (todas en un archivo, una por página) o **Boletas (zip)** (un PDF por trabajador), desde el periodo
  o desde **Planilla → Boletas**.
- **Boletas por correo** (solo periodos cerrados): se envían en segundo plano a los trabajadores con correo. Sin
  certificado van protegidas con el número de documento como contraseña; con certificado van firmadas. Queda la
  fecha de cada envío y se puede **Reenviar**.
- **Pagos (banco)**: Excel o CSV con banco, cuenta y neto de cada trabajador, los pagos en efectivo aparte y el total
  por banco, para preparar la transferencia.

---

## 8. Beneficios sociales

### 8.1 Gratificaciones (julio y diciembre)

No se calculan aparte: entran solas en la planilla de julio y diciembre (0406) con la bonificación extraordinaria
(0312, 9 %; no está afecta a ONP, AFP ni EsSalud). **Beneficios → Gratificaciones** muestra el resumen y el detalle por trabajador: meses
computables, faltas y remuneración computable (incluye horas extra y comisiones regulares).

### 8.2 CTS (mayo y noviembre)

**Beneficios → CTS → Calcular** (o **Recalcular**) con las planillas del semestre y el sexto de la gratificación. Muestra el total a
depositar y el monto por trabajador; avisa si falta la cuenta CTS. **Constancias PDF** o **Constancias (zip)**.
Al terminar, **Cerrar CTS** (solo el administrador la reabre).

### 8.3 Vacaciones

**Beneficios → Vacaciones**: récord por trabajador (días ganados, gozados y saldo); en rojo los periodos vencidos.
**Registrar vacaciones**: desde qué fecha y cuántos días; se descuentan del periodo más antiguo y aparecen solos en
la planilla del mes (columna «Vac.») como remuneración vacacional.

### 8.4 Liquidaciones

Con el cese registrado, **Beneficios → Liquidaciones → Calcular**: gratificación y CTS truncas, vacaciones no gozadas
(con su indemnización si vencieron) y vacaciones truncas. Si corresponde, marque **Pagar indemnización por despido
arbitrario** (y, en plazo fijo, la fecha de vencimiento del contrato). La liquidación se paga en la planilla del mes
del cese; el plazo legal es de 48 horas.

Documentos: **Hoja** de liquidación, **Certificado de trabajo** y **Carta para liberar CTS**. Al **Cerrar** ya no se
recalcula y el cese no se puede cambiar.

---

## 9. Archivos para SUNAT y AFP

Solo se generan de **periodos cerrados**: se declara exactamente lo que se pagó. Cada descarga queda registrada con la
huella de sus archivos.

| Módulo         | Qué genera                                                                                              | Dónde se usa                        |
| -------------- | ------------------------------------------------------------------------------------------------------- | ----------------------------------- |
| **PLAME**      | Zip con .rem (conceptos), .jor (días y horas), .snl (suspensiones), .toc, y .ps4/.4ta si hay honorarios | PDT PLAME 0601 → importar           |
| **AFPnet**     | Archivo con un afiliado por línea, CUSPP y remuneración asegurable                                      | AFPnet → carga de planilla          |
| **T-Registro** | Altas y bajas de un rango de fechas (.ide, .tra, .per, .est) y derechohabientes                         | PVS T-Registro y SOL → carga masiva |

En PLAME y AFPnet, **Descargar todas** baja los archivos de todas las empresas del mes en un solo paso.

En **T-Registro**, elija el rango y pulse **Consultar**: lista las altas y bajas y los datos que faltan por
trabajador (**Completar datos** lleva a su ficha). Con todo completo, **Descargar archivos**.

---

## 10. Reportes y panel

### 10.1 Reportes

**Reportes**: elija el mes (de la empresa activa); cada reporte se descarga en **Excel** o **PDF**.

| Reporte                              | Contenido                                                                 |
| ------------------------------------ | ------------------------------------------------------------------------- |
| Planilla del mes                     | Una fila por trabajador, un concepto por columna                          |
| Resumen                              | Lo que se paga a ONP, a cada AFP, a EsSalud, a SUNAT y a los trabajadores |
| Centralización contable              | Asiento del mes con las cuentas del PCGE de cada concepto (debe = haber)  |
| Costo laboral                        | Remuneración, aportes del empleador y provisiones por trabajador          |
| Costo por centro de costo            | El costo laboral agrupado por área                                        |
| Asiento contable por centro de costo | La centralización con los cargos separados por centro                     |
| Comparativo con el mes anterior      | Neto, bruto y costo de cada trabajador, variación, altas y bajas          |

Las cuentas contables de cada concepto se ajustan en **Administración → Conceptos**.

### 10.2 Panel

La página de inicio resume el mes de todas las empresas (o de una, con el filtro): costo laboral y su variación,
cuántas empresas cerraron, **Por atender** (tasas AFP faltantes, cambios sin recalcular, gratificación, CTS,
vacaciones vencidas), la evolución de 12 meses y el estado de cada empresa (periodo, PLAME, AFPnet, boletas enviadas).

---

## 11. Regímenes laborales

| Régimen                  | Gratificación        | CTS                           | Vacaciones | Salud           |
| ------------------------ | -------------------- | ----------------------------- | ---------- | --------------- |
| General                  | 1 sueldo jul + 1 dic | ½ sueldo por semestre         | 30 días    | EsSalud 9 %     |
| Pequeña empresa (REMYPE) | ½ sueldo jul + ½ dic | 15 remuneraciones diarias/año | 15 días    | EsSalud 9 %     |
| Microempresa (REMYPE)    | No                   | No                            | 15 días    | SIS o EsSalud   |
| Agrario (Ley 31110)      | Prorrateada o legal  | Prorrateada o legal           | 30 días    | EsSalud agrario |

### Régimen agrario (Ley 31110)

- **Beneficios prorrateados (por defecto):** cada mes la boleta incluye la gratificación (16.66 % del básico, 0406),
  su bonificación extraordinaria (0312) y la CTS (9.72 %, 0904). Esos trabajadores no reciben gratificación en
  julio/diciembre ni depósito CTS, ni truncas en la liquidación.
- **Si el trabajador pidió por escrito** cobrar gratificación y CTS en las fechas legales, desmarque en su ficha
  (Laborales) **Gratificaciones y CTS prorrateadas**: recibe ambas como en el régimen general.
- **BETA** (0314): 30 % de la RMV al mes, proporcional a los días trabajados y de vacaciones. No es remunerativa.
- **EsSalud agrario** sin base mínima: la tasa depende de lo que la empresa declaró el año anterior. Revise cada
  **enero** la casilla de la empresa «100 o más trabajadores agrarios o ventas de 1,700 UIT o más».
- ⚠ Aún no se calculan: utilidades, sobretasa nocturna y BETA para jornadas menores a 4 horas.

El régimen de **construcción civil** se agregará cuando se confirmen las cifras del convenio vigente.

---

## 12. Administración

| Módulo        | Para qué                                                                                                                                                                                  |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Auditoría** | Quién hizo qué, cuándo, desde qué IP y qué valores cambiaron. Filtros por empresa, módulo, acción, usuario y fechas. No se puede editar ni borrar.                                        |
| **Respaldos** | Copia diaria automática de la base (03:00) y **Crear respaldo ahora**. **Descargue uno cada semana** y guárdelo fuera del servidor. Ver [respaldos.md](respaldos.md).                     |
| **Licencia**  | Estado de la licencia y uso del plan (empresas, usuarios, trabajadores). Para renovar, **Registrar clave**. Sin licencia válida el sistema queda en solo lectura: nunca se pierden datos. |
| **Soporte**   | (Servicio en la nube) **Permitir acceso por 24 horas** deja entrar a Serialo.pe para ayudarle; todo lo que haga queda en la auditoría. **Quitar acceso** lo corta al momento.             |

---

## 13. Preguntas frecuentes

**«Calcular» muestra una lista de problemas.**
Es a propósito: el sistema no calcula a medias. Lo más común: faltan las tasas AFP del mes, un trabajador en AFP sin
AFP asignada o un parámetro sin vigencia. Corríjalo y calcule de nuevo.

**Cambié un sueldo y el mes anterior no cambió.**
Correcto: los cambios rigen desde el mes indicado en el historial. Si el mes anterior debía cambiar, regístrelo desde
ese mes (si está cerrado, primero hay que reabrirlo).

**No puedo editar un periodo.**
Está cerrado. Solo el administrador lo reabre, indicando el motivo.

**No aparece el botón de enviar boletas o de PLAME.**
Ambos requieren el periodo cerrado.

**Un trabajador no aparece en la planilla.**
Revise su fecha de ingreso o de cese y pulse **Actualizar trabajadores** en el periodo.

**La boleta dice «BORRADOR».**
El periodo aún no está cerrado.

**El sistema está en «solo lectura».**
La licencia venció o no corresponde al dominio. Se puede consultar y descargar todo; para volver a registrar,
renueve la licencia en Administración → Licencia o escriba a Serialo.pe.

**¿Puedo deshacer un error grave (una carga masiva equivocada, un periodo borrado)?**
Sí, con un respaldo. Comuníquese con soporte antes de hacer más cambios.

---

Soporte: Serialo.pe – Manu Dev.
