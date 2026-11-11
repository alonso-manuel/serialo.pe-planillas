# Roadmap — tiempo completo desde el 9 nov 2026

Marca `[x]` al terminar (o usa `/actualizar-roadmap`). Cada tarea: código + tests + docs.

## Ya hecho (kit de arranque)

- [x] Migraciones del núcleo, personal y planilla
- [x] Enums y modelos Eloquent
- [x] Motor de cálculo mensual (`CalculadoraPlanilla`) — caso real validado al céntimo
- [x] Generadores PLAME `.rem` y `.jor` + nombre de archivo
- [x] Servicio `CalcularPlanillaPeriodo` (BD ⇄ motor)
- [x] Seeders: parámetros, AFP, tasas AFP (ejemplo), conceptos, demo ficticia
- [x] Plan de producto, documentación y comandos de Claude Code

## v1.0 — 9 nov 2026 → 8 ene 2027

### Semana 1 (9–13 nov) · Núcleo

- [x] Proyecto creado con el kit, `composer test` en verde (corregir avisos de PHPStan del kit si aparecen), primer commit
- [x] Layout: barra lateral con módulos, selector de empresa activa (sesión + `HandleInertiaRequests`)
- [x] Usuarios: CRUD admin, rol, activar/desactivar, asignar empresas
- [x] Empresas: CRUD, establecimientos, autocompletado por RUC (servicio intercambiable: API de consulta RUC o Padrón Reducido)
- [x] Policies multiempresa + test Feature "asistente no ve empresa ajena"
- [x] Parámetros: listado por clave con vigencias, alta de nueva vigencia (no editar pasadas)
- [x] Tasas AFP: grilla mensual por AFP, importar desde Excel/CSV
- [x] Conceptos: listado y edición de afectaciones (los de `sistema` no se borran)
- [x] Auditoría: trait `Auditable` en modelos clave

### Semana 2 (16–20 nov) · Personal

- [x] Trabajadores: listado con filtros, ficha por pestañas (personales, laborales, pensión/salud, cuentas)
- [x] Autocompletar nombre por DNI (servicio intercambiable)
- [x] Historial: cambios de sueldo/cargo/pensión con vigencia
- [x] Cese de trabajador (fecha y motivo tabla SUNAT)
- [x] Carga masiva desde Excel con plantilla descargable, vista previa y errores por fila

### Semana 3 (23–27 nov) · Planilla mensual + DEMO VISUAL

- [x] Periodos: abrir mes (crea líneas para trabajadores activos), estados
- [x] Grilla de novedades editable (días, faltas, HE, bonos, adelantos) estilo hoja de cálculo
- [x] Botón "Calcular" → `CalcularPlanillaPeriodo`; cálculo de todas las empresas en cola con progreso
- [x] Vista de resultado por trabajador (detalle de conceptos)
- [x] Cerrar / reabrir periodo (solo admin, auditado)
- [x] **Demo visual** con `DemoSeeder` + videos por módulo (ver `docs/videos/guiones.md`)

### Semana 4 (30 nov – 4 dic) · Gratificación de diciembre + boletas

- [x] Gratificación jul/dic + bonificación extraordinaria (reglas en `reglas-calculo.md`) con tests
- [x] Boleta de pago PDF (diseño propio), lote por empresa, zip
- [x] Envío de boletas por correo (cola)

### Semana 5 (7–11 dic) · Archivos oficiales

- [x] PLAME: `.snl`, `.toc` + pantalla de exportación (una o todas las empresas, zip)
- [x] AFPnet: archivo de planilla (TXT, todas las AFP) + licencia sin goce en la grilla
- [x] Tabla `exportaciones` (historial con hash)
- [ ] Probar importación real en PLAME y AFPnet con datos ficticios

### Semana 6 (14–18 dic) · Beneficios y reportes

- [x] CTS mayo/noviembre + constancia de depósito PDF
- [x] Vacaciones: récord, saldos, goce (afecta planilla)
- [x] Reportes: planilla, resumen, centralización, costo laboral (Excel y PDF)
- [x] Panel del estudio: estado de cada empresa en el mes (abierto / calculado / cerrado / exportado)

### Semana 7 (21–30 dic) · Producto vendible

- [x] Configuración de la empresa: logo (boletas y reportes) — pedido el 09/11
- [x] Liquidación de beneficios sociales: gratificación, CTS y vacaciones truncas, vacaciones no gozadas e indemnización; pago en la planilla del mes del cese; hoja, certificado de trabajo y carta de CTS — adelantada de v1.1 el 10/11
- [x] Panel con gráficos: costo laboral 12 meses, reparto del costo, neto por empresa, avance del mes, accesos por módulo y filtro por empresa — pedido el 09/11
- [x] Instalador web (requisitos, código de instalación, base de datos, administrador, cron) — ver docs/instalacion.md
- [x] Licencia firmada (Ed25519) + límites por plan + modo solo lectura — ver docs/licencias.md
- [x] Multitenant base (modo SaaS en el VPS): una base por estudio por subdominio, sesión/caché/archivos/cola aislados, comandos `estudio:crear` y `estudios:migrar` — ver docs/multitenant.md
- [x] Panel central (`admin.serialo.pe`): superadmin con 2FA obligatorio, cuentas (demo/cliente), alta con invitación por correo, suspender, restablecer acceso, eliminar, auditoría central — ver docs/multitenant.md
- [x] Panel central: exportar la base de una cuenta (.sql.gz) — ver docs/respaldos.md
- [x] Panel central: entrar como soporte (solo si el estudio lo autoriza por 24 h; usuario «Soporte Serialo.pe» auditado, fuera del plan) — ver docs/multitenant.md
- [x] Planes SaaS: plan y límites por cuenta (plantillas en config), editar desde el panel, vencimiento con solo lectura y avisos por correo (3 días antes y al vencer) — ver docs/multitenant.md
- [x] Respaldos automáticos: diario a las 03:00, por estudio en SaaS, 14 días, copia fuera del servidor opcional, pantalla de descarga para el admin y respaldo previo a eliminar una cuenta — ver docs/respaldos.md
- [x] Guía y scripts para un VPS dedicado (referencia) — ver docs/vps.md
- [ ] **Al final:** despliegue con Docker en el VPS compartido (dominio `impulso.cfd`: `*.impulso.cfd` y `admin.impulso.cfd`), sin chocar con las otras apps
- ~~Actualizador desde el panel (instalaciones únicas)~~ — descartado el 10/11
- ~~Script de empaquetado (zip versionado)~~ — descartado el 10/11

### Semana 8 (4–8 ene 2027) · Lanzamiento

- ~~Piloto con contador: 10 casos reales anonimizados como tests~~ — descartado el 10/11
- ~~Correcciones~~ — descartado el 10/11 (se corrige sobre la marcha)
- [x] Demos por estudio desde el panel central: ya cubierto por **Nueva cuenta** tipo demo (app limpia, vencimiento y avisos)
- [x] Manual de uso y videos cortos — ya existen; los que falten se generan cuando se pidan
- [ ] Etiqueta `v1.0.0`

## v1.1 — 11 ene → 12 feb 2027

- [x] Quinta categoría: retención mensual (art. 40 del Reglamento de la LIR) con proyección, gratificaciones por percibir, extraordinarios, cese, otros empleadores y detalle en la línea — ver docs/reglas-calculo.md
- [x] Indemnización por despido arbitrario en la liquidación: 0501 (indeterminado), 0503 (plazo fijo) y REMYPE; además, la indemnización vacacional corregida de 0911 a 0504 según la Tabla 22 — ver docs/reglas-calculo.md
- [x] T-Registro: altas y bajas en RP_<RUC>.ide/.tra/.per/.est para el PVS, revisión de datos faltantes, ficha del trabajador con los datos de SUNAT y catálogo oficial de ocupaciones — ver docs/formatos-oficiales.md
- [x] Derechohabientes: registro en la ficha con las reglas de las tablas 19, 20 y 27, bajas con motivo y archivos RD_ de alta y baja para la carga masiva en SOL — ver docs/formatos-oficiales.md
- [x] Cuarta categoría: recibos por honorarios en el periodo, retención del 8 % y PLAME .4ta / .ps4 — ver docs/formatos-oficiales.md
- [x] Asistencia desde Excel: plantilla con los trabajadores del periodo (faltas, licencias, subsidios y horas extra) e importación todo o nada con la lista de errores
- [x] Préstamos en cuotas: cronograma, descuento automático 0706 en la planilla del mes, estado de cuenta y aviso de saldo al cese
- [x] Certificado anual de rentas y retenciones de quinta (art. 45 del Reglamento de la LIR): por trabajador o de todos en zip, desde la pestaña Quinta, con firma digital

Ideas tomadas del análisis de mercado (09/11): conceptos recurrentes y proporcionales por trabajador, EPS y seguro Vida Ley, comparativo entre recálculos de un mismo mes y simulador de costo de contratación.

## v1.2 — 15 feb → 26 mar 2027

- [x] Centros de costo: catálogo por empresa, en la ficha con vigencia (historial), foto en cada línea de planilla y reporte «Costo por centro de costo»
- [ ] Comisiones de ventas (hoy solo existe la comisión de la AFP)
- [x] Pagos a bancos (genérico): Excel/CSV del periodo con banco, cuenta y neto, efectivo aparte y resumen por banco (los formatos propios de cada banco, cuando se tengan sus especificaciones)
- [x] Asiento contable por centro de costo (reporte en Excel y PDF): cargos separados por el centro de cada trabajador, abonos sin centro, debe = haber
- [ ] Validación cruzada con T-Registro (datos de la ficha frente a lo registrado en SUNAT)
- [x] Reportes analíticos: comparativo con el mes anterior por trabajador (neto, bruto y costo, variación, altas y bajas) y costo por centro de costo; el panel ya tiene el costo de 12 meses y su reparto

## v2.0 — abr → jun 2027

- [x] Firma digital de boletas y documentos laborales con el certificado de la empresa (PAdES) — adelantada el 10/11, ver docs/firma-digital.md
- [ ] Regímenes agrario y construcción civil
- [ ] Portal del trabajador (incluye su conformidad de recepción)
