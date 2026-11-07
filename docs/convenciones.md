# Convenciones de código

## Backend

- PHP 8.3 (hosting de clientes). `composer.json` fija `config.platform.php = 8.3.0` para que el lock nunca traiga paquetes que exijan 8.4 aunque desarrolles en 8.4; no quitarlo. `declare` no obligatorio; tipos estrictos en firmas y retornos. PHPStan del starter kit debe pasar.
- Controladores **delgados**: validan con `FormRequest`, autorizan con Policy, llaman a un Service, devuelven `Inertia::render` o redirect.
- Rutas en `routes/web.php` agrupadas por módulo, nombres en español: `empresas.index`, `planillas.calcular`.
- Lógica que cambia datos en varias tablas → Service con `DB::transaction`.
- Roles con `App\Enums\RolUsuario` (`Admin`, `Asistente`); nunca comparar con `'admin'`.
- Enums respaldados por string; casts en el modelo. Nada de strings mágicos (`'afp'`) fuera del enum.
- Montos: `string` en DTOs, `decimal:2` en casts, `BigDecimal` para operar. **Nunca** `(float)`, `round()`, `+` sobre montos.
- Excepciones de negocio: `\DomainException` con mensaje en español apto para mostrar al usuario.
- Consultas: siempre `with()` para evitar N+1 en listados; paginar (`->paginate(25)`).
- Migraciones nuevas: `database/migrations/AAAA_MM_DD_HHMMSS_<accion>_<tabla>.php`; nunca editar una ya publicada a clientes, crear otra.

## Auditoría

- Todo modelo de negocio nuevo usa `Auditable` y define `descripcionAuditoria()` (texto legible: "Andes S.A.C. (RUC …)"). Agregar su nombre corto al morph map.
- Test: la acción principal del módulo deja su fila en `auditorias`.

## Multiempresa (cómo hacer un módulo nuevo)

1. **Modelo** con `empresa_id`: `use PerteneceAEmpresa;` (relación `empresa()` y scopes `deEmpresa()` / `visiblesPara()`).
2. **Policy**: `use AutorizaPorEmpresa;` (view/update/delete solo en empresas visibles). Agrega reglas propias sobrescribiendo métodos (ver `PeriodoPolicy`: periodo cerrado = inmutable).
3. **Rutas** de trabajo con middleware `empresa.activa` (`RequiereEmpresaActiva`): sin empresa activa → panel con aviso.
4. **Controlador**: lista con `Modelo::deEmpresa($empresaActiva->obtener($user)->id)`; crea siempre en la empresa activa; `Gate::authorize('view|update', $modelo)` en lo demás. Nunca recibir `empresa_id` del formulario.
5. **Test Feature obligatorio**: asistente con empresa A no ve, edita ni borra registros de la empresa B (por URL directa → 403).

Las pruebas base están en `tests/Feature/Multiempresa/AislamientoTest.php`.

## Idioma

- `APP_LOCALE=es` (por defecto en `config/app.php`), `fallback_locale=en`. Mensajes propios en `lang/es/*.php` y `lang/es.json`.
- Al crear un campo nuevo de formulario, agrega su nombre visible en `lang/es/validation.php` → `attributes`.

## Frontend

- Páginas en `resources/js/pages/<modulo>/` (`Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`).
- Componentes UI de `@/components/ui` (shadcn-vue). Componentes propios del dominio en `@/components/<modulo>/`.
- Menú lateral definido en `resources/js/lib/modulos.ts`; un módulo sin `href` se muestra deshabilitado con "Pronto". Los grupos con `soloAdmin` se ocultan a los asistentes.
- Rutas tipadas con Wayfinder (`import { index } from '@/routes/empresas'`), nunca URLs a mano.
- Formularios con `useForm` / `<Form>` de Inertia; errores con `InputError`.
- Montos se muestran con `Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' })`; se envían como string.
- Tablas grandes (novedades): edición en línea, navegación con teclado, guardado por fila.

## Tests

- `tests/Unit/<Area>`: dominio puro (extiende `PHPUnit\Framework\TestCase`).
- `tests/TestCase::call()` limpia los servicios `scoped` antes de cada petición (como en producción).
- `tests/Feature/<Modulo>`: HTTP con `RefreshDatabase`; siempre incluir: listado, crear, validación, editar, borrar y **aislamiento multiempresa**.
- Nombre de método en español y snake_case con `#[Test]`: `asistente_no_ve_trabajadores_de_otra_empresa`.
- Caso legal validado por contador = test con comentario de origen (mes, régimen, "validado por ...") y sin datos personales.

## Git

- Ramas: `main` estable, `feat/<modulo>`.
- Commits convencionales en español: `feat(planilla): cierre de periodo`, `fix(calculo): prima AFP con tope RMA`, `docs: reglas de CTS`.
- Versiones semánticas: `v1.0.0`; cada versión publicada lleva notas de cambio para clientes.
