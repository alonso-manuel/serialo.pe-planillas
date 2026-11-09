<?php

namespace App\Jobs;

use App\Enums\TipoDocumento;
use App\Mail\BoletaPagoMail;
use App\Models\EnvioBoleta;
use App\Services\Planilla\BoletaPago;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía la boleta de un trabajador. Respeta el límite de correos por minuto del hosting:
 * si se alcanza, el job vuelve a la cola (no cuenta como fallo). Si el correo falla, se reintenta
 * hasta 3 veces y el error queda en la constancia.
 */
class EnviarBoletaJob implements ShouldQueue
{
    use Queueable;

    /** Reintentos por error de correo (las esperas por el límite no cuentan). */
    public int $maxExceptions = 3;

    /** @var list<int> segundos entre reintentos */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $envioId) {}

    /** Mientras espere por el límite de envío, sigue intentando hasta por un día. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('correos-boletas')];
    }

    public function handle(BoletaPago $boletas): void
    {
        $envio = EnvioBoleta::query()->with('linea.periodo.empresa', 'linea.trabajador')->find($this->envioId);
        if ($envio === null || $envio->estado === EnvioBoleta::ENVIADO) {
            return;
        }

        $linea = $envio->linea;
        $periodo = $linea->periodo;
        $t = $linea->trabajador;

        try {
            Mail::to($envio->email)->send(new BoletaPagoMail(
                empresa: (string) $periodo->empresa?->razon_social,
                periodo: $periodo->etiqueta(),
                trabajador: trim("{$t->nombres} {$t->apellido_paterno}"),
                tipoDocumento: $t->tipo_documento === TipoDocumento::Dni ? 'DNI' : mb_strtolower($t->tipo_documento->etiqueta()),
                pdf: $boletas->pdfProtegido($periodo, $linea),
                nombreArchivo: $boletas->nombreArchivo($periodo, $linea),
            ));
        } catch (Throwable $e) {
            $envio->update(['error' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        }

        $envio->update(['estado' => EnvioBoleta::ENVIADO, 'enviado_en' => now(), 'error' => null]);
    }

    public function failed(?Throwable $e): void
    {
        EnvioBoleta::query()->whereKey($this->envioId)->where('estado', EnvioBoleta::PENDIENTE)->update([
            'estado' => EnvioBoleta::FALLIDO,
            'error' => mb_substr($e?->getMessage() ?? 'Error desconocido al enviar el correo.', 0, 500),
        ]);
    }
}
