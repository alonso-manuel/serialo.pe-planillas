<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo al administrador de un estudio nuevo (o que pidió restablecer acceso) para crear su contraseña. */
class InvitacionEstudio extends Notification
{
    public function __construct(
        public readonly string $estudio,
        public readonly string $direccion,
        public readonly string $enlace,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $horas = (int) round((int) config('auth.passwords.users.expire') / 60);

        return (new MailMessage)
            ->subject('Tu cuenta de Serialo Planillas está lista')
            ->greeting('¡Hola!')
            ->line("La cuenta de {$this->estudio} en Serialo Planillas está lista.")
            ->line("Tu dirección de ingreso es {$this->direccion}")
            ->action('Crear mi contraseña', $this->enlace)
            ->line("El enlace vence en {$horas} horas. Si vence, pide uno nuevo a Serialo.pe.")
            ->salutation('Equipo de Serialo.pe');
    }
}
