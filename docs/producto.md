# Producto: Serialo Planillas

Plan completo (con precios y estrategia de venta): documento "Plan de producto: Sistema de Planillas Serialo.pe" en Claude.
Este archivo es el resumen que el código necesita.

## Para quién

| Segmento                                                           | Necesita                                                                               |
| ------------------------------------------------------------------ | -------------------------------------------------------------------------------------- |
| Estudios contables (ej. 30 empresas con 1–3 trabajadores cada una) | Multiempresa, varios asistentes a la vez, cálculo y exportación en lote, panel del mes |
| Empresas con área de personal                                      | Una empresa, boletas, beneficios, reportes para gerencia                               |

Problema: hoy trabajan con libros de Excel con macros, un archivo por empresa, copiando datos a mano para el PLAME, un usuario a la vez.

## Licencias (afectan límites en el código)

| Licencia    | Límites                            | Precio                               |
| ----------- | ---------------------------------- | ------------------------------------ |
| Empresa     | 1 RUC, 50 trabajadores, 2 usuarios | S/ 690 + S/ 290/año desde el 2.º año |
| Estudio     | 40 empresas, 3 usuarios            | S/ 1,490 + S/ 490/año                |
| Estudio Pro | Empresas ilimitadas, 10 usuarios   | S/ 2,490 + S/ 790/año                |

Los límites se leen de la clave de licencia (ver `arquitectura.md`), nunca se escriben fijos en el código.

## Módulos y versión en que entran

| Módulo     | Función                                                                 | Versión |
| ---------- | ----------------------------------------------------------------------- | ------- |
| Núcleo     | Empresas con autocompletado por RUC, establecimientos, centros de costo | 1.0     |
| Núcleo     | Usuarios, roles, empresas asignadas                                     | 1.0     |
| Núcleo     | Parámetros por mes (RMV, UIT, AFP, ONP, EsSalud, SIS, SCTR)             | 1.0     |
| Núcleo     | Conceptos con código tabla 22 y afectaciones                            | 1.0     |
| Núcleo     | Regímenes general, pequeña, micro                                       | 1.0     |
| Núcleo     | Auditoría y respaldos automáticos                                       | 1.0     |
| Núcleo     | Regímenes agrario y construcción civil                                  | 2.0     |
| Personal   | Ficha del trabajador, historial, altas y bajas                          | 1.0     |
| Personal   | Carga masiva desde Excel                                                | 1.0     |
| Personal   | Derechohabientes                                                        | 1.1     |
| Planilla   | Novedades (días, horas, HE, faltas, subsidios)                          | 1.0     |
| Planilla   | Cálculo masivo por empresa o todas                                      | 1.0     |
| Planilla   | Cierre y reapertura de periodos                                         | 1.0     |
| Planilla   | Boletas PDF en lote y por correo                                        | 1.0     |
| Planilla   | Carga masiva de asistencia                                              | 1.1     |
| Planilla   | Quinta categoría y certificado anual                                    | 1.1     |
| Planilla   | Cuarta categoría (recibos por honorarios)                               | 1.1     |
| Planilla   | Planilla por centro de costos                                           | 1.2     |
| Beneficios | Gratificaciones jul/dic + bonificación extraordinaria                   | 1.0     |
| Beneficios | CTS may/nov + constancia PDF                                            | 1.0     |
| Beneficios | Vacaciones: cálculo, saldos, programación                               | 1.0     |
| Beneficios | Liquidación de beneficios sociales                                      | 1.1     |
| Beneficios | Comisiones; reporte de provisiones                                      | 1.2     |
| SUNAT/AFP  | PLAME .jor .rem .snl .toc                                               | 1.0     |
| SUNAT/AFP  | AFPnet                                                                  | 1.0     |
| SUNAT/AFP  | PLAME .4ta .ps4                                                         | 1.1     |
| SUNAT/AFP  | T-Registro carga masiva                                                 | 1.1     |
| SUNAT/AFP  | Validación cruzada con T-Registro                                       | 1.2     |
| Pagos      | Adelantos, préstamos, estado de cuenta                                  | 1.1     |
| Pagos      | Archivos de pago a bancos (BCP, BBVA, Interbank, Scotiabank)            | 1.2     |
| Pagos      | Asientos contables exportables                                          | 1.2     |
| Reportes   | Planilla, resumen, centralización, costo laboral                        | 1.0     |
| Reportes   | Panel del estudio (estado de cada empresa en el mes)                    | 1.0     |
| Documentos | Certificado de retenciones AFP, elección de sistema, traslado CTS       | 1.1     |
| Portal     | Portal del trabajador; firma digital de boletas                         | 2.0     |

## Criterio de calidad para vender

La planilla calculada coincide **al céntimo** con planillas reales ya presentadas (validadas por un contador asesor),
y los archivos se importan **sin errores** en PLAME y AFPnet.

## Demo

Instalación pública (ej. `demo.serialo.pe`) con 3 empresas y 10 trabajadores ficticios (`DemoSeeder`), reiniciada cada noche
con `php artisan migrate:fresh --seed --force` programado.
