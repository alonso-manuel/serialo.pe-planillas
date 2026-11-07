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
    - Se resuelve con `App\Services\Empresa\EmpresaActiva` (registrado `scoped`): solo empresas visibles y activas; si la de la sesión deja de serlo, se activa la primera por razón social. Los controladores filtran por `$empresaActiva->obtener($user)`.
    - Se cambia con `PUT /empresa-activa` (`empresa-activa.update`): 403 si es ajena, error de validación si está inactiva o eliminada.
- Policies por modelo comprueban `puedeVerEmpresa($modelo->empresa_id)`.

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
