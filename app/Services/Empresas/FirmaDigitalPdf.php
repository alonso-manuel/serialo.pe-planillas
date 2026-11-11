<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\FirmaDigital;
use App\Models\User;
use Carbon\CarbonImmutable;
use ddn\sapp\PDFDoc;
use DomainException;
use RuntimeException;

/**
 * Firma digital de los documentos laborales de una empresa (PAdES básico, adbe.pkcs7.detached).
 *
 * - El admin registra el certificado .pfx/.p12 del representante legal y su clave; se guardan cifrados.
 * - Cada PDF (boletas, liquidación, certificado de trabajo, cartas y constancias de CTS) se firma al generarse.
 *   Adobe Reader muestra «Firmado por …» y avisa si el archivo se modificó después.
 * - Sin certificado vigente, el documento sale sin firma digital (con espacio para firmar a mano).
 * - Si la firma falla, no se entrega un PDF que diga estar firmado: se lanza el error.
 */
class FirmaDigitalPdf
{
    /** @return array{titular: string, documento: string|null, emisor: string, numero_serie: string, huella: string, valido_desde: CarbonImmutable, valido_hasta: CarbonImmutable} */
    public static function leer(string $p12, string $clave): array
    {
        if (! openssl_pkcs12_read($p12, $partes, $clave)) {
            throw new DomainException('No se pudo abrir el certificado: la clave es incorrecta o el archivo no es un .pfx/.p12.');
        }

        $x509 = openssl_x509_parse($partes['cert']);
        if ($x509 === false || ! openssl_x509_check_private_key($partes['cert'], $partes['pkey'])) {
            throw new DomainException('El archivo no trae un certificado con su clave privada.');
        }

        $sujeto = $x509['subject'];
        $emisor = $x509['issuer'];
        $texto = fn (mixed $v): ?string => is_array($v) ? (string) end($v) : (is_string($v) ? $v : null);

        return [
            'titular' => $texto($sujeto['CN'] ?? null) ?? $texto($sujeto['O'] ?? null) ?? 'Sin nombre',
            'documento' => $texto($sujeto['serialNumber'] ?? null),
            'emisor' => $texto($emisor['CN'] ?? null) ?? $texto($emisor['O'] ?? null) ?? 'Desconocido',
            'numero_serie' => (string) ($x509['serialNumberHex'] ?? $x509['serialNumber'] ?? ''),
            'huella' => (string) openssl_x509_fingerprint($partes['cert'], 'sha256'),
            'valido_desde' => CarbonImmutable::createFromTimestamp($x509['validFrom_time_t'], (string) config('app.timezone')),
            'valido_hasta' => CarbonImmutable::createFromTimestamp($x509['validTo_time_t'], (string) config('app.timezone')),
        ];
    }

    public function registrar(Empresa $empresa, string $p12, string $clave, User $usuario): FirmaDigital
    {
        $datos = self::leer($p12, $clave);

        if ($datos['valido_hasta']->isPast()) {
            throw new DomainException('El certificado venció el '.$datos['valido_hasta']->format('d/m/Y').'. Renuévalo con la entidad que lo emitió.');
        }
        if ($datos['valido_desde']->subMinutes(5)->isFuture()) {
            throw new DomainException('El certificado recién será válido desde el '.$datos['valido_desde']->format('d/m/Y').'.');
        }

        $firma = FirmaDigital::query()->firstOrNew(['empresa_id' => $empresa->id]);
        $firma->fill([...$datos, 'certificado' => base64_encode($p12), 'clave' => $clave, 'registrado_por' => $usuario->id])->save();

        return $firma;
    }

    public function quitar(Empresa $empresa): void
    {
        FirmaDigital::query()->where('empresa_id', $empresa->id)->first()?->delete();
    }

    /** Firma vigente de la empresa (null si no tiene o venció). */
    public function vigente(Empresa $empresa): ?FirmaDigital
    {
        $firma = FirmaDigital::query()->where('empresa_id', $empresa->id)->first();

        return $firma instanceof FirmaDigital && $firma->vigente() ? $firma : null;
    }

    /**
     * Texto visible que las plantillas ponen en el recuadro del empleador cuando el PDF se firmará.
     *
     * @return array{titular: string, documento: string|null, fecha: string}|null
     */
    public function sello(Empresa $empresa): ?array
    {
        $firma = $this->vigente($empresa);

        return $firma === null ? null : [
            'titular' => $firma->titular,
            'documento' => $firma->documento,
            'fecha' => CarbonImmutable::now('America/Lima')->format('d/m/Y H:i'),
        ];
    }

    /** Devuelve el PDF firmado con el certificado de la empresa, o el mismo PDF si no tiene uno vigente. */
    public function firmar(string $pdf, Empresa $empresa, string $motivo): string
    {
        $firma = $this->vigente($empresa);
        if ($firma === null) {
            return $pdf;
        }

        if (! openssl_pkcs12_read((string) base64_decode($firma->certificado, true), $partes, $firma->clave)) {
            throw new RuntimeException('No se pudo leer el certificado digital guardado de la empresa. Vuelve a registrarlo.');
        }

        $doc = PDFDoc::from_string($pdf);
        if ($doc === false || $doc->set_signature_certificate($partes, $firma->clave) !== true) {
            throw new RuntimeException('No se pudo preparar la firma digital del documento.');
        }
        $doc->set_metadata_props($firma->titular, $motivo, 'Perú', '');

        $firmado = $doc->to_pdf_file_s();
        if (! is_string($firmado) || ! str_contains($firmado, '/ByteRange')) {
            throw new RuntimeException('No se pudo firmar digitalmente el documento.');
        }

        return $firmado;
    }
}
