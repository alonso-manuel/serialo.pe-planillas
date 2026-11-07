# Instalar el proyecto y seguir con Claude Code en VS Code

Requisitos en tu PC (Windows): PHP 8.3+, Composer, Node 22+, MySQL 8 (Laragon o XAMPP sirven), Git, VS Code y Claude Code.

## 1. Crear el proyecto base

```bash
cd C:\Users\Alonso\Desktop
composer global require laravel/installer   # si no lo tienes
laravel new serialo-planillas
```

En el asistente elige: **Vue** como starter kit, autenticación de Laravel (no WorkOS), **PHPUnit** como framework de pruebas, y responde _No_ a ejecutar `npm install` si prefieres hacerlo después.

## 2. Copiar el kit encima

Descomprime `serialo-planillas-kit.zip` y copia su contenido **dentro** de `serialo-planillas/` aceptando reemplazar
(`database/seeders/DatabaseSeeder.php` se reemplaza a propósito). Quedan agregados:
`CLAUDE.md`, `docs/`, `.claude/commands/`, `app/Domain`, `app/Enums`, `app/Models`, `app/Services`, migraciones, seeders y tests.

## 3. Ajustar el modelo User

En `app/Models/User.php`:

```php
use App\Models\Concerns\TieneEmpresas;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TieneEmpresas; // + los traits que ya tenga (TwoFactorAuthenticatable, etc.)

    protected $fillable = ['name', 'email', 'password', 'rol', 'activo'];
```

(En Laravel 13 el `$fillable` puede venir como atributo `#[Fillable([...])]`: agrega `'rol', 'activo'` en esa lista.)

## 4. Base de datos y dependencias

Crea la base `serialo_planillas` en MySQL y en `.env`:

```
APP_NAME="Serialo Planillas"
APP_LOCALE=es
APP_TIMEZONE=America/Lima
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=serialo_planillas
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
```

```bash
cd serialo-planillas
composer require brick/math        # ya viene con Laravel; esto lo deja explícito (necesita >= 0.14.2)
php artisan migrate:fresh --seed
npm install
php artisan test                   # deben pasar los 8 tests del motor y de PLAME
composer dev                       # http://localhost:8000  → demo@serialo.pe / demo1234
```

## 5. Git y Claude Code

```bash
git init && git add . && git commit -m "chore: proyecto base + kit Serialo Planillas"
code .
```

En la terminal de VS Code:

```bash
claude
```

Primer mensaje sugerido:

> Lee CLAUDE.md y docs/roadmap.md. Estamos en la semana 1. Empieza por la primera tarea pendiente: módulo de empresas con autocompletado por RUC. Antes de escribir código, muéstrame el plan.

O usa directamente `/siguiente-tarea`.

## Si algo falla

- `Undefined constant RoundingMode::HalfUp` → `composer update brick/math` (necesitas 0.14.2 o superior).
- Error de clave duplicada en `users.rol` → la migración del kit se ejecutó dos veces; usa `migrate:fresh`.
- Los tests Unit del kit extienden `PHPUnit\Framework\TestCase` (no arrancan Laravel) a propósito: son rápidos.
- `composer test` corre también **PHPStan nivel 7 (Larastan)**. El kit se probó con `php -l` y con los tests del motor, pero no con PHPStan (no había `vendor/` donde se armó). Si reporta avisos en el código del kit (anotaciones genéricas de relaciones como `@return HasMany<Trabajador, $this>`, tipos de propiedades), pídele a Claude Code como primera tarea: _"corrige los errores de PHPStan del kit sin cambiar comportamiento y sin tocar los tests del motor"_.
