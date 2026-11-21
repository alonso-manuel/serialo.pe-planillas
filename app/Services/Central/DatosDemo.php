<?php

namespace App\Services\Central;

use App\Models\Auditoria;
use App\Models\Central\Estudio;
use App\Models\Trabajador;
use Database\Seeders\EstudioDemoSeeder;
use Database\Seeders\HistorialDemoSeeder;

/**
 * Carga datos 100 % FICTICIOS en un estudio (modo SaaS) para que el prospecto pruebe el sistema: empresas de
 * varios regímenes con sus trabajadores, un año de planillas cerradas y el mes actual abierto con novedades.
 * RUC 200000000xx y DNI 7000xxxx inventados (Ley 29733). Se puede repetir: lo que ya existe no se duplica.
 */
class DatosDemo
{
    /** Mezcla de régimen general, pequeña y microempresa, como un estudio contable típico. */
    public const POR_DEFECTO = ['pacifico', 'misti', 'andes', 'huallaga', 'chira', 'ucayali', 'espiga'];

    /** @param  list<string>  $empresas  claves de EstudioDemoSeeder::CLAVES */
    public function cargar(Estudio $estudio, array $empresas = self::POR_DEFECTO, ?string $correo = null): void
    {
        $estudio->run(function () use ($empresas, $correo): void {
            Auditoria::sinRegistrar(function () use ($empresas, $correo): void {
                $datos = app(EstudioDemoSeeder::class);
                $datos->empresasElegidas = $empresas;
                $datos->setContainer(app())->__invoke();

                app(HistorialDemoSeeder::class)->setContainer(app())->__invoke();

                // Los correos inventados (@ejemplo.test) rebotarían en el envío real y dañan la reputación del
                // dominio: se reemplazan por el correo indicado o se quitan.
                Trabajador::query()->where('numero_documento', 'like', '7000%')
                    ->update(['email' => $correo]);
            });
        });
    }
}
