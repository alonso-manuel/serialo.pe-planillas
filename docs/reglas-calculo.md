# Reglas de cálculo

> Fuente de verdad del motor (`app/Domain/Planilla/CalculadoraPlanilla.php`).
> Todo número legal sale de la tabla `parametros` o `tasas_afp`, nunca del código.
> ⚠ = pendiente de verificar con la norma o con el contador asesor antes de vender.

## Redondeo

- Cada concepto se calcula con precisión completa y se redondea a **2 decimales HALF_UP** (`Monto::redondear`).
- Totales = suma de conceptos ya redondeados (así cuadra con la boleta y con el PLAME).

## Planilla mensual (implementado ✔)

| #   | Concepto               | Código         | Fórmula                                                                                                                         |
| --- | ---------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Básico                 | 0121           | `remuneracion_basica × dias_laborados / 30`                                                                                     |
| 2   | Asignación familiar    | 0201           | `RMV × pct_asig_fam` (10 %) si el trabajador la tiene; no se prorratea por días ⚠ (confirmar criterio con contador para faltas) |
| 3   | Valor hora             | —              | `(básico completo + asignación familiar) / 30 / 8`                                                                              |
| 4   | Horas extra 25 %       | 0105           | `valor_hora × 1.25 × horas` (primeras 2 horas diarias)                                                                          |
| 5   | Horas extra 35 %       | 0106           | `valor_hora × 1.35 × horas` (a partir de la 3.ª)                                                                                |
| 6   | Ingresos adicionales   | según tabla 22 | Monto de la novedad; entra a la base de pensión/EsSalud según la afectación del concepto                                        |
| 7   | **Base afecta**        | —              | suma de ingresos afectos                                                                                                        |
| 8   | ONP                    | 0607           | `base × 13 %`                                                                                                                   |
| 9   | AFP aporte obligatorio | 0608           | `base × 10 %`                                                                                                                   |
| 10  | AFP prima de seguro    | 0606           | `min(base, RMA) × prima`                                                                                                        |
| 11  | AFP comisión           | 0601           | `base × comisión` (flujo o mixta según `tipo_comision_afp`)                                                                     |
| 12  | Descuentos             | 07xx           | adelantos, préstamos, etc. (novedades)                                                                                          |
| 13  | EsSalud (empleador)    | 0804           | `max(base, RMV) × 9 %`                                                                                                          |
| 14  | SIS (empleador, micro) | 0811           | monto fijo `aporte_sis` (S/ 15) en lugar de EsSalud                                                                             |
| 15  | Neto                   | —              | `ingresos − aportes del trabajador − descuentos`                                                                                |

Un trabajador AFP sin tasas del mes ⇒ excepción (no se calcula a ciegas).

### Caso real validado ✔ (planilla presentada oct. 2026, anonimizado)

Microempresa con SIS. Básico 1,230.00, sin asignación, AFP con comisión 0 % (mixta), prima 1.37 %, aporte 10 %.

| Concepto         | Esperado     |
| ---------------- | ------------ |
| 0121 Básico      | 1,230.00     |
| 0608 Aporte      | 123.00       |
| 0606 Prima       | 16.85        |
| 0601 Comisión    | 0.00         |
| Total descuentos | 139.85       |
| **Neto**         | **1,090.15** |
| 0811 SIS         | 15.00        |

Test: `CalculadoraPlanillaTest::caso_real_microempresa_afp_con_sis`. **No modificar.**

### Casos de fórmula (aún no validados con planilla real)

- ONP + asignación + HE 2 h al 25 % y 1 h al 35 % + movilidad 100 (no afecta) + adelanto 200 ⇒ neto 1,325.82.
- EsSalud con 15 días: base 565 < RMV ⇒ 9 % de 1,130 = 101.70.
- Sueldo 20,000 AFP ⇒ prima topada en RMA 12,000 (tope de ejemplo).

**Meta antes de vender:** al menos 10 casos reales validados (ONP, AFP flujo y mixta, EsSalud, SIS, HE, faltas, subsidio, ingreso y cese a mitad de mes).

## Días laborados al abrir el mes (mes comercial de 30) ⚠ confirmar con contador

- `App\Domain\Planilla\DiasLaborados`: mes completo = 30 (también febrero); ingreso en el mes = desde ese día hasta el 30 (el 16 → 15, el 31 → 1); cese en el mes = hasta ese día (el 10 → 10; el último día del mes → 30).
- Entra al periodo quien ingresó antes de que termine el mes y no cesó antes de que empiece. Faltas, licencias y subsidios se ajustan en las novedades.

## Datos del trabajador vigentes en el mes

- El cálculo de un periodo usa sueldo, asignación familiar, sistema de pensiones (AFP, comisión) y seguro **vigentes al último día del mes** (`HistorialTrabajador::vistaAl`). Así un periodo antiguo se recalcula igual aunque después haya aumentos.
- Los cambios rigen **por mes completo** (desde el día 1). ⚠ Un aumento a mitad de mes se registra desde el mes que acuerden empleador y contador; el prorrateo por días dentro del mes no está en v1.0.
- No se registra ni anula un cambio que afecte un mes con periodo cerrado.

## Regímenes (REMYPE) ⚠ confirmar con contador

|                     | General                       | Pequeña empresa               | Microempresa                                       |
| ------------------- | ----------------------------- | ----------------------------- | -------------------------------------------------- |
| Salud               | EsSalud 9 %                   | EsSalud 9 %                   | SIS (monto fijo) o EsSalud                         |
| Gratificación       | 1 sueldo jul + 1 dic          | ½ sueldo jul + ½ dic          | No                                                 |
| CTS                 | 1 sueldo/año (½ por semestre) | 15 remuneraciones diarias/año | No                                                 |
| Vacaciones          | 30 días                       | 15 días                       | 15 días                                            |
| Asignación familiar | Sí                            | ⚠ verificar                   | ⚠ verificar (criterio mayoritario: no obligatoria) |

## Gratificaciones (semana 4) — Ley 27735 y Ley 30334

- Semestres: enero–junio (se paga en julio, hasta el 15) y julio–diciembre (diciembre, hasta el 15).
- Monto = remuneración computable × meses completos laborados / 6. Meses incompletos no cuentan.
- Remuneración computable = básico + asignación familiar + promedio de conceptos variables (HE, comisiones) si se percibieron en al menos 3 meses del semestre ⚠.
- Faltas injustificadas reducen proporcionalmente ⚠ (confirmar fórmula: 1/180 por día).
- **Bonificación extraordinaria** = gratificación × 9 % (EsSalud) o 6.75 % (EPS).
- La gratificación y la bonificación **no** están afectas a ONP, AFP ni EsSalud; sí a quinta categoría.
- Códigos PLAME: 0406 gratificación, 0312/0313 bonificación extraordinaria ⚠ verificar en tabla 22.

## CTS (semana 6) — D.S. 001-97-TR

- Depósitos: mayo (periodo noviembre–abril) y noviembre (mayo–octubre), hasta el día 15.
- Remuneración computable = básico + asignación familiar + 1/6 de la última gratificación + promedio de variables.
- Monto = computable / 12 × meses completos + computable / 360 × días del mes incompleto.
- Pequeña empresa: la mitad. Microempresa: no aplica.

## Vacaciones (semana 6)

- Récord: 1 año de servicios con el mínimo de días efectivos ⚠ (260 días en jornada de 6 días / 210 en 5 días).
- Remuneración vacacional (0118) = remuneración computable; afecta a aportes como la remuneración ordinaria.
- Vacaciones truncas (en liquidación, v1.1): computable / 12 por mes + /360 por día.

## Quinta categoría (v1.1)

Proyección anual de rentas − 7 UIT; tramos 8 % / 14 % / 17 % / 20 % / 30 %; retención mensual según el procedimiento del art. 40 del Reglamento de la LIR ⚠ verificar tramos vigentes.

## Verificaciones pendientes (lista viva)

- [ ] Códigos tabla 22: 0811 (SIS), 0312/0313/0406 (gratificación), 0904 (CTS), 0909 (movilidad).
- [ ] UIT 2026 y fecha de vigencia (se cargó 5,350 de 2025 como referencia).
- [ ] Monto vigente del aporte SIS microempresa (se cargó S/ 15).
- [ ] RMA vigente del mes (SBS) y tasas reales de cada AFP (`TasaAfpSeeder` tiene **datos de ejemplo**).
- [ ] `codigo_sunat` de cada AFP (tabla paramétrica T-Registro).
- [ ] Asignación familiar en REMYPE y prorrateo por faltas.
- [ ] Redondeo de horas extra: ¿por hora o por total? (hoy: por total).
