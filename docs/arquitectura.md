# Arquitectura

## Capas

```
Vue (Inertia) ──► Controller (delgado) ──► FormRequest (validación)
                        │
                        ▼
                 Services/<Modulo>  ── lee/escribe Eloquent, abre transacción, audita
                        │
                        ▼
                 Domain/<Modulo>    ── PHP puro: DTOs in → resultado out (testeado al céntimo)
```

- **Domain** no conoce Laravel. Recibe strings decimales y enums, devuelve `ResultadoPlanilla`.
- **Services** convierten modelos ⇄ DTOs (`ParametrosRepositorio`, `CalcularPlanillaPeriodo`).
- **Jobs** (cola `database`) para cálculo/exportación en lote de muchas empresas: `CalcularPlanillaEmpresaJob` por empresa, agrupados con `Bus::batch` y barra de progreso en la UI.

## Multiempresa

- Una instalación = un cliente (un estudio). Dentro hay muchas empresas.
- `users.rol`: `admin` (ve todo y configura) | `asistente` (solo empresas asignadas en `empresa_user`).

## Usuarios y acceso

- Sin registro público ni autoeliminación de cuenta: los usuarios los crea un admin en `/usuarios` (`UserPolicy`, `GestionUsuarios`).
- El admin define la contraseña inicial (el formulario sugiere una segura) y el correo queda verificado.
- Los usuarios **nunca se eliminan**, se desactivan: se conserva la autoría en auditoría y cierres de periodo.
- Un usuario inactivo no puede iniciar sesión (`Fortify::authenticateUsing`) y, si tenía sesión abierta, `AsegurarUsuarioActivo` la cierra en su siguiente petición.
- Un admin no puede desactivarse ni quitarse el rol a sí mismo; así el sistema nunca queda sin un admin activo.
- Un admin no guarda filas en `empresa_user` (ve todas); al pasar a admin se limpian.
- Empresa activa: guardada en sesión (`session('empresa_id')`), selector en la barra lateral, compartida a Vue vía `HandleInertiaRequests::share()` (`empresaActiva`, `empresas`, `auth.esAdmin`).
    - Se resuelve con `App\Services\Empresas\EmpresaActiva` (registrado `scoped`): solo empresas visibles y activas; si la de la sesión deja de serlo, se activa la primera por razón social. Los controladores filtran por `$empresaActiva->obtener($user)`.
    - Se cambia con `PUT /empresa-activa` (`empresa-activa.update`): 403 si es ajena, error de validación si está inactiva o eliminada.
- Policies por modelo comprueban `puedeVerEmpresaId($modelo->empresa_id)` mediante el trait `AutorizaPorEmpresa`; los modelos usan `PerteneceAEmpresa` y las rutas de trabajo el middleware `empresa.activa`.

## Empresas y consulta de RUC

- Solo el admin gestiona empresas (`EmpresaPolicy`). No se eliminan: se desactivan y salen del selector de empresa activa.
- El RUC se valida con `App\Domain\Sunat\Ruc` (11 dígitos, prefijo 10/15/16/17/20, dígito verificador módulo 11) y **no cambia** después de crear la empresa.
- Toda empresa nace con su establecimiento `0000 · Principal`. Los establecimientos van anidados con `scopeBindings()`; el `0000` no se elimina ni cambia de código.
- SIS solo para régimen de microempresa (validado en `GuardarEmpresaRequest`).
- Consulta RUC intercambiable (`App\Services\Ruc\ConsultaRuc`), elegida con `RUC_DRIVER`:

| Driver                  | Costo                   | Cobertura                        | Configuración                                 |
| ----------------------- | ----------------------- | -------------------------------- | --------------------------------------------- |
| `openruc` (por defecto) | Gratis, sin token       | Solo personas jurídicas (RUC 20) | nada                                          |
| `decolecta`             | Plan gratuito con token | Todos los RUC                    | `DECOLECTA_TOKEN` (registro en decolecta.com) |
| `ninguno`               | —                       | —                                | datos a mano                                  |

Con `DECOLECTA_TOKEN`, el driver `openruc` consulta los RUC 20 en OpenRUC y el resto (personas naturales) en Decolecta (`ConsultaRucCombinada`), para reservar el cupo mensual. Cada consulta exitosa se guarda 24 h en caché (`ConsultaRucEnCache`). Ruta `GET /empresas/consulta-ruc/{ruc}` limitada a 20/min. Pendiente v1.1: driver Padrón Reducido.

## Parámetros legales

- Pantalla `/parametros` (solo admin): una tarjeta por `ClaveParametro` con valor vigente, próximo e historial.
- **No se editan.** Un cambio legal = vigencia nueva desde el día 1 de un mes (`NuevaVigenciaRequest`), con base legal obligatoria.
- Tasas: se guardan como fracción (`0.0900`) y se ingresan/muestran en porcentaje (`9.00 %`). Formato y conversión con `BigDecimal` en el enum.
- Anular solo si ningún periodo **cerrado** cae en su rango de vigencia (`VigenciasParametros::esAnulable`). Base legal con la palabra "VERIFICAR" se marca en pantalla para revisión del contador.

## Tasas AFP

- Pantalla `/tasas-afp` (solo admin): resumen anual (mes completo/incompleto/vacío) y grilla editable del mes, con "Copiar del mes anterior".
- Tasas en porcentaje en pantalla y archivos; se guardan como fracción (`App\Domain\Planilla\Porcentaje`, sin float). Rangos de seguridad: aporte 0–20 %, prima y comisiones 0–5 %.
- Un mes con algún periodo **cerrado** (de cualquier empresa) no se modifica, ni por grilla ni por importación.
- Importación (`ImportadorTasasAfp`, openspout): plantilla `.xlsx` (columnas `anio, mes, afp, aporte, prima, comision_flujo, comision_mixta, rma`) prellenada con el último mes; acepta `.xlsx` o `.csv` (`,` o `;`, decimales con coma). Vista previa en sesión con errores por fila → confirmar guarda solo las válidas. Si el aporte viene < 1 se asume fracción y se convierte, con aviso.

## Conceptos (tabla 22)

- Pantalla `/conceptos` (solo admin), agrupada por tipo. Se cargan solo los conceptos que se usan; el contador agrega los demás desde la tabla 22 oficial (no se transcribe la tabla completa).
- **Sistema** (los calcula el motor): solo se renombran; no cambian afectaciones, no se desactivan ni eliminan.
- **Propios**: solo ingresos (01xx–05xx, 09xx, 10xx) o descuentos (07xx); el tipo se deduce del código. Los descuentos no llevan afectaciones. Se eliminan solo si nunca se usaron en novedades ni resultados; si no, se desactivan.
- Cambiar una afectación vale para cálculos nuevos y recálculos de periodos abiertos; los cerrados conservan su resultado.

## Auditoría

- Trait `App\Models\Concerns\Auditable` en Empresa, Establecimiento, User, Trabajador, Novedad, Parametro, TasaAfp, Concepto y Periodo: registra creado/actualizado/eliminado/restaurado con usuario, empresa, IP, descripción y `{campo: [antes, después]}`.
- Nunca guarda secretos: `password` = "(cambiada)"; los atributos ocultos no se registran. Cambios solo de fechas internas no generan fila.
- Acciones especiales: `$modelo->registrarAuditoria('cerrado', [...])`. Cambios fuera de columnas (empresas asignadas): `conCambiosAuditables()` + `registrarCambiosPendientes()`.
- `auditable_type` usa nombres cortos (morph map en `AppServiceProvider`). Seeders e instalador envuelven en `Auditoria::sinRegistrar()`.
- `Auditoria` es inmutable (updating/deleting cancelados). Se conserva indefinidamente. Pantalla `/auditoria` (solo admin) con filtros por empresa, usuario, registro, acción y fechas.

## Trabajadores

- `/trabajadores` (admin y asistente, middleware `empresa.activa`): solo los de la empresa activa; se crean siempre en ella (se ignora `empresa_id` del formulario).
- Ficha en 4 pestañas (personales, laborales, pensión y salud, cuentas). Documento: DNI 8 dígitos; CE y pasaporte 4–12 caracteres; único por empresa y tipo.
- AFP exige AFP, CUSPP (12) y tipo de comisión; al pasar a ONP se limpian. SIS solo si la empresa es microempresa.
- Avisos no bloqueantes: básico < RMV vigente (tiempo parcial) y menor de edad.
- Eliminar solo si nunca estuvo en planilla (se borra del todo para poder registrarlo de nuevo); si no, cese.
- Enums: `TipoDocumento` (01, 04, 07 ⚠ confirmar), `TipoContrato` (simplificado hasta T-Registro), `TipoComisionAfp`.

## Consulta de DNI

- `App\Services\Dni\ConsultaDni` (driver `DNI_DRIVER=decolecta|ninguno`, token `DECOLECTA_TOKEN` compartido con RUC; plan gratuito ~100 consultas/mes). Sin token, el botón aparece desactivado.
- Ruta `GET /trabajadores/consulta-dni/{dni}` (empresa activa, 10/min). No gasta cupo si el DNI es inválido o ya está registrado en la empresa.
- Ley 29733: solo se usan apellidos y nombres; caché de 10 min con la clave cifrada (sha256). Un 429 del proveedor = cupo agotado (mensaje claro).

## Licencia (v1.0, semana 7)

- Clave = JSON firmado con **Ed25519** (`sodium_crypto_sign_detached`), en base64:
  `{cliente, plan, max_empresas, max_usuarios, max_trabajadores, dominio, emitida, actualizaciones_hasta}`.
- La **clave pública** va dentro del sistema; la **privada** solo en la PC de Serialo.pe (generador de licencias aparte, nunca en este repo).
- Verificación: middleware al iniciar sesión + comando programado diario. Si el dominio no coincide o la firma falla → modo solo lectura (nunca borrar datos).
- `actualizaciones_hasta` controla si el actualizador puede descargar nuevas versiones.

## Instalador web (semana 7)

`/instalar` (solo si no existe `storage/instalado`): verifica PHP y extensiones (bcmath o gmp, intl, mbstring, pdo_mysql, zip, sodium), permisos de `storage/`, pide datos de BD, escribe `.env`, ejecuta migraciones y seeders de catálogos, activa la licencia, crea el primer admin.

## Actualizaciones (semana 7)

Panel admin → "Buscar actualizaciones": consulta un JSON en serialo.pe con la última versión; si la licencia lo permite, descarga el zip firmado, verifica la firma, activa modo mantenimiento, respalda la BD, reemplaza archivos (sin `vendor` del cliente si no cambió), ejecuta `migrate --force` y sale de mantenimiento.

## Respaldos

`spatie/laravel-backup` (BD + storage) programado diario, retención 30 días; descarga desde el panel admin.

## PDFs

Boletas, constancias de CTS y liquidaciones con Blade + dompdf. Una boleta por página; lote = un PDF por empresa o zip.

## Hosting objetivo

Hosting compartido con cPanel (PHP 8.3, MySQL). Por eso: cola `database` + cron `schedule:run` cada minuto, sin Redis, sin Node en producción (los assets se compilan antes de empaquetar).
