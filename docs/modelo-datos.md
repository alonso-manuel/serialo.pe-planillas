# Modelo de datos

Migraciones en `database/migrations/2026_11_09_*`. Montos `decimal(12,2)`, tasas `decimal(6,4)`.

| Tabla                | Propósito                                                         | Claves importantes                                             |
| -------------------- | ----------------------------------------------------------------- | -------------------------------------------------------------- |
| users (+rol, activo) | Usuarios del estudio                                              | rol: admin / asistente                                         |
| empresas             | Empleadores (RUC)                                                 | ruc único, regimen_laboral, seguro_salud, softDeletes          |
| establecimientos     | Locales de la empresa (código SUNAT)                              | empresa_id, codigo '0000'                                      |
| empresa_user         | Qué empresas ve cada asistente                                    |                                                                |
| parametros           | RMV, UIT, tasas, con vigencia                                     | único (clave, vigente_desde)                                   |
| afps                 | Habitat, Integra, Prima, Profuturo                                | codigo único, codigo_sunat                                     |
| tasas_afp            | Tasas mensuales SBS por AFP                                       | único (afp_id, anio, mes)                                      |
| conceptos            | Tabla 22 SUNAT + afectaciones                                     | codigo_plame único, tipo, afecto_*, sistema                    |
| trabajadores         | Ficha del trabajador                                              | único (empresa, tipo_documento, numero_documento), softDeletes |
| trabajador_historial | Cambios de sueldo, cargo, pensión con fecha                       | campo, valor_anterior, valor_nuevo, vigente_desde              |
| periodos             | Mes de planilla por empresa                                       | único (empresa, anio, mes), estado abierto/calculado/cerrado   |
| planilla_lineas      | Un trabajador en un periodo: entradas (días, horas) y totales     | único (periodo, trabajador)                                    |
| novedades            | Conceptos variables del mes (bono, adelanto, falta)               | planilla_linea_id, concepto_id, monto                          |
| planilla_conceptos   | Resultado del cálculo por concepto (fuente del PLAME y la boleta) | monto_devengado, monto_pagado                                  |
| auditorias           | Quién cambió qué (inmutable)                                      | morphs auditable, descripcion, accion, cambios json, ip        |

## Relaciones

```
Empresa 1─* Establecimiento
Empresa 1─* Trabajador 1─* TrabajadorHistorial
Empresa 1─* Periodo 1─* PlanillaLinea *─1 Trabajador
PlanillaLinea 1─* Novedad *─1 Concepto
PlanillaLinea 1─* PlanillaConcepto *─1 Concepto
Afp 1─* TasaAfp ; Trabajador *─1 Afp
User *─* Empresa (empresa_user)
```

## Pendientes de diseñar (cuando llegue su semana)

- `gratificaciones`, `cts_depositos`, `vacaciones` (saldos y goces) — semana 4 y 6.
- `centros_costo` (v1.2), `derechohabientes` (v1.1), `prestamos` (v1.1).
- `exportaciones` (historial de archivos PLAME/AFPnet generados, con hash) — semana 5.
