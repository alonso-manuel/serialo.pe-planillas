<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'empresa_id', 'auditable_type', 'auditable_id', 'accion', 'cambios', 'ip'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cambios' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
