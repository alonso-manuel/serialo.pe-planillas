---
description: Propone y arranca la siguiente tarea pendiente del roadmap
---
1. Lee `CLAUDE.md` y `docs/roadmap.md`. Identifica la semana actual según la fecha de hoy y la primera casilla `[ ]` pendiente (si la semana anterior tiene pendientes, avísame primero).
2. Revisa el código existente relacionado (modelos, migraciones, rutas, páginas) para no duplicar nada.
3. Muéstrame un plan corto: archivos a crear/modificar, tests que escribirás y cualquier duda legal o de negocio (marca con ⚠ lo que deba confirmar un contador).
4. Espera mi visto bueno. Luego implementa: tests primero en lógica de cálculo, después código, después UI.
5. Al terminar: `composer test` y `npm run types:check` en verde, marca la casilla en `docs/roadmap.md` y propón el mensaje de commit.

$ARGUMENTS
