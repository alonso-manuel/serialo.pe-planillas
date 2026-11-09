<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Boleta de pago por correo, con el PDF protegido (clave = número de documento). */
class BoletaPagoMail extends Mailable
{
    public function __construct(
        public readonly string $empresa,
        public readonly string $periodo,
        public readonly string $trabajador,
        public readonly string $tipoDocumento,
        private readonly string $pdf,
        private readonly string $nombreArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // La dirección es la cuenta SMTP configurada; el nombre visible es la empresa.
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: "Boleta de pago — {$this->periodo} — {$this->empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.boleta');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->nombreArchivo)->withMime('application/pdf')];
    }
}
