<?php

namespace App\Jobs;

use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Services\Central\DatosDemo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Carga los datos ficticios en una cuenta recién creada desde el panel (tarda; por eso va en la cola). */
class CargarDatosDemoJob implements ShouldQueue
{
    use Queueable;

    /** Un solo intento: si falla, se repite a mano con `estudio:datos-demo`. */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $estudioId) {}

    public function handle(DatosDemo $datos): void
    {
        $estudio = Estudio::query()->find($this->estudioId);

        if ($estudio === null) {
            return; // la cuenta se eliminó antes de que corriera la cola
        }

        $datos->cargar($estudio);

        AuditoriaCentral::registrar('datos demo', "Cuenta {$estudio->nombre}", $estudio->id, ['empresas' => DatosDemo::POR_DEFECTO]);
    }
}
