---
description: Crea un módulo CRUD completo siguiendo las convenciones del proyecto
argument-hint: <nombre del módulo en español, ej. "centros de costo">
---

Crea el módulo **$ARGUMENTS** siguiendo `docs/convenciones.md` y `docs/modelo-datos.md`:

1. Migración (si falta) con `empresa_id` cuando el dato pertenece a una empresa; montos `decimal(12,2)`.
2. Modelo con `$fillable`, `casts`, relaciones, trait `Auditable` + `descripcionAuditoria()` y su nombre en el morph map.
3. Multiempresa según `docs/convenciones.md` → "Multiempresa": trait `PerteneceAEmpresa` en el modelo, policy con `AutorizaPorEmpresa`, rutas con middleware `empresa.activa`.
4. `FormRequest` Store/Update con mensajes en español.
5. Controlador delgado + rutas con nombres en español.
6. Páginas Vue (`Index`, `Create`/`Edit` o un `Form` compartido) con shadcn-vue, Wayfinder y la empresa activa (`EmpresaActiva::obtener($user)` en el backend, `page.props.empresaActiva` en Vue).
7. Entrada en la barra lateral: agrega el `href` de Wayfinder al módulo en `resources/js/lib/modulos.ts` (deja de mostrarse como "Pronto").
8. Tests Feature: listar, crear, validar, editar, eliminar y aislamiento multiempresa.
9. Actualiza `docs/modelo-datos.md` y `docs/roadmap.md`.

Muéstrame el plan antes de escribir código.
