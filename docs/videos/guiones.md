# Videos de demo — guiones

Dieciocho videos cortos (1280×720, `.webm`, sin audio) que muestran cómo va quedando Serialo Planillas, en dos temporadas:
la **1** (00–09) recorre la base del sistema y la **2** (10–17) los módulos que vinieron después: panel, boletas,
beneficios, archivos oficiales y reportes.
Cada video lleva **subtítulos en pantalla** (versión breve de la narración), una **etiqueta de escena** arriba a la derecha
y un **cursor azul** que marca cada clic. La columna **Narración** es el texto para leer como voz en off si se graba audio
encima (por ejemplo, con Clipchamp o CapCut), o para acompañar una presentación en vivo.

Todos los datos son **ficticios** (DNI 7000xxxx, RUC 200000000xx, nombres inventados). Ningún dato real de clientes (Ley 29733).

| #   | Video                | Duración | Usuario | Empresa activa              |
| --- | -------------------- | -------- | ------- | --------------------------- |
| 00  | Resumen del proyecto | ~1:05    | —       | —                           |
| 01  | Acceso al sistema    | ~1:00    | Admin   | Comercial Los Andes         |
| 02  | Empresas             | ~1:00    | Admin   | Comercial Los Andes         |
| 03  | Usuarios             | ~0:40    | Admin   | —                           |
| 04  | Parámetros legales   | ~0:40    | Admin   | —                           |
| 05  | Tasas AFP            | ~0:35    | Admin   | —                           |
| 06  | Conceptos            | ~0:40    | Admin   | —                           |
| 07  | Trabajadores         | ~1:30    | Admin   | Distribuidora Pacífico S.A. |
| 08  | Periodos y planilla  | ~1:30    | Admin   | Distribuidora Pacífico S.A. |
| 09  | Auditoría            | ~0:30    | Admin   | —                           |
| 10  | Panel del estudio    | ~1:05    | Admin   | — (filtro: Distribuidora)   |
| 11  | Boletas de pago      | ~1:00    | Admin   | Distribuidora Pacífico S.A. |
| 12  | Gratificaciones      | ~0:40    | Admin   | Distribuidora Pacífico S.A. |
| 13  | CTS                  | ~0:50    | Admin   | Distribuidora Pacífico S.A. |
| 14  | Vacaciones           | ~0:55    | Admin   | Distribuidora Pacífico S.A. |
| 15  | PLAME                | ~0:45    | Admin   | — (todas las empresas)      |
| 16  | AFPnet               | ~0:25    | Admin   | — (todas las empresas)      |
| 17  | Reportes             | ~0:45    | Admin   | Distribuidora Pacífico S.A. |

Orden pensado para contar una historia: primero el **resumen**, luego **cómo se entra**, después la **configuración**
(empresas, usuarios, parámetros, tasas, conceptos), el **personal**, la **planilla** y la **auditoría**, que muestra
el rastro de todo lo hecho en los videos anteriores. La temporada 2 sigue el ciclo del mes: el **panel** para ver qué falta,
las **boletas**, los **beneficios** (gratificación, CTS, vacaciones), los **archivos para SUNAT y AFP** y los **reportes**.

En la temporada 2, los PDF (boletas, constancias, reportes) y los archivos descargados (zip del PLAME, TXT de AFPnet)
se muestran dentro del video en un **visor superpuesto**, para que se vea lo que genera cada módulo.

---

## 00 · Resumen del proyecto (~1:05)

Presentación animada (no es la aplicación). Seis láminas.

| Tiempo    | En pantalla                                                                                                              | Narración                                                                                                                                                                                                                                                                                                                        |
| --------- | ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:06 | «Serialo Planillas — Sistema web de planillas para el Perú»                                                              | Les presento Serialo Planillas, un sistema web de planillas hecho en Perú por Serialo.pe para estudios contables y empresas.                                                                                                                                                                                                     |
| 0:06–0:16 | **El problema de hoy**: Excel con macros, cambios legales a mano, PLAME manual, un usuario a la vez                      | Hoy la mayoría trabaja con libros de Excel con macros: un archivo por empresa, fórmulas que se corrigen a mano cada vez que cambia la RMV o una tasa AFP, y datos que se copian al PLAME uno por uno. Además, solo puede trabajar una persona a la vez.                                                                          |
| 0:16–0:28 | **Qué hace**: planilla mensual, multiempresa, cálculo en lote, beneficios, boletas, archivos oficiales                   | Serialo Planillas calcula la planilla de los regímenes general, pequeña y microempresa; atiende muchas empresas a la vez; calcula todo el mes en lote; y va a generar beneficios sociales, boletas en PDF y los archivos del PLAME, AFPnet y T-Registro.                                                                         |
| 0:28–0:41 | **Principios**: cálculo exacto, leyes como datos, periodo cerrado inmutable, auditoría, datos en el servidor del cliente | Lo construimos con cinco principios: cálculo exacto al céntimo; los valores legales se guardan con su vigencia, así un mes antiguo siempre se recalcula igual; un periodo cerrado no se toca; todo queda auditado; y el sistema se instala en el servidor del cliente, así los datos de sus trabajadores no salen de su control. |
| 0:41–0:52 | **Cómo va el desarrollo**: disponible hoy / próximas semanas                                                             | Esto es lo que ya funciona hoy, y esto es lo que llega en las próximas semanas: gratificación de diciembre, boletas, CTS, vacaciones y los archivos para SUNAT y AFP.                                                                                                                                                            |
| 0:52–1:05 | **En los siguientes videos**: lista del 1 al 9                                                                           | En los siguientes videos recorremos cada módulo. Todos los datos que verán son ficticios.                                                                                                                                                                                                                                        |

## 01 · Acceso al sistema (~1:00)

| Tiempo    | En pantalla                                                      | Narración                                                                                                                                                                   |
| --------- | ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:07 | Pantalla de ingreso                                              | Serialo Planillas se instala en el hosting del estudio contable o de la empresa. No tiene registro público: cada usuario lo crea el administrador.                          |
| 0:07–0:15 | Se escribe correo y contraseña; «Ingresar»                       | Se ingresa con correo y contraseña, o con una llave de acceso —una passkey— desde el celular o la huella de la computadora.                                                 |
| 0:15–0:24 | Menú lateral; el cursor pasa por Trabajadores, Periodos, PLAME   | A la izquierda están los módulos agrupados por tema. Los que dicen «Pronto» llegan en las próximas semanas.                                                                 |
| 0:24–0:32 | Se abre el selector de empresa activa                            | Arriba está la empresa activa: todo lo que se ve y se registra pertenece a esa empresa. Un estudio contable cambia de empresa con un clic.                                  |
| 0:32–0:55 | Menú de usuario → Configuración → Seguridad; confirma contraseña | En la configuración, cada usuario puede cambiar su contraseña, activar el doble factor y registrar sus passkeys. Para entrar aquí, el sistema vuelve a pedir la contraseña. |
| 0:55–1:00 | Opciones de seguridad                                            | Así se entra al sistema de forma segura.                                                                                                                                    |

## 02 · Empresas (~1:00)

| Tiempo    | En pantalla                                                        | Narración                                                                                                                                          |
| --------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:07 | Lista de empresas con régimen, trabajadores y estado               | El sistema es multiempresa. Aquí el administrador registra las empresas que atiende, cada una con su RUC, su régimen laboral y su seguro de salud. |
| 0:07–0:15 | «Nueva empresa»; se escribe el RUC                                 | Registremos una empresa nueva. Con el botón «Buscar en SUNAT» se completan la razón social y el domicilio; aquí los escribimos a mano.             |
| 0:15–0:32 | Razón social, nombre comercial, representante, domicilio, régimen  | Elegimos el régimen: pequeña empresa. El régimen define los beneficios, y solo la microempresa puede usar el SIS en lugar de EsSalud.              |
| 0:32–0:42 | «Registrar empresa»; aparecen los establecimientos                 | Al registrarla, el sistema crea su establecimiento principal con el código 0000, que es el que pide SUNAT.                                         |
| 0:42–1:00 | Lista actualizada; selector de empresa activa con la nueva empresa | Ya aparece en la lista. Se puede editar o desactivar, pero su historial nunca se borra. Y ya está disponible en el selector de empresa activa.     |

## 03 · Usuarios (~0:40)

| Tiempo    | En pantalla                                              | Narración                                                                                                                                   |
| --------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:05 | Lista de usuarios con rol y empresas                     | Hay dos roles: el administrador ve todas las empresas y configura el sistema; el asistente trabaja solo con las empresas que se le asignan. |
| 0:05–0:15 | «Nuevo usuario»: nombre, correo, «Sugerir»               | Creamos un usuario. El administrador escribe la contraseña o el sistema le sugiere una segura, que luego se le entrega.                     |
| 0:15–0:28 | Rol «Asistente»; se marcan dos empresas; «Crear usuario» | Lo hacemos asistente y le asignamos dos empresas: solo verá esas.                                                                           |
| 0:28–0:40 | Lista actualizada; botón de desactivar                   | Los usuarios no se borran: se desactivan, para que la auditoría siempre sepa quién hizo cada cambio.                                        |

## 04 · Parámetros legales (~0:40)

| Tiempo    | En pantalla                                                      | Narración                                                                                                                                                     |
| --------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:08 | Tarjetas: RMV, UIT, EsSalud, SIS, ONP, asignación familiar       | La RMV, la UIT, EsSalud, ONP, el SIS y la asignación familiar no están escritos en el programa: son datos con fecha de vigencia y base legal.                 |
| 0:08–0:15 | RMV S/ 1,130.00; aviso «Por verificar»                           | Así, un mes antiguo siempre se recalcula con los valores de su época. Los que todavía debe confirmar el contador quedan marcados «Por verificar».             |
| 0:15–0:20 | Se abre el historial de la UIT                                   | El historial muestra cada valor anterior y desde cuándo rigió.                                                                                                |
| 0:20–0:35 | «+» en RMV: monto, mes desde el que rige, base legal; «Cancelar» | Si cambia la ley, se registra una vigencia nueva con su mes de inicio y su base legal. Esto es solo un ejemplo, así que cancelamos para no cambiar los datos. |
| 0:35–0:40 | Resto de parámetros                                              | El motor de cálculo siempre toma el valor vigente en el mes de la planilla.                                                                                   |

## 05 · Tasas AFP (~0:35)

| Tiempo    | En pantalla                                               | Narración                                                                                                                        |
| --------- | --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:07 | Calendario 2026; grilla de noviembre                      | Cada mes la SBS publica el aporte, la prima de seguro, las comisiones y la remuneración máxima asegurable de cada AFP.           |
| 0:07–0:14 | Diciembre marcado «Falta»                                 | El calendario muestra qué meses están completos. Diciembre falta: sin esas tasas no se puede calcular a los trabajadores de AFP. |
| 0:14–0:25 | Clic en Dic → «Copiar de Noviembre» → «Guardar diciembre» | Si no cambiaron, se copian del mes anterior y se ajusta lo necesario. Guardamos y diciembre queda completo.                      |
| 0:25–0:35 | Botones «Plantilla Excel» e «Importar»                    | También se pueden cargar desde Excel con la plantilla del sistema.                                                               |

## 06 · Conceptos (~0:40)

| Tiempo    | En pantalla                                                      | Narración                                                                                                                                                                        |
| --------- | ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:07 | Tabla de ingresos con afectaciones                               | Los pagos y descuentos usan los códigos de la tabla 22 de SUNAT, los mismos que exige el PLAME. Para cada ingreso se marca si está afecto a EsSalud, ONP, AFP y renta de quinta. |
| 0:07–0:12 | Etiqueta «sistema»                                               | Los marcados como «sistema» los usa el motor de cálculo: no se pueden borrar ni cambiar sus afectaciones.                                                                        |
| 0:12–0:30 | «Nuevo concepto»: código 0107, nombre, afectaciones; «Registrar» | Agregamos el 0107, trabajo en día feriado. El tipo —ingreso, descuento o aporte— se deduce del código. Marcamos sus afectaciones y lo registramos.                               |
| 0:30–0:40 | Búsqueda «feriado»                                               | Ya se puede usar como novedad en la planilla del mes.                                                                                                                            |

## 07 · Trabajadores (~1:30)

| Tiempo    | En pantalla                                                                              | Narración                                                                                                                                                                                                                    |
| --------- | ---------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:10 | Lista del personal; búsqueda «Vargas»                                                    | Aquí está el personal de la empresa activa, con búsqueda y filtros por estado y por sistema de pensión.                                                                                                                      |
| 0:10–0:30 | «Nuevo trabajador», pestaña Personales                                                   | La ficha se organiza en pestañas. Con DNI, un botón consulta RENIEC y completa los nombres; aquí los escribimos a mano.                                                                                                      |
| 0:30–0:45 | Pestaña Laborales: cargo, fecha de ingreso, sueldo, asignación familiar                  | En los datos laborales van el cargo, la fecha de ingreso, el sueldo básico y si percibe asignación familiar.                                                                                                                 |
| 0:45–0:55 | Pestaña Pensión y salud: AFP, CUSPP; «Registrar trabajador»                              | Si está en AFP, se indica cuál, su CUSPP y el tipo de comisión.                                                                                                                                                              |
| 0:55–1:10 | Ficha guardada → pestaña Historial; botón «Registrar cese»                               | Los cambios de sueldo, cargo o pensión se registran indicando el mes desde el que rigen, así un periodo antiguo se recalcula con los datos de ese mes. El cese se registra con su fecha y el motivo de la tabla 17 de SUNAT. |
| 1:10–1:25 | Importar: archivo de 3 trabajadores; vista previa con avisos; «Registrar 3 trabajadores» | Para empezar rápido, se descarga la plantilla, se llena y se sube. Antes de grabar, el sistema muestra una vista previa con los errores y avisos de cada fila.                                                               |
| 1:25–1:30 | Lista con los nuevos trabajadores                                                        | Y los nuevos trabajadores ya están en la lista.                                                                                                                                                                              |

## 08 · Periodos y planilla (~1:30)

| Tiempo    | En pantalla                                                          | Narración                                                                                                                                                                                                        |
| --------- | -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:07 | Lista: noviembre abierto, octubre cerrado                            | Cada mes es un periodo, que puede estar abierto, calculado o cerrado. Octubre ya está calculado y cerrado.                                                                                                       |
| 0:07–0:22 | Grilla de noviembre; se escriben 4 horas extra al 25 %               | La grilla de novedades funciona como una hoja de cálculo: faltas, horas extra, bonos y adelantos. Cada fila se guarda sola al pasar a la siguiente.                                                              |
| 0:22–0:30 | «Calcular planilla»; aparece la columna Neto y el aviso con el total | Calculamos. Antes, el sistema revisa que no falten tasas ni parámetros; si falta algo, lo explica y no calcula nada a medias. Aparece el neto de cada trabajador y el total del mes.                             |
| 0:30–0:45 | Detalle de un trabajador; flechas «siguiente»                        | En el detalle se ven los ingresos, los aportes del trabajador, los otros descuentos y los aportes del empleador. Con las flechas se pasa al siguiente.                                                           |
| 0:45–1:05 | «Cerrar periodo» → confirmación; «Reabrir periodo» con motivo        | Revisada la planilla, el administrador la cierra: desde ese momento nadie puede modificarla. Para corregir algo, solo el administrador la reabre, y debe indicar el motivo.                                      |
| 1:05–1:30 | «Calcular todas las empresas» → avance por empresa → «Terminado»     | Por último, calculamos todas las empresas a la vez. Se procesa en segundo plano y se ve el avance de cada una. Las que no tienen el mes abierto o tienen datos incompletos se informan, sin detener a las demás. |

## 09 · Auditoría (~0:30)

| Tiempo    | En pantalla                                 | Narración                                                                                                                                                                          |
| --------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:08 | Lista de registros                          | Todo cambio queda registrado: quién lo hizo, cuándo, desde qué dirección IP y qué valores cambiaron.                                                                               |
| 0:08–0:18 | Fila «Reabierto» expandida: estado y motivo | Aquí están las acciones de los videos anteriores: la empresa, el usuario, los trabajadores, el cierre… Al abrir una fila se ve el detalle, por ejemplo el motivo de la reapertura. |
| 0:18–0:30 | Filtros y desplazamiento                    | Se puede filtrar por empresa, módulo, acción, usuario y fechas. Y el registro es inmutable: nadie puede editarlo ni borrarlo.                                                      |

---

# Temporada 2

## 10 · Panel del estudio (~1:05)

| Tiempo    | En pantalla                                                   | Narración                                                                                                                      |
| --------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| 0:00–0:16 | Indicadores: costo laboral con su variación, cierre del mes   | Al entrar, el panel resume el mes de todas las empresas: el costo laboral, cuánto cambió y cuántas empresas ya cerraron.       |
| 0:16–0:32 | «Por atender»; gráfico de 12 meses con el detalle de cada mes | Abajo, lo que queda por atender y la evolución de los últimos doce meses. Al pasar el mouse se ve cada mes; también hay tabla. |
| 0:32–0:42 | ¿A dónde va el costo laboral? · Neto por empresa              | A dónde va cada sol de la planilla, y cuánto se paga en cada empresa.                                                          |
| 0:42–0:55 | Filtro «Empresa» → Distribuidora Pacífico                     | Con el filtro, el panel muestra una sola empresa, con los mismos indicadores y gráficos.                                       |
| 0:55–1:05 | Sección «Módulos»                                             | Y al final, el acceso a cada módulo con una breve descripción.                                                                 |

## 11 · Boletas de pago (~1:00)

| Tiempo    | En pantalla                                                     | Narración                                                                                                                    |
| --------- | --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:10 | Boletas por mes; noviembre «con marca BORRADOR»                 | Las boletas se generan solas al calcular la planilla. Mientras el mes no se cierra, salen con la marca «borrador».           |
| 0:10–0:28 | Visor: PDF de octubre (una boleta por página)                   | Las de octubre, todas en un PDF listo para imprimir: logo de la empresa, días, ingresos, descuentos, aportes y neto a pagar. |
| 0:28–0:33 | Botón «Zip»                                                     | También se descargan en un zip, un archivo por trabajador.                                                                   |
| 0:33–0:52 | Septiembre → «Enviar boletas por correo» → «Enviar»             | Y se envían por correo, en un PDF protegido con el DNI del trabajador, en segundo plano y respetando el límite del hosting.  |
| 0:52–1:00 | Estado por trabajador: enviada (con fecha), en cola, «Reenviar» | Queda la fecha de cada envío, y se puede reenviar a quien la pida.                                                           |

## 12 · Gratificaciones (~0:40)

| Tiempo    | En pantalla                                                     | Narración                                                                                                                |
| --------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| 0:00–0:08 | Resumen: julio 2026 y diciembre 2025                            | La gratificación de julio y diciembre no se calcula aparte: entra sola en la planilla de ese mes.                        |
| 0:08–0:22 | Planilla de julio: tabla «Gratificación de Julio 2026»          | El detalle por trabajador: meses completos, faltas y remuneración computable. Quien ingresó en julio aún no suma un mes. |
| 0:22–0:30 | Columna «Bonificación»                                          | La bonificación extraordinaria es el 9 % (o 6.75 % con EPS) y no paga ONP, AFP ni EsSalud.                               |
| 0:30–0:40 | Detalle del trabajador: conceptos 0406 y 0312 junto a su sueldo | Todo aparece en el detalle del trabajador y en su boleta.                                                                |

## 13 · CTS (~0:50)

| Tiempo    | En pantalla                                              | Narración                                                                                                                      |
| --------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| 0:00–0:12 | Depósito noviembre 2026; «Recalcular»                    | La CTS se deposita en mayo y noviembre. Se calcula con un clic, con las planillas del semestre y el sexto de la gratificación. |
| 0:12–0:26 | Total a depositar; tabla por trabajador; aviso de cuenta | Total a depositar y, por trabajador, el tiempo computable y su monto. Si falta la cuenta CTS en la ficha, el sistema lo avisa. |
| 0:26–0:35 | Visor: constancias de depósito en PDF                    | Las constancias de depósito salen en PDF.                                                                                      |
| 0:35–0:50 | «Cerrar CTS» → cerrada; botón «Reabrir»                  | Al final se cierra: ya no se recalcula, y solo el administrador puede reabrirla.                                               |

## 14 · Vacaciones (~0:55)

| Tiempo    | En pantalla                                                | Narración                                                                                                                  |
| --------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:12 | Récord por trabajador; «2 periodos vencidos» en rojo       | El récord vacacional de cada trabajador: días ganados, gozados y saldo. En rojo, las vacaciones vencidas sin gozar.        |
| 0:12–0:18 | Detalle de un trabajador: años de servicio y fecha límite  | El detalle muestra cada año de servicios y su fecha límite.                                                                |
| 0:18–0:38 | «Registrar»: desde, días, observación; detalle actualizado | Para registrar vacaciones se indica desde qué fecha y cuántos días; se descuentan del periodo más antiguo y el saldo baja. |
| 0:38–0:55 | Planilla de noviembre: columna «Vac.»; «Calcular planilla» | En la planilla del mes, esos días aparecen solos y se pagan como remuneración vacacional.                                  |

## 15 · PLAME (~0:45)

| Tiempo    | En pantalla                                                        | Narración                                                                                                                       |
| --------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| 0:00–0:10 | Empresas con octubre cerrado; «Solo se exportan periodos cerrados» | Los archivos para importar en el PDT PLAME de SUNAT, de cada empresa con el mes cerrado: se declara exactamente lo que se pagó. |
| 0:10–0:28 | Visor: zip con .rem, .jor y .toc                                   | Cada archivo con el nombre que pide SUNAT: el .rem con los conceptos por código de la tabla 22, el .jor con días y horas.       |
| 0:28–0:45 | «Descargar todas»; historial con la huella SHA-256 de cada archivo | Se descargan los de todas las empresas a la vez, y cada descarga queda registrada con la huella de sus archivos.                |

## 16 · AFPnet (~0:25)

| Tiempo    | En pantalla                                 | Narración                                                                                            |
| --------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| 0:00–0:18 | Empresas; visor con el TXT de Distribuidora | El archivo que se carga en AFPnet: un afiliado por línea, con su CUSPP y su remuneración asegurable. |
| 0:18–0:25 | «Descargar todas»                           | Solo de meses cerrados, y de todas las empresas a la vez.                                            |

## 17 · Reportes (~0:45)

| Tiempo    | En pantalla                            | Narración                                                                                            |
| --------- | -------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| 0:00–0:05 | Cuatro tarjetas; mes «Octubre 2026»    | Cada mes hay cuatro reportes, en Excel y en PDF.                                                     |
| 0:05–0:15 | Visor: planilla del mes                | La planilla del mes, con una fila por trabajador y cada concepto en su columna.                      |
| 0:15–0:24 | Visor: resumen                         | El resumen, con lo que se paga a ONP, a cada AFP, a EsSalud, a SUNAT y a los trabajadores.           |
| 0:24–0:36 | Visor: centralización contable         | La centralización contable: el asiento del mes con las cuentas del PCGE, donde debe y haber cuadran. |
| 0:36–0:45 | Tarjeta «Costo laboral», botón «Excel» | Y el costo laboral por trabajador, con las provisiones de gratificación, CTS y vacaciones.           |

---

## Voz en off con ElevenLabs

Los textos listos para pegar están en [`elevenlabs/`](elevenlabs/), un archivo por video. Son la narración de las tablas
de arriba, adaptada para voz:

- **Ajustados al tiempo del video**: a ritmo normal (~2.5 palabras por segundo) cada audio dura menos que su video
  (habla + pausas: 00 ≈ 63 s de 65, 08 ≈ 70 s de 87, 09 ≈ 22 s de 27…).
- **Pausas entre escenas** con `<break time="1.5s" />` (ElevenLabs acepta hasta 3 s por pausa). Cada párrafo es una escena.
- **Siglas escritas como se pronuncian**: «o ene pe» (ONP), «a efe pe» (AFP), «u i te» (UIT), «ese be ese» (SBS),
  «de ene i» (DNI), «Es Salud», «cero uno cero siete», «tabla veintidós». Se evitan símbolos (S/, %, códigos) y
  palabras en inglés (passkey → «llave de acceso», 2FA → «doble factor»).
- **Total: ~11,000 caracteres** para los 18 videos (temporada 2: ~4,300). Revisar el saldo del plan antes de generar.
- Siglas nuevas de la temporada 2: «ce te ese» (CTS), «pe de efe» (PDF), «pe de te» (PDT), «e pe ese» (EPS),
  «ce u ese pe pe» (CUSPP), «a efe pe net» (AFPnet), «punto rem» y «punto jor» (extensiones de archivo).

**Configuración sugerida**

| Opción     | Valor                                                                             |
| ---------- | --------------------------------------------------------------------------------- |
| Modelo     | Eleven Multilingual v2 (respeta las etiquetas `<break>`)                          |
| Voz        | Una voz en español latinoamericano, tono profesional y cercano; la misma en todos |
| Stability  | ~50 %                                                                             |
| Similarity | ~75 %                                                                             |
| Style      | 0 % (más natural y estable para narración)                                        |
| Velocidad  | 1.0 (si un audio queda largo, 1.05–1.1)                                           |

**Montaje** (Clipchamp, incluido en Windows 11, o CapCut)

1. Importar el video `.webm` y su audio `.mp3`.
2. Poner el audio al inicio. Si una escena se adelanta o se atrasa, cortar el audio en la pausa entre párrafos
   y moverlo hasta que coincida con el subtítulo en pantalla.
3. Exportar en 1080p. Si se agrega música de fondo, dejarla por debajo de la voz (~10–15 %).

Si una palabra suena mal, se corrige solo esa frase: se escribe como se pronuncia (como con las siglas) o se usa
el diccionario de pronunciación de ElevenLabs, y se regenera únicamente ese archivo.

## Cómo volver a grabarlos

Los videos se graban de forma automática con Playwright (`tools/videos/`), así se pueden regenerar cuando cambie la interfaz.

```bash
# 1. Base limpia con datos ficticios y servidor corriendo
php artisan migrate:fresh --seed
php artisan db:seed --class=HistorialDemoSeeder   # solo para la temporada 2 (un año de planillas)
composer dev                      # http://127.0.0.1:8000 (incluye la cola para el cálculo masivo)

# 2. Grabar (la primera vez instala Playwright solo en esa carpeta)
cd tools/videos
npm install
npx playwright install chromium   # si no está descargado
npm run grabar                    # los 18 videos, en orden
node videos.js 08-periodos        # o solo uno
```

- Los videos quedan en `videos/` (ignorada por git). Con `CAPS=1` además guarda una captura por subtítulo en `videos/caps/` para revisar.
- Grabarlos **en orden y sobre la base recién sembrada**: cada video crea datos que usan los siguientes (empresa, usuario, conceptos)
  y la auditoría muestra todo lo hecho en ellos.
- La temporada 2 necesita un año de planillas cerradas (panel de 12 meses, gratificación de julio, CTS, PLAME):
  `HistorialDemoSeeder` las crea, junto con correos y cuentas CTS ficticias. No llena la auditoría.
  Los videos 11, 13 y 14 cambian datos (envían boletas, cierran la CTS, registran vacaciones); para repetirlos,
  volver a sembrar la base.
- Los meses que se nombran en los recorridos (octubre y noviembre de 2026) son los del momento de la grabación.
  Si se graba en otro mes, ajustarlos en `videos.js`.
- El visor de PDF y archivos está en `tools/videos/visor.js` (usa `pdfjs-dist`; Chromium sin ventana no dibuja PDF en el video).
- Formato `.webm` (se abre en Chrome, Edge, VLC y el Reproductor multimedia de Windows 11). Para `.mp4`, convertir con
  HandBrake o `ffmpeg -i 00-resumen.webm 00-resumen.mp4`.
- El texto de los subtítulos está en `tools/videos/videos.js`; la presentación del resumen, en `tools/videos/resumen.html`.
