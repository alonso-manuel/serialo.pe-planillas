<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use App\Models\Empresa;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en `auditorias` quién creó, cambió, eliminó o restauró el modelo (regla 7).
 *
 * - Solo los campos que cambiaron, como {campo: [antes, después]}; ignora id y fechas internas.
 * - Nunca guarda secretos: `password` se anota como "(cambiada)" y el resto de atributos
 *   ocultos ($hidden) no se registra.
 * - Acciones especiales (cerrar/reabrir periodo...): `registrarAuditoria('cerrado', [...])`.
 * - Cambios fuera de las columnas (p. ej. empresas asignadas a un usuario): `conCambiosAuditables()`
 *   antes de guardar, y `registrarCambiosPendientes()` después por si no hubo otro cambio.
 *
 * El modelo define `descripcionAuditoria()`; puede sobrescribir `empresaIdAuditoria()`.
 *
 * @mixin Model
 */
trait Auditable
{
    /** @var array<string, array{0: mixed, 1: mixed}> */
    private array $auditoriaExtra = [];

    /** Campos que nunca se registran como cambio. */
    private const AUDITORIA_IGNORAR = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public static function bootAuditable(): void
    {
        static::created(fn (self $m) => $m->auditarEvento('creado'));
        static::updated(fn (self $m) => $m->auditarEvento('actualizado'));
        static::deleted(fn (self $m) => $m->auditarEvento('eliminado'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $m) => $m->auditarEvento('restaurado'));
        }
    }

    abstract public function descripcionAuditoria(): string;

    /** Empresa a la que pertenece el registro (para filtrar la auditoría por empresa). */
    public function empresaIdAuditoria(): ?int
    {
        if ($this instanceof Empresa) {
            return $this->id;
        }

        $empresaId = $this->getAttribute('empresa_id');

        return is_int($empresaId) ? $empresaId : null;
    }

    /**
     * Agrega cambios que no son columnas del modelo al próximo registro de auditoría.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    public function conCambiosAuditables(array $cambios): static
    {
        foreach ($cambios as $campo => [$antes, $despues]) {
            if ($antes !== $despues) {
                $this->auditoriaExtra[$campo] = [$antes, $despues];
            }
        }

        return $this;
    }

    /** Si quedaron cambios extra sin registrar (el modelo no cambió columnas), los registra ahora. */
    public function registrarCambiosPendientes(string $accion = 'actualizado'): void
    {
        if ($this->auditoriaExtra !== []) {
            $this->registrarAuditoria($accion, []);
        }
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    public function registrarAuditoria(string $accion, array $cambios = []): void
    {
        if (Auditoria::estaPausada()) {
            $this->auditoriaExtra = [];

            return;
        }

        $cambios = [...$cambios, ...$this->auditoriaExtra];
        $this->auditoriaExtra = [];

        $request = app()->bound('request') ? request() : null;

        Auditoria::create([
            'user_id' => Auth::guard('web')->id(), // nunca el superadmin del panel central,
            'empresa_id' => $this->empresaIdAuditoria(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'descripcion' => mb_substr($this->descripcionAuditoria(), 0, 255),
            'accion' => $accion,
            'cambios' => $cambios === [] ? null : $cambios,
            'ip' => $request?->ip(),
        ]);
    }

    private function auditarEvento(string $accion): void
    {
        $cambios = match ($accion) {
            'creado' => $this->diferencias(array_keys($this->getAttributes()), antes: false, despues: true),
            'actualizado', 'restaurado' => $this->diferencias(array_keys($this->getChanges()), antes: true, despues: true),
            default => $this->diferencias(array_keys($this->getAttributes()), antes: true, despues: false),
        };

        if ($cambios === [] && $this->auditoriaExtra === [] && $accion === 'actualizado') {
            return; // solo cambiaron fechas internas
        }

        $this->registrarAuditoria($accion, $cambios);
    }

    /**
     * @param  list<string>  $campos
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diferencias(array $campos, bool $antes, bool $despues): array
    {
        $ocultos = $this->getHidden();
        $resultado = [];

        foreach ($campos as $campo) {
            if (in_array($campo, self::AUDITORIA_IGNORAR, true)) {
                continue;
            }

            if ($campo === 'password') {
                $resultado[$campo] = [$antes ? '(cambiada)' : null, $despues ? '(cambiada)' : null];

                continue;
            }

            if (in_array($campo, $ocultos, true)) {
                continue;
            }

            $valorAntes = $antes ? $this->valorAuditable($this->getOriginal($campo)) : null;
            $valorDespues = $despues ? $this->valorAuditable($this->getAttribute($campo)) : null;

            if ($antes && $despues && $valorAntes === $valorDespues) {
                continue;
            }

            $resultado[$campo] = [$valorAntes, $valorDespues];
        }

        return $resultado;
    }

    private function valorAuditable(mixed $valor): mixed
    {
        return match (true) {
            $valor instanceof BackedEnum => $valor->value,
            $valor instanceof DateTimeInterface => $valor->format('H:i:s') === '00:00:00'
                ? $valor->format('Y-m-d')
                : $valor->format('Y-m-d H:i:s'),
            is_scalar($valor), $valor === null => $valor,
            default => json_encode($valor),
        };
    }
}
