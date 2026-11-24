<?php

namespace App\Notifications;

use App\Models\Central\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al superadmin: llegó una solicitud de cuenta de prueba desde la web (en la cola, para no demorar el formulario). */
class NuevaSolicitud extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Solicitud $solicitud) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->solicitud;
        $tipo = $s->tipo === 'empresa' ? 'Empresa' : 'Estudio contable';

        $correo = (new MailMessage)
            ->subject("Nueva solicitud de prueba: {$s->organizacion}")
            ->greeting('Nueva solicitud de cuenta de prueba')
            ->line("{$s->nombre} · {$tipo}: {$s->organizacion}")
            ->line("Celular: {$s->celular} · Correo: {$s->correo}");

        if ($s->empresas !== null) {
            $correo->line("Maneja: {$s->empresas}");
        }
        if ($s->mensaje !== null) {
            $correo->line("Mensaje: {$s->mensaje}");
        }

        return $correo
            ->action('Ver en el panel', 'https://'.config('planillas.dominio_panel').'/solicitudes')
            ->salutation('Serialo Planillas');
    }
}
