<?php

namespace App\Services\Ruc;

/** Datos de un contribuyente tal como los informa SUNAT (vía el proveedor). */
final readonly class DatosRuc
{
    public function __construct(
        public string $ruc,
        public string $razonSocial,
        public ?string $direccion,
        public ?string $ubigeo,
        public ?string $estado,
        public ?string $condicion,
    ) {}

    /** Los proveedores devuelven '', '-' o espacios cuando no hay dato. */
    public static function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? '');

        return $valor === '' || $valor === '-' ? null : $valor;
    }

    public static function ubigeo(mixed $valor): ?string
    {
        $valor = self::limpiar($valor);

        return $valor !== null && preg_match('/^\d{6}$/', $valor) === 1 ? $valor : null;
    }

    /** Aviso si el contribuyente no está ACTIVO y HABIDO. */
    public function alerta(): ?string
    {
        if (($this->estado === null || $this->estado === 'ACTIVO') && ($this->condicion === null || $this->condicion === 'HABIDO')) {
            return null;
        }

        $situacion = implode(' / ', array_filter([$this->estado, $this->condicion]));

        return "SUNAT reporta este RUC como {$situacion}. Verifica antes de registrar la planilla.";
    }

    /** @return array{ruc: string, razon_social: string, direccion: ?string, ubigeo: ?string, estado: ?string, condicion: ?string, alerta: ?string} */
    public function toArray(): array
    {
        return [
            'ruc' => $this->ruc,
            'razon_social' => $this->razonSocial,
            'direccion' => $this->direccion,
            'ubigeo' => $this->ubigeo,
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'alerta' => $this->alerta(),
        ];
    }
}
