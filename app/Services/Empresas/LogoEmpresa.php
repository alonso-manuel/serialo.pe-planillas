<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logo de la empresa: se reduce a un máximo de 600 × 200 px, se guarda como PNG en el disco privado
 * y se incrusta en los PDF (boletas, constancias, reportes) como data URI.
 */
class LogoEmpresa
{
    public const ANCHO_MAX = 600;

    public const ALTO_MAX = 200;

    private const DISCO = 'local';

    public function guardar(Empresa $empresa, UploadedFile $archivo): void
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $origen = @imagecreatefromstring($contenido);
        if ($origen === false) {
            throw new \DomainException('No se pudo leer la imagen. Sube un PNG o JPG válido.');
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, self::ANCHO_MAX / $ancho, self::ALTO_MAX / $alto);
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        // Lienzo transparente para conservar la transparencia de los PNG.
        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, (int) imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagepng($destino, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($origen);
        imagedestroy($destino);

        $ruta = "logos/empresa-{$empresa->id}-".Str::random(8).'.png';
        Storage::disk(self::DISCO)->put($ruta, $png);

        $anterior = $empresa->logo_path;
        $empresa->update(['logo_path' => $ruta]);
        if ($anterior !== null) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
    }

    public function quitar(Empresa $empresa): void
    {
        if ($empresa->logo_path === null) {
            return;
        }

        $ruta = $empresa->logo_path;
        $empresa->update(['logo_path' => null]);
        Storage::disk(self::DISCO)->delete($ruta);
    }

    /** Contenido PNG del logo, o null si no tiene. */
    public function contenido(?Empresa $empresa): ?string
    {
        if ($empresa?->logo_path === null || ! Storage::disk(self::DISCO)->exists($empresa->logo_path)) {
            return null;
        }

        return Storage::disk(self::DISCO)->get($empresa->logo_path);
    }

    /** "data:image/png;base64,…" para incrustar en los PDF (dompdf no necesita acceder a archivos ni URLs). */
    public function dataUri(?Empresa $empresa): ?string
    {
        $png = $this->contenido($empresa);

        return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
    }
}
