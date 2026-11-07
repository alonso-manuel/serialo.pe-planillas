# Convenciones de código

## Backend

- PHP 8.3, `declare` no obligatorio; tipos estrictos en firmas y retornos. PHPStan del starter kit debe pasar.
- Controladores **delgados**: validan con `FormRequest`, autorizan con Policy, llaman a un Service, devuelven `Inertia::render` o redirect.
- Rutas en `routes/web.php` agrupadas por módulo, nombres en español: `empresas.index`, `planillas.calcular`.
- Lógica que cambia datos en varias tablas → Service con `DB::transaction`.
- Enums respaldados por string; casts en el modelo. Nada de strings mágicos (`'afp'`) fuera del enum.
- Montos: `string` en DTOs, `decimal:2` en casts, `BigDecimal` para operar. **Nunca** `(float)`, `round()`, `+` sobre montos.
- Excepciones de negocio: `\DomainException` con mensaje en español apto para mostrar al usuario.
- Consultas: siempre `with()` para evitar N+1 en listados; paginar (`->paginate(25)`).
- Migraciones nuevas: `database/migrations/AAAA_MM_DD_HHMMSS_<accion>_<tabla>.php`; nunca editar una ya publicada a clientes, crear otra.

## Frontend

- Páginas en `resources/js/pages/<modulo>/` (`Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`).
- Componentes UI de `@/components/ui` (shadcn-vue). Componentes propios del dominio en `@/components/<modulo>/`.
- Rutas tipadas con Wayfinder (`import { index } from '@/routes/empresas'`), nunca URLs a mano.
- Formularios con `useForm` / `<Form>` de Inertia; errores con `InputError`.
- Montos se muestran con `Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' })`; se envían como string.
- Tablas grandes (novedades): edición en línea, navegación con teclado, guardado por fila.

## Tests

- `tests/Unit/<Area>`: dominio puro (extiende `PHPUnit\Framework\TestCase`).
- `tests/Feature/<Modulo>`: HTTP con `RefreshDatabase`; siempre incluir: listado, crear, validación, editar, borrar y **aislamiento multiempresa**.
- Nombre de método en español y snake_case con `#[Test]`: `asistente_no_ve_trabajadores_de_otra_empresa`.
- Caso legal validado por contador = test con comentario de origen (mes, régimen, "validado por ...") y sin datos personales.

## Git

- Ramas: `main` estable, `feat/<modulo>`.
- Commits convencionales en español: `feat(planilla): cierre de periodo`, `fix(calculo): prima AFP con tope RMA`, `docs: reglas de CTS`.
- Versiones semánticas: `v1.0.0`; cada versión publicada lleva notas de cambio para clientes.
