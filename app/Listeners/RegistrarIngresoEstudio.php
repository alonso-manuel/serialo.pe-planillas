<?php

namespace App\Listeners;

use App\Models\Central\Estudio;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Modo SaaS: cada ingreso de un usuario a su estudio suma en la cuenta del panel central (cuántas veces entró, cuándo y
 * quién fue la última vez), para saber si un prospecto está probando la demo. El acceso de soporte de Serialo.pe
 * no cuenta. En una instalación única no hay estudio y no hace nada.
 */
class RegistrarIngresoEstudio
{
    public function handle(Login $event): void
    {
        $estudio = tenant();
        if (! $estudio instanceof Estudio) {
            return;
        }

        $usuario = $event->user;
        if ($usuario instanceof User && $usuario->es_soporte) {
            return;
        }

        // Directo en la base central, sin tocar updated_at de la cuenta ni su auditoría.
        Estudio::query()->whereKey($estudio->getTenantKey())->toBase()->increment('ingresos', 1, [
            'ultimo_ingreso_en' => now(),
            'ultimo_ingreso_usuario' => $usuario instanceof User ? $usuario->name : null,
        ]);
    }
}
