<?php

namespace App\Models\Central;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Solicitud de cuenta de prueba desde la web pública (modo SaaS). Se atiende en el panel central.
 *
 * @property int $id
 * @property string $nombre
 * @property string $tipo estudio | empresa
 * @property string $organizacion
 * @property string $celular
 * @property string $correo
 * @property string|null $empresas cuántas empresas o trabajadores maneja (texto libre)
 * @property string|null $mensaje
 * @property string|null $ip
 * @property CarbonImmutable|null $atendida_en
 * @property CarbonImmutable $created_at
 */
class Solicitud extends Model
{
    use CentralConnection;

    protected $table = 'solicitudes';

    protected $fillable = ['nombre', 'tipo', 'organizacion', 'celular', 'correo', 'empresas', 'mensaje', 'ip', 'atendida_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['atendida_en' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** Celular en formato de WhatsApp (51 + 9 dígitos), o null si no parece un celular peruano. */
    public function whatsapp(): ?string
    {
        $digitos = preg_replace('/\D/', '', $this->celular) ?? '';
        if (strlen($digitos) === 11 && str_starts_with($digitos, '51')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^9\d{8}$/', $digitos) === 1 ? '51'.$digitos : null;
    }
}
