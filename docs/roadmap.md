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
- [ ] Grilla de novedades editable (días, faltas, HE, bonos, adelantos) estilo hoja de cálculo
- [ ] Botón "Calcular" → `CalcularPlanillaPeriodo`; cálculo de todas las empresas en cola con progreso
- [ ] Vista de resultado por trabajador (detalle de conceptos)
- [ ] Cerrar / reabrir periodo (solo admin, auditado)
- [ ] **Demo visual** con `DemoSeeder` + grabar video de 3–5 min

### Semana 4 (30 nov – 4 dic) · Gratificación de diciembre + boletas

- [ ] Gratificación jul/dic + bonificación extraordinaria (reglas en `reglas-calculo.md`) con tests
- [ ] Boleta de pago PDF (diseño propio), lote por empresa, zip
- [ ] Envío de boletas por correo (cola)

### Semana 5 (7–11 dic) · Archivos oficiales

- [ ] PLAME: `.snl`, `.toc` + pantalla de exportación (una o todas las empresas, zip)
- [ ] AFPnet: archivo por AFP
- [ ] Tabla `exportaciones` (historial con hash)
- [ ] Probar importación real en PLAME y AFPnet con datos ficticios

### Semana 6 (14–18 dic) · Beneficios y reportes

- [ ] CTS mayo/noviembre + constancia de depósito PDF
- [ ] Vacaciones: récord, saldos, goce (afecta planilla)
- [ ] Reportes: planilla, resumen, centralización, costo laboral (Excel y PDF)
- [ ] Panel del estudio: estado de cada empresa en el mes (abierto / calculado / cerrado / exportado)

### Semana 7 (21–30 dic) · Producto vendible

- [ ] Instalador web
- [ ] Licencia firmada (Ed25519) + límites por plan + modo solo lectura
- [ ] Actualizador desde el panel
- [ ] Respaldos automáticos
- [ ] Script de empaquetado (build de assets, sin dev-dependencies, zip versionado)

### Semana 8 (4–8 ene 2027) · Lanzamiento

- [ ] Piloto con contador: 10 casos reales anonimizados como tests
- [ ] Correcciones
- [ ] Demo pública `demo.serialo.pe` con reinicio nocturno
- [ ] Manual de uso y videos cortos
- [ ] Etiqueta `v1.0.0`

## v1.1 — 11 ene → 12 feb 2027

Quinta categoría, liquidaciones, T-Registro, cuarta categoría, carga masiva de asistencia, derechohabientes, adelantos y préstamos, documentos laborales.

## v1.2 — 15 feb → 26 mar 2027

Centros de costo, comisiones, pagos a bancos, asientos contables, validación con T-Registro, reportes analíticos.

## v2.0 — abr → jun 2027

Regímenes agrario y construcción civil, portal del trabajador, firma digital de boletas.
