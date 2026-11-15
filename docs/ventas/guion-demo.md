# Guion de la demo por videollamada (20 minutos)

Para prospectos que llegan por los anuncios de WhatsApp (ver `docs/videos/meta-ads.md`). Objetivo de la llamada:
que el prospecto vea **su** problema resuelto y acepte un siguiente paso concreto (prueba guiada o compra).

Regla de oro: **habla menos que él**. Pregunta, escucha y muestra solo lo que responde a lo que te dijo.

## Ambiente de demo (una sola vez)

En el VPS, un estudio propio con datos **100 % ficticios** (RUC 200000000xx, DNI 7000xxxx):

```bash
cd /opt/serialo
docker compose exec app php artisan estudio:datos-demo <subdominio> --correo=<tu-correo>
```

Carga 7 empresas (régimen general, pequeña y microempresa) con sus trabajadores, 11 meses de planillas cerradas
(con gratificación de julio y CTS) y el **mes actual abierto con novedades**, listo para «Calcular todas las
empresas». `--correo` hace que las boletas de prueba lleguen a tu bandeja (así muestras el envío real); sin esa
opción los trabajadores quedan sin correo. Se puede repetir sin duplicar datos. Otras empresas del catálogo:
`--empresas=andes,misti,sonrisa…` (ver `EstudioDemoSeeder::CLAVES`).

Para la boleta **firmada**: en **Empresas → editar la empresa → Firma digital** (Distribuidora Pacífico, régimen
general), subir el certificado de prueba
`tools/videos/demo-firma.p12` (clave `demo1234`, autofirmado, solo para demos).

Después de la demo el mes queda calculado; se puede volver a calcular en la siguiente demo mientras **no se cierre**.
No cierres el periodo durante la demo.

## Antes de la llamada (10 minutos)

- [ ] Lo que ya sabes del prospecto (de WhatsApp): tipo (estudio o empresa), n.º de empresas, regímenes, cómo hace hoy
      la planilla, n.º aproximado de trabajadores.
- [ ] Ambiente de demo abierto con **datos ficticios** (nunca datos de otro cliente, Ley 29733), sesión iniciada y
      un periodo **abierto** listo para calcular.
- [ ] Pestañas preparadas: Periodos, grilla de novedades, Boletas, PLAME, AFPnet, CTS, Auditoría.
- [ ] Notificaciones del computador silenciadas, zoom del navegador al 110–125 % (se lee mejor en la pantalla
      compartida).
- [ ] Una boleta firmada ya generada, por si el envío por correo demora.
- [ ] Papel para anotar sus frases textuales: luego las usas en el cierre.

## Estructura

| Min.  | Bloque                 | Objetivo                                     |
| ----- | ---------------------- | -------------------------------------------- |
| 0–3   | Apertura y diagnóstico | Que él cuente su dolor con sus palabras      |
| 3–14  | Demostración           | Mostrar su flujo mensual, de principio a fin |
| 14–17 | Preguntas y objeciones | Resolver dudas, no defenderse                |
| 17–20 | Cierre                 | Acordar el siguiente paso con fecha          |

## 1. Apertura y diagnóstico (0–3 min)

> «Gracias por el tiempo, [nombre]. La idea es que en 20 minutos veas cómo quedaría tu planilla mensual en el sistema.
> Antes de mostrarte nada, cuéntame rápido: hoy, con tus 7 empresas, ¿cómo es tu cierre de planilla cada mes?»

Preguntas para profundizar (elige dos o tres, no todas):

- «¿Cuánto tiempo te toma la planilla de todas las empresas?»
- «¿Qué es lo que más te demora o lo que más errores da?»
- «¿La PLAME la digitas o la importas?»
- «¿Quién más del estudio trabaja en las planillas?»
- «¿Te ha pasado que se dañe un Excel o que alguien cambie una fórmula?»

Anota sus frases. Si dice «me demoro tres días», esa frase vale más que cualquier función.

## 2. Demostración (3–14 min)

Muestra **un mes completo**, en el orden en que él trabaja. En cada paso: muestra, di el beneficio en una frase y
pregunta «¿así lo haces hoy?».

1. **Multiempresa (1 min).** Cambia de empresa en el selector del menú.
    > «Aquí están todas tus empresas, cada una con sus trabajadores y su régimen: general, pequeña o micro. No hay un
    > Excel por empresa.»
2. **Novedades (2 min).** Abre el periodo y escribe horas extra y una falta en la grilla.
    > «Las novedades del mes se cargan igual que en Excel: celda por celda, con Tab y Enter. Si tienes la asistencia en
    > un archivo, también se importa.»
3. **Calcular todas las empresas (2 min).** Botón «Calcular todas las empresas», elige el mes y deja que avance.
    > «Con un clic calcula todas tus empresas: ONP, AFP con sus tasas del mes, EsSalud, quinta categoría. Cada régimen
    > con sus reglas.»
    - Abre el detalle de un trabajador: «Aquí ves cada concepto y de dónde sale. Nada es una caja negra.»
4. **Boletas (2 min).** Genera las boletas en PDF y muestra una firmada.
    > «Las boletas salen en PDF, firmadas digitalmente, y se envían por correo a cada trabajador. El PDF va protegido
    > con su DNI.»
5. **PLAME y AFPnet (2 min).** Descarga los archivos del mes.
    > «Estos son los archivos para importar en la PLAME y en AFPnet. Ya no se digita trabajador por trabajador.»
6. **Lo que le preocupe (2 min).** Según lo que te dijo en el diagnóstico:
    - Si teme errores o cambios: **Auditoría** («quién cambió qué y cuándo») y **periodo cerrado** («cerrado ya no se
      puede modificar»).
    - Si le pesan los beneficios: **CTS, gratificación, vacaciones y liquidaciones**.
    - Si teme perder información: **respaldos**.

Si algo falla en vivo, no te disculpes de más: «Esto lo reviso y te lo muestro por WhatsApp hoy mismo», y sigue.

## 3. Preguntas y objeciones (14–17 min)

Primero pregunta: «¿Qué te pareció? ¿Hay algo de tu trabajo mensual que no hayamos visto?». Respuestas para las
objeciones frecuentes:

| Objeción                             | Respuesta                                                                                                                                                               |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| «Mi Excel ya funciona»               | «Seguro que sí; lo armaste tú. La pregunta es cuántas horas al mes te cuesta y qué pasa el día que se rompe. Aquí eso desaparece.»                                      |
| «Es beta, ¿y si tiene errores?»      | «Por eso estamos con pocos estudios y te acompaño directamente. Los cálculos tienen pruebas automáticas y cualquier diferencia que encuentres la corrijo de inmediato.» |
| «¿Y si cambia la ley (RMV, UIT…)?»   | «Las tasas y montos legales son parámetros con fecha de vigencia, se actualizan sin tocar tus datos y un mes antiguo siempre se recalcula igual.»                       |
| «¿Dónde quedan mis datos?»           | «En tu propio hosting o en nuestro servidor, con base de datos separada por estudio, respaldos y registro de cambios.»                                                  |
| «Está caro»                          | «Son unos S/ 21 por empresa al mes. ¿Cuántas horas te toma hoy la planilla de las 7? Con una o dos horas ahorradas ya se paga.»                                         |
| «Tengo que pensarlo / consultarlo»   | «Claro. ¿Qué es lo que te falta ver para decidir? Si quieres, lo pruebas una semana con una empresa y el jueves lo vemos juntos.»                                       |
| «¿Hace construcción civil?» (u otra) | Responde solo lo que el sistema hace hoy. Si no lo hace: «Todavía no; lo anoto y te aviso cuando esté.» **Nunca prometer fechas.**                                      |

## 4. Cierre (17–20 min)

Resume con **sus** palabras y propone un siguiente paso concreto:

> «Me dijiste que la planilla de las 7 empresas te toma [tiempo] y que la PLAME la digitas a mano. Con el sistema
> calculas todas de un clic y los archivos salen listos. Te propongo esto: te creo tu acceso, cargas una de tus
> empresas esta semana y el [día] a las [hora] lo revisamos juntos 15 minutos. ¿Te parece?»

Si está listo para comprar, presenta el plan mensual que le corresponde (alojado en nuestro servidor; incluye
actualizaciones, respaldos y soporte):

| Plan        | Para                             | Precio            |
| ----------- | -------------------------------- | ----------------- |
| Empresa     | 1 RUC, hasta 50 trabajadores     | **S/ 69 al mes**  |
| Estudio     | Hasta 40 empresas, 3 usuarios    | **S/ 149 al mes** |
| Estudio Pro | Empresas ilimitadas, 10 usuarios | **S/ 249 al mes** |

Un estudio con 7 empresas corresponde al plan **Estudio**. Argumento de valor: S/ 149 entre 7 empresas son ~S/ 21
por empresa al mes. Durante la beta se puede ofrecer un precio de lanzamiento para los primeros clientes, **siempre
con fecha límite** (por ejemplo, «los 3 primeros meses a S/ 99, si empiezas antes de fin de mes»).

Nunca termines la llamada sin **fecha y hora** del siguiente contacto.

## Después de la llamada (el mismo día)

1. Mensaje de resumen por WhatsApp:

    ```text
    Gracias por tu tiempo, [nombre]. Como quedamos:
    ✅ Te creé tu acceso: [enlace]
    ✅ Usuario: [correo] (la contraseña te llega en un mensaje aparte)
    ✅ Empieza creando una de tus empresas y cargando sus trabajadores (puedes usar la plantilla de Excel).
    📅 Nos vemos el [día] a las [hora] para revisarlo juntos.
    Cualquier duda, escríbeme por aquí.
    ```

2. Acceso de prueba: crear su estudio desde el panel de superadmin, con un plazo corto (7 días) y seguimiento acordado.
   Para la prueba, mejor datos de ejemplo o anonimizados; con datos reales de sus clientes, solo con la licencia
   contratada y los Términos y la Política de privacidad aceptados (Ley 29733).
3. Etiqueta en WhatsApp Business: «Demo hecha», y anota de qué anuncio vino (video o imagen).
4. Si no responde en 2 días: «Hola [nombre], ¿pudiste entrar al sistema? Si quieres, lo vemos 10 minutos y te ayudo
   a cargar tu primera empresa.»

## Qué medir

Por cada prospecto: anuncio de origen, fecha de la conversación, ¿demo hecha?, ¿prueba?, ¿compra?, licencia y monto.
Con 10–20 demos sabrás qué anuncio trae mejores clientes, no solo más mensajes.
