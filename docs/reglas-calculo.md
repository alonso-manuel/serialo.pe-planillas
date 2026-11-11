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

## Novedades del mes

- Días pagados = días del mes (según ingreso/cese) − faltas − días subsidiados. ⚠ El pago del subsidio (primeros 20 días por incapacidad a cargo del empleador o subsidio de EsSalud) no está en v1.0: se registra como novedad manual.
- Una novedad por concepto y trabajador; monto 0 o vacío la elimina. Solo conceptos activos de tipo ingreso o descuento (los de sistema los calcula el motor).

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

Implementado en `App\Domain\Planilla\CalculadoraGratificacion` (motor puro) y `App\Services\Planilla\GratificacionPeriodo`.
Se agrega sola al calcular la planilla de **julio** y **diciembre** (conceptos de sistema 0406 y 0312).

- Semestres: enero–junio (se paga en julio, hasta el 15) y julio–diciembre (diciembre, hasta el 15).
- **Meses computables**: meses calendario completos del semestre. El mes de ingreso cuenta solo si se ingresó el día 1.
  **Diciembre cuenta** como mes completo si el trabajador está en planilla (se paga antes de que termine) ⚠.
- **Remuneración computable** = básico + asignación familiar + promedio de variables.
  Variables = **horas extra** (0105, 0106) ⚠ comisiones y otros variables quedan para después.
  El promedio (suma del semestre / 6) entra solo si hubo variables en **3 meses o más** del semestre.
  Se usan el básico y la asignación vigentes en el mes de pago (historial del trabajador).
- **Gratificación** = computable × meses / 6 − computable / 180 × días de falta injustificada ⚠ (1/180 por día).
  Los días subsidiados cuentan como laborados (Ley 27735, art. 6). Las **licencias sin goce** (suspensión perfecta)
  se descuentan igual que las faltas ⚠. Nunca es negativa.
- **Régimen**: general 100 %; pequeña empresa y microempresa según parámetros con vigencia
  (`factor_grati_pequena` = 0.50, `factor_grati_micro` = 0.00) ⚠ verificar.
- **Bonificación extraordinaria** = gratificación × `tasa_bonif_extra` (9 %, igual al aporte a EsSalud).
  ⚠ EPS (6.75 %) no está implementado: el sistema solo maneja EsSalud y SIS.
- Redondeo: la gratificación se redondea a 2 decimales y la bonificación se calcula sobre ese monto redondeado.
- La gratificación y la bonificación **no** están afectas a ONP, AFP ni EsSalud; sí a quinta categoría (v1.1).
- **Meses sin planilla en el sistema** (cliente que empezó a mitad del semestre): se cuentan completos según
  las fechas, sin faltas ni horas extra, y se avisa en la pantalla del periodo ⚠.
- Datos del semestre: faltas y horas extra de las planillas de los meses anteriores al de pago. En diciembre,
  las faltas y horas extra del propio diciembre no entran (aún no se conocen al día 15) ⚠.
- **Cese** antes del día 15 del mes de pago: no recibe gratificación en la planilla; se paga como
  gratificación trunca en la liquidación de beneficios.
- Códigos PLAME: 0406 gratificación, 0312 bonificación extraordinaria ⚠ verificar en tabla 22.

## CTS (semana 6) — D.S. 001-97-TR y D.S. 004-97-TR

Implementado en `App\Domain\Planilla\CalculadoraCts` (motor puro) y `App\Services\Planilla\LiquidacionesCts`.
Pantalla **Beneficios → CTS** (por empresa activa): calcular, cerrar/reabrir (admin, auditado) y constancias PDF.

- Depósitos: **mayo** (periodo noviembre del año anterior – abril) y **noviembre** (mayo – octubre), hasta el día 15. Solo soles.
- Entran los trabajadores con vínculo al cierre del periodo. Quien cesó antes la cobra en su liquidación de beneficios.
- **Tiempo computable**: desde el inicio del periodo o el ingreso, en mes comercial de 30 días (ingreso el 10 → 21 días ese mes).
- **Se descuentan** por día: faltas injustificadas, licencias sin goce y los días de **subsidio por enfermedad que pasen de 60**
  en el periodo; el subsidio por **maternidad** se computa completo ⚠.
- **Remuneración computable** = básico + asignación familiar + **1/6 de la gratificación** del periodo (diciembre para mayo,
  julio para noviembre) + promedio de horas extra (suma / 6, si las hubo en 3 meses o más) ⚠.
    - La gratificación se toma de la planilla calculada de ese mes (0406). Si no hay planilla, se **estima** con el motor de
      gratificación (meses completos, sin faltas) y se avisa.
- **Monto** = computable / 360 × días computables (= /12 por mes + /360 por día). Redondeo a 2 decimales al final.
- **Régimen**: general 100 %; pequeña empresa `factor_cts_pequena` = 0.50; microempresa `factor_cts_micro` = 0.00 ⚠.
  En pequeña empresa la gratificación también es la mitad, así que el sexto es menor.
- Meses del periodo sin planilla en el sistema: se cuentan completos, sin descuentos ni variables, y se avisa.
- **Constancia de depósito** (art. 29): una por trabajador con periodo, tiempo, remuneración computable desglosada,
  monto en cifras y letras, entidad y cuenta CTS. Lleva «BORRADOR» mientras la liquidación no está cerrada.

## Vacaciones (semana 6) — D.Leg. 713 y D.S. 012-92-TR

Implementado en `App\Domain\Planilla\CalculadoraVacaciones` (motor puro) y `App\Services\Planilla\VacacionesTrabajador`.
Pantalla **Beneficios → Vacaciones** (empresa activa): saldos por año de servicios y registro de goces (auditado).

- **Derecho**: cada año completo de servicios desde el ingreso. Días por régimen como parámetros con vigencia:
  `dias_vac_general` = 30, `dias_vac_pequena` = 15, `dias_vac_micro` = 15. Se usa el valor vigente al cumplir el año.
- **Vencimiento**: el descanso debe gozarse dentro del año siguiente; si no, el periodo queda **vencido**
  (indemnización vacacional: por ahora solo se avisa).
- **Récord** (art. 10): 260 días de labor efectiva (jornada de 6 días) o 210 (5 días). No son efectivos (art. 12):
  faltas, licencias sin goce y subsidio por enfermedad que pase de 60 días al año. Como la ficha no registra la jornada,
  el derecho se genera al cumplir el año y se **avisa** si los días no efectivos del año superan 40 ⚠
  (≈ 300 días laborables − 260 con 6 días; ≈ 249 − 210 con 5 días).
- **Goces**: fecha de inicio + días calendario. Se imputan al periodo más antiguo con saldo. No se registran ni borran
  en meses cuya planilla está cerrada, ni se cruzan entre sí, ni superan el saldo de los años cumplidos.
- **En la planilla**: los días de cada mes (calendario, tope 30; mes completo = 30) van a la línea como `dias_vacaciones`
  (columna «Vac.» de solo lectura). El básico se paga por los demás días y los de vacaciones como **0118 remuneración
  vacacional** = (básico + promedio de horas extra de los 6 meses anteriores, si las hubo en 3 o más) / 30 × días ⚠.
  Afecta a ONP, AFP y EsSalud como el sueldo. La asignación familiar se paga aparte como cada mes.
- **PLAME**: `.snl` con el código **23** (S.I. descanso vacacional); el `.jor` no cuenta horas ordinarias de esos días.
- **Gratificación y CTS**: los días de vacaciones son tiempo computable (no se descuentan).
- Pendiente: venta de vacaciones (D.Leg. 1405). Las truncas y la indemnización se pagan en la liquidación.

## Liquidación de beneficios sociales — D.S. 001-97-TR, Ley 27735, D.Leg. 713

Implementado en `App\Domain\Planilla\CalculadoraLiquidacion` (motor puro, reutiliza los de gratificación, CTS y vacaciones
con la fecha de cese) y `App\Services\Planilla\LiquidacionesBeneficios`. Pantalla **Beneficios → Liquidaciones**.

- **Gratificación trunca** (Ley 27735, art. 7): meses calendario completos del semestre en curso hasta el cese (el mes del
  cese cuenta solo si cesa el último día), con faltas a 1/180 y bonificación extraordinaria ⚠. Si cesa en **julio antes del
  día 15**, además la gratificación completa de enero–junio, que no se cobró en la planilla (0406 + 0312).
- **CTS trunca**: desde el inicio del periodo en curso (1 de mayo o 1 de noviembre) hasta el cese, en mes comercial de 30 días,
  con los mismos descuentos que la CTS. El periodo anterior lo cubre el depósito de mayo/noviembre (el trabajador tenía
  vínculo a su cierre). **Sexto**: la gratificación percibida en el periodo (julio en mayo–octubre, diciembre en
  noviembre–abril); si cesa en julio o diciembre antes del pago, la de esta liquidación; si aún no hubo, cero ⚠.
- **Vacaciones** (D.Leg. 713): días ganados y no gozados × computable / 30 (0118); si el periodo **venció** sin gozarse,
  además una **indemnización** por los mismos días (art. 23, **0504**, no remunerativa). **Truncas** del año en curso: dozavos y
  treintavos × días del régimen / 30, con al menos un mes de servicios (D.S. 012-92-TR, art. 22) ⚠.
  Computable = básico + asignación familiar + promedio de horas extra de los 6 meses previos (si las hubo en 3 o más) ⚠.
  Un adelanto de vacaciones en el año en curso solo se avisa ⚠.
- **Régimen**: factores de gratificación y CTS por parámetro (general 1, pequeña 0.5, micro 0); vacaciones 30 / 15 / 15.
- **Pago**: los conceptos entran solos en la **planilla del mes del cese** (boleta, PLAME, AFPnet). Solo las vacaciones
  (0118, 0114) son remuneración: afectas a ONP/AFP y EsSalud; gratificación, bonificación, CTS e indemnización no ⚠.
  Calcular la liquidación deja esa planilla «abierta» para recalcular; con la planilla cerrada no se calcula.
- **Documentos**: hoja de liquidación (con el pago de la planilla del mes si ya se recalculó), certificado de trabajo y
  carta a la entidad para liberar la CTS (D.S. 001-97-TR, art. 45). «BORRADOR» mientras no se cierra.
- Cerrada = inmutable: no se recalcula ni cambia el cese; solo el admin la reabre con motivo (auditado).
- **Indemnización por despido arbitrario** (opcional al calcular la liquidación; se conserva al recalcular), sobre la
  remuneración computable (básico + asignación familiar + promedio de variables) ÷ 30 por día:
    - Indeterminado (art. 38, **0501**): 45 remuneraciones diarias (1.5 sueldos) por año de servicios, fracciones por
      dozavos y treintavos; tope 360 días (12 sueldos).
    - Plazo fijo (art. 76, **0503**): 45 remuneraciones diarias por cada mes que faltaba hasta el vencimiento (días en
      treintavos); tope 12 sueldos. Pide la fecha de vencimiento del contrato.
    - Pequeña empresa 20 días por año (tope 120); microempresa 10 (tope 90); en REMYPE también para plazo fijo ⚠.
    - Nada dentro del periodo de prueba (3 meses). No remunerativa: inafecta a aportes y a renta.
- Pendiente: comisiones en el promedio.

## Quinta categoría (v1.1)

Retención mensual según el art. 40 del Reglamento de la LIR (`CalculadoraQuinta`, `RetencionQuinta`). Concepto **0605**.

1. **Renta anual** = lo afecto a quinta pagado por la empresa en los meses anteriores del año + certificados de
   **otros empleadores** (ficha del trabajador → pestaña Quinta) + lo ordinario del mes + **remuneración mensual**
   (básico completo + asignación familiar) × meses que faltan + **gratificaciones por percibir** (julio y/o diciembre,
   estimadas: remuneración mensual × meses del semestre desde el ingreso ÷ 6 × factor del régimen × (1 + bonificación)).
2. **Renta neta** = renta anual − deducción (7 UIT). **Impuesto** por tramos acumulativos (hasta 5 / 20 / 35 / 45 UIT:
   8 / 14 / 17 / 20 %; exceso 30 %). Deducción, cortes y tasas son parámetros con vigencia.
3. **Retención ordinaria**: enero–marzo impuesto ÷ 12; abril (− retenido ene–mar) ÷ 9; mayo–julio (− ene–abr) ÷ 8;
   agosto (− ene–jul) ÷ 5; setiembre–noviembre (− ene–ago) ÷ 4; diciembre impuesto − todo lo retenido.
4. **Extraordinario** del mes (todo lo afecto que no sea básico, asignación familiar, remuneración vacacional ni
   gratificación/bonificación: horas extra, bonos, novedades, conceptos de liquidación): se retiene completa la
   diferencia de impuesto que genera.
5. **Mes del cese**: sin proyección; se retiene el impuesto sobre lo percibido menos lo retenido (nunca negativo).
   Diciembre y cese informan el **saldo a favor** (lo retenido de más), que no se devuelve en la planilla.
6. Las retenciones de otros empleadores se restan como si fueran de meses anteriores.
7. Si se registra el **0605 como novedad**, se usa ese monto y no se calcula.
8. El historial es **por empresa** (cada empresa del estudio es un empleador distinto).

El detalle (renta proyectada, impuesto, divisor, retención ordinaria y extraordinaria) se guarda en la línea y se ve en
el detalle del trabajador en la planilla. No se aplica la deducción adicional de 3 UIT (gastos): va en la declaración
anual del trabajador.

**Certificado de rentas y retenciones** (art. 45 del Reglamento): por ejercicio, con las planillas **cerradas** de la
empresa: remuneraciones, gratificaciones y otros afectos; renta bruta − deducción; impuesto por tramos; retenciones
(0605) y la diferencia (saldo o retenido en exceso). Avisa si quedan planillas del año sin cerrar.

## Cuarta categoría (v1.1)

Retención por comprobante (LIR art. 74): **tasa** (8 %) sobre el monto del recibo por honorarios cuando **supera** el
monto mínimo (S/ 1,500), salvo que el prestador tenga constancia de suspensión de retenciones del ejercicio del pago.
Notas de crédito, dietas y otros: sin retención ⚠. Tasa y mínimo con vigencia en Parámetros.

## Préstamos al trabajador (v1.1)

Cuotas mensuales iguales sin intereses (la última ajusta los céntimos), descontadas solas en la planilla de su mes
con el concepto **0706** de la tabla 22 («Otros descuentos no deducibles de la base imponible»: no reduce la base de
aportes). Un 0706 registrado a mano en la grilla reemplaza la cuota. La primera cuota no puede caer en un mes cerrado;
una cuota cuenta como pagada cuando su planilla se cierra. Al cese con saldo pendiente, la liquidación lo avisa ⚠.

## Verificaciones pendientes (lista viva)

- [ ] Códigos tabla 22: 0811 (SIS), 0312/0313/0406 (gratificación), 0904 (CTS), 0909 (movilidad).
- [ ] UIT 2026 y fecha de vigencia (se cargó 5,350 de 2025 como referencia).
- [ ] Monto vigente del aporte SIS microempresa (se cargó S/ 15).
- [ ] RMA vigente del mes (SBS) y tasas reales de cada AFP (`TasaAfpSeeder` tiene **datos de ejemplo**).
- [ ] `codigo_sunat` de cada AFP (tabla paramétrica T-Registro).
- [ ] Asignación familiar en REMYPE y prorrateo por faltas.
- [ ] Redondeo de horas extra: ¿por hora o por total? (hoy: por total).
- [ ] Reportes: cuentas PCGE propuestas por concepto (6211, 6214, 6215, 6221, 6271, 4031, 4032, 4170, 1411, 4111…) y provisiones mensuales del costo laboral (gratificación 1/6, CTS 1/12, vacaciones 1/12 sobre básico + asignación).
- [ ] Vacaciones: margen de 40 días no efectivos para el aviso de récord; remuneración vacacional con promedio de horas extra; mes completo = 30 días.
- [ ] CTS: tope de 60 días de subsidio por enfermedad, maternidad completa, 1/6 de la gratificación estimada si no hay planilla, factor REMYPE 50 % / 0 %.
- [ ] Licencia sin goce: descuenta días pagados y reduce la gratificación como las faltas (1/180 por día).
- [ ] PLAME: códigos de la tabla 21 (07 faltas, 21/22 subsidios), jornada de 8 h diarias en el .jor, montos en cero omitidos en el .rem.
- [ ] Liquidación: códigos tabla 22 **0407** (gratificación proporcional), **0313** (bonificación proporcional), **0114** (vacaciones truncas) — códigos confirmados con la Tabla 22 de SUNAT (22/09/2026); la indemnización vacacional pasó de 0911 (que es «Participación en las utilidades») a **0504**; afectación de las vacaciones no gozadas y truncas; sexto de gratificación de la CTS trunca; computable de vacaciones con asignación familiar; mínimo de un mes para truncas; casos reales para tests.
- [ ] Cuarta: tasa 8 % y mínimo S/ 1,500 vigentes; dietas de directorio (retención distinta) y notas de crédito.
- [ ] Indemnización por despido: remuneración base con promedio de variables, fracciones también en REMYPE, fórmula por año en REMYPE para plazo fijo, periodo de prueba de 3 meses (6 o 12 para confianza/dirección), cuenta PCGE 623.
- [ ] Quinta: clasificación ordinario/extraordinario (horas extra y bonos como extraordinarios), bonificación extraordinaria afecta, indemnización vacacional (0504) y CTS inafectas, cese sin proyección, retenciones de otros empleadores restadas desde abril, estimación de gratificaciones por percibir (tasa 9 % aun con EPS), divisores del art. 40, UIT 2026.
- [ ] Gratificación: diciembre como mes completo, faltas 1/180 por día, variables solo HE (≥ 3 meses), factor REMYPE (50 % / 0 %), meses sin planilla asumidos completos, faltas de diciembre excluidas.
