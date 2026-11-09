<?php

namespace App\Services\Planilla;

use App\Jobs\EnviarBoletaJob;
use App\Models\EnvioBoleta;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Envío de boletas por correo (en cola). Solo periodos cerrados: nunca sale una boleta «BORRADOR».
 * Cada envío deja una constancia en `envios_boleta`.
 */
class EnvioBoletas
{
    /**
     * Pone en cola las boletas de los trabajadores con correo. Si se indica una línea, solo esa (reenvío).
     *
     * @return array{en_cola: int, sin_correo: list<string>}
     */
    public function enviar(Periodo $periodo, User $usuario, ?PlanillaLinea $soloLinea = null): array
    {
        if (! $periodo->estaCerrado()) {
            throw new \DomainException('Las boletas se envían por correo cuando el periodo está cerrado.');
        }

        $lineas = $soloLinea !== null ? collect([$soloLinea->loadMissing('trabajador')]) : $this->lineas($periodo);

        $enCola = 0;
        $sinCorreo = [];
        foreach ($lineas as $linea) {
            $email = $this->correo($linea);
            if ($email === null) {
                $sinCorreo[] = $linea->trabajador->nombre_completo;

                continue;
            }

            if ($soloLinea === null && $this->ultimo($linea)?->estado === EnvioBoleta::ENVIADO) {
                continue; // en un envío general no se repite a quien ya la recibió
            }

            $envio = EnvioBoleta::query()->create([
                'planilla_linea_id' => $linea->id,
                'email' => $email,
                'estado' => EnvioBoleta::PENDIENTE,
                'user_id' => $usuario->id,
            ]);
            EnviarBoletaJob::dispatch($envio->id);
            $enCola++;
        }

        return ['en_cola' => $enCola, 'sin_correo' => $sinCorreo];
    }

    /**
     * Estado de los envíos del periodo para la pantalla.
     *
     * @return array{puede_enviar: bool, con_correo: int, sin_correo: list<string>, enviados: int, pendientes: int, fallidos: int,
     *     filas: list<array{linea_id: int, nombre: string, email: string|null, estado: string|null, error: string|null, enviado_en: string|null}>}
     */
    public function resumen(Periodo $periodo): array
    {
        $lineas = $periodo->lineas()->whereNotNull('calculado_en')->with('trabajador')->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)->values();

        $ultimos = EnvioBoleta::query()
            ->whereIn('planilla_linea_id', $lineas->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('planilla_linea_id'); // queda el último envío de cada línea

        $filas = $lineas->map(function (PlanillaLinea $l) use ($ultimos) {
            /** @var EnvioBoleta|null $envio */
            $envio = $ultimos->get($l->id);

            return [
                'linea_id' => $l->id,
                'nombre' => $l->trabajador->nombre_completo,
                'email' => $envio->email ?? $this->correo($l),
                'estado' => $envio?->estado,
                'error' => $envio?->error,
                'enviado_en' => $envio?->enviado_en?->format('d/m/Y H:i'),
            ];
        });

        $contar = fn (string $estado) => $filas->where('estado', $estado)->count();

        return [
            'puede_enviar' => $periodo->estaCerrado(),
            'con_correo' => $filas->whereNotNull('email')->count(),
            'sin_correo' => array_values($filas->whereNull('email')->pluck('nombre')->all()),
            'enviados' => $contar(EnvioBoleta::ENVIADO),
            'pendientes' => $contar(EnvioBoleta::PENDIENTE),
            'fallidos' => $contar(EnvioBoleta::FALLIDO),
            'filas' => array_values($filas->all()),
        ];
    }

    /** @return Collection<int, PlanillaLinea> */
    private function lineas(Periodo $periodo): Collection
    {
        return $periodo->lineas()->whereNotNull('calculado_en')->with('trabajador')->get();
    }

    private function correo(PlanillaLinea $linea): ?string
    {
        $email = trim((string) $linea->trabajador->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function ultimo(PlanillaLinea $linea): ?EnvioBoleta
    {
        return EnvioBoleta::query()->where('planilla_linea_id', $linea->id)->latest('id')->first();
    }
}
