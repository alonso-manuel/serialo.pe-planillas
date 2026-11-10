# Serialo Planillas — guía para Claude Code

Sistema web de **planillas para el Perú**, desarrollado por **Serialo.pe – Manu Dev** (Alonso de la Cruz).
Se vende como **licencia instalable** (pago único + plan anual) a **estudios contables** (multiempresa) y **empresas**.
Calcula la planilla mensual de los regímenes general, pequeña y microempresa, beneficios sociales,
boletas en PDF y genera los archivos oficiales para **PLAME (SUNAT)**, **AFPnet** y **T-Registro**.

Lee también: `docs/producto.md` (qué se vende), `docs/roadmap.md` (qué toca ahora),
`docs/reglas-calculo.md` (fórmulas legales) y `docs/convenciones.md` (cómo se escribe el código).

## Stack

- Laravel 13 (starter kit oficial de Vue): Inertia 3, Vue 3 + TypeScript, Tailwind 4, shadcn-vue (reka-ui), Fortify (login, 2FA, passkeys), Wayfinder (rutas tipadas en TS).
- MySQL 8 en producción (los clientes usan hosting compartido con cPanel). SQLite en memoria para tests.
- `brick/math` (`BigDecimal`) para TODO cálculo de dinero o tasas.
- PDF de boletas: `barryvdh/laravel-dompdf` (plantilla Blade en `resources/views/pdf/boleta.blade.php`, servicio `BoletaPago`).
- Excel: `openspout/openspout` 5.3 (lectura/escritura .xlsx y .csv; usado en tasas AFP y carga masiva).
- Colas: driver `database` (funciona en hosting compartido con cron).

## Comandos

```bash
composer dev            # servidor + vite + cola + logs (php artisan dev)
php artisan test        # tests (rápido)
php artisan test --filter=CalculadoraPlanilla
composer test           # pint --test + phpstan + tests (lo que corre en CI)
composer lint           # formatea con Pint
npm run check:fix       # lint/format del frontend (vp)
npm run types:check     # vue-tsc
php artisan migrate:fresh --seed   # BD limpia con catálogos y datos demo
```

## Estructura del dominio

```
app/
  Domain/                      # PHP puro: sin Eloquent, sin facades, sin request. 100 % testeable.
    Planilla/                  # CalculadoraPlanilla + DTOs (ParametrosPeriodo, DatosTrabajador, EntradaPlanilla, ResultadoPlanilla, Monto)
    Sunat/Plame/               # Generadores de archivos .rem, .jor (siguen: .snl, .toc, .4ta, .ps4)
    Sunat/TRegistro/           # (v1.1)
    Afp/                       # (v1.0) archivo AFPnet
  Services/Planilla/           # Orquestan BD ⇄ dominio (CalcularPlanillaPeriodo, ParametrosRepositorio)
  Models/                      # Eloquent. Nombres de tablas en español y plural (trabajadores, novedades...)
  Models/Concerns/TieneEmpresas.php   # trait del User: empresas visibles, rol admin
  Enums/                       # RegimenLaboral, SistemaPension, SeguroSalud, TipoConcepto, EstadoPeriodo, ClaveParametro
  Http/Controllers/<Modulo>/   # Controladores delgados; validación en FormRequest
resources/js/pages/<modulo>/   # Páginas Inertia (Index.vue, Form.vue...)
tests/Unit/Planilla            # Casos de cálculo validados por contador (la red de seguridad del producto)
tests/Unit/Sunat               # Formato exacto de archivos oficiales
tests/Feature/<Modulo>         # HTTP + permisos + multiempresa
docs/                          # Documentación del producto (mantenerla al día)
```

## Reglas que NO se rompen

1. **Nunca `float` para dinero.** Usa `BigDecimal` / `Monto::de()`; montos viajan como `string` con 2 decimales;
   tasas con 4 decimales. Redondeo `RoundingMode::HalfUp` a 2 decimales **por concepto**, y los totales suman los conceptos ya redondeados.
   Columnas `decimal(12,2)` (montos) y `decimal(6,4)` (tasas); casts `'decimal:2'`.
2. **El dominio es puro.** `app/Domain` no toca la BD ni Laravel. Los datos entran por DTOs y salen en `ResultadoPlanilla`.
3. **Parámetros legales son datos, no código.** RMV, UIT, tasas, códigos SUNAT van en tablas con `vigente_desde`.
   Nunca escribir `1130` o `0.09` en el código del motor. Un periodo antiguo siempre se recalcula igual.
4. **Multiempresa en cada consulta.** Todo lo que pertenece a una empresa se filtra por `empresa_id` y por
   `$user->puedeVerEmpresa()`. Cada controlador nuevo necesita un test Feature que pruebe que un usuario NO ve datos de otra empresa.
5. **Periodo cerrado = inmutable.** No se recalcula ni se editan novedades si `estado = cerrado`.
6. **Tests primero en cálculo.** Cada regla nueva entra con un test; los casos de `tests/Unit/Planilla` validados por contador
   nunca se modifican para "hacerlos pasar". Si fallan, el error está en el motor.
7. **Auditoría.** Crear/editar/borrar empresa, trabajador, novedad, parámetro o cerrar/reabrir periodo registra fila en `auditorias`.
   También usuarios (alta, edición, rol, empresas asignadas, activación), establecimientos, tasas AFP, conceptos y la licencia.
   Se hace con el trait `Auditable` (automático) o `registrarAuditoria()` para acciones especiales.
8. **Datos reales de clientes nunca entran al repositorio**, ni en seeders, tests, capturas o demos (Ley 29733).
   Para casos de prueba se anonimizan DNI, nombres y RUC; solo quedan los números.
9. **No copiar** textos, diseños ni fórmulas de productos de terceros (Excel con macros, SmartPlani u otros). Se construye desde la norma pública.
10. Antes de dar por terminada una tarea: `composer test` en verde y `npm run types:check` sin errores.

## Idioma y nombres

- Dominio en **español** (clases, métodos, tablas, columnas, rutas, textos de UI): `Trabajador`, `calcular()`, `neto_pagar`, `/planillas/{periodo}`.
- Código técnico genérico puede quedar en inglés (helpers, componentes UI base de shadcn).
- Textos de interfaz en español de Perú, tono formal y claro ("Calcular planilla", "Cerrar periodo").
- Sin tildes en identificadores (`anio`, `pequena`), con tildes en textos visibles.

## Glosario rápido

| Término       | Significado                                                                   |
| ------------- | ----------------------------------------------------------------------------- |
| RMV           | Remuneración Mínima Vital (S/ 1,130 desde 2025)                               |
| UIT           | Unidad Impositiva Tributaria                                                  |
| PLAME         | Planilla Mensual de Pagos (SUNAT, formulario 0601)                            |
| T-Registro    | Registro de trabajadores en SUNAT (altas, bajas, datos)                       |
| AFP / SPP     | Sistema Privado de Pensiones (Habitat, Integra, Prima, Profuturo)             |
| ONP / SNP     | Sistema Nacional de Pensiones (13 %)                                          |
| CUSPP         | Código único del afiliado a AFP                                               |
| RMA           | Remuneración máxima asegurable (tope para la prima de seguro AFP)             |
| EsSalud       | Seguro de salud, 9 % a cargo del empleador, base mínima RMV                   |
| SIS           | Seguro Integral de Salud, monto fijo, solo microempresas (REMYPE)             |
| Tabla 22      | Catálogo SUNAT de conceptos remunerativos (códigos de 4 dígitos)              |
| Novedad       | Dato variable del mes: horas extra, faltas, bonos, adelantos                  |
| Gratificación | Julio y diciembre, + bonificación extraordinaria 9 % (EsSalud) o 6.75 % (EPS) |
| CTS           | Compensación por tiempo de servicios, depósitos en mayo y noviembre           |
| Quinta        | Renta de quinta categoría (impuesto a la renta del trabajador)                |

## Flujo de trabajo con Claude Code

- `/siguiente-tarea` — lee el roadmap y propone la siguiente tarea concreta.
- `/nuevo-modulo <nombre>` — crea migración, modelo, policy, controlador, FormRequest, páginas Vue y tests Feature con las convenciones.
- `/validar-calculo` — corre los tests del motor y revisa una regla contra `docs/reglas-calculo.md`.
- `/actualizar-roadmap` — marca lo terminado en `docs/roadmap.md`.

Al terminar cada tarea: actualizar `docs/roadmap.md` (casilla) y, si cambió una regla legal, `docs/reglas-calculo.md`.
Commits pequeños en español: `feat(trabajadores): carga masiva desde Excel`.
