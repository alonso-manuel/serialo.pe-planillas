<?php

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\TieneEmpresas;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property RolUsuario $rol
 * @property bool $activo
 * @property bool $es_soporte Usuario «Soporte Serialo.pe» (modo SaaS): no cuenta para el plan ni entra con contraseña
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'rol', 'activo'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, PasskeyAuthenticatable, TieneEmpresas, TwoFactorAuthenticatable;

    /**
     * Mismos valores por defecto que la migración, para que un modelo recién creado
     * no tenga rol ni estado nulos en memoria.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rol' => 'asistente',
        'activo' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'es_soporte' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function descripcionAuditoria(): string
    {
        return "{$this->name} ({$this->email})";
    }
}
