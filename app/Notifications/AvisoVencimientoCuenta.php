<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo al administrador de una cuenta (modo SaaS) cuando su demo o plan está por vencer o ya venció. */
class AvisoVencimientoCuenta extends Notification
{
    public function __construct(
        public readonly string $cuenta,
        public readonly string $direccion,
        public readonly string $fecha,
        public readonly bool $vencida,
        public readonly bool $esDemo,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $que = $this->esDemo ? 'La demo' : 'El plan';

        if ($this->vencida) {
            return (new MailMessage)
                ->subject("{$que} de Serialo Planillas venció")
                ->greeting('¡Hola!')
                ->line("{$que} de {$this->cuenta} venció el {$this->fecha}.")
                ->line('Tus datos se conservan: puedes ingresar y descargar todo, pero no registrar cambios.')
                ->line('Para continuar, responde este correo o comunícate con Serialo.pe.')
                ->action('Ingresar', $this->direccion)
                ->salutation('Equipo de Serialo.pe');
        }

        return (new MailMessage)
            ->subject("{$que} de Serialo Planillas vence el {$this->fecha}")
            ->greeting('¡Hola!')
            ->line("{$que} de {$this->cuenta} vence el {$this->fecha}.")
            ->line('Después de esa fecha podrás seguir viendo y descargando tus datos, pero no registrar cambios.')
            ->line('Si quieres continuar, responde este correo o comunícate con Serialo.pe.')
            ->action('Ingresar', $this->direccion)
            ->salutation('Equipo de Serialo.pe');
    }
}
