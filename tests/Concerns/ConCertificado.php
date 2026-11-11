<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Certificados digitales de prueba (autofirmados, datos ficticios) y verificación de firmas PAdES.
 * Nunca se usan certificados reales en los tests (Ley 29733).
 */
trait ConCertificado
{
    /** .p12 autofirmado válido por $dias días desde ahora. */
    protected function certificadoP12(string $clave = 'Clave-Cert-2026', int $dias = 365, string $titular = 'REPRESENTANTE FICTICIO PRUEBA'): string
    {
        // En Windows, OpenSSL de PHP necesita un archivo de configuración: se usa uno mínimo.
        $cnf = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents((string) $cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        $cfg = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        $llave = openssl_pkey_new($cfg);
        $csr = openssl_csr_new(['CN' => $titular, 'serialNumber' => 'DNI 00000000', 'O' => 'Empresa Ficticia S.A.C.', 'C' => 'PE'], $llave, $cfg);
        $cert = openssl_csr_sign($csr, null, $llave, $dias, $cfg);
        openssl_pkcs12_export($cert, $p12, $llave, $clave);
        @unlink((string) $cnf);

        return $p12;
    }

    protected function archivoCertificado(string $p12, string $nombre = 'firma.pfx'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'pfx');
        file_put_contents((string) $ruta, $p12);

        return new UploadedFile((string) $ruta, $nombre, 'application/x-pkcs12', null, true);
    }

    /** ¿El PDF lleva una firma digital válida sobre todo su contenido? */
    protected function firmaValida(string $pdf): bool
    {
        if (preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m) !== 1) {
            return false;
        }
        [, $a, $b, $c, $d] = array_map('intval', $m);
        if ($c + $d !== strlen($pdf)) {
            return false; // la firma debe cubrir hasta el final del archivo
        }

        $datos = tempnam(sys_get_temp_dir(), 'dat');
        $firma = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents((string) $datos, substr($pdf, $a, $b).substr($pdf, $c, $d));
        file_put_contents((string) $firma, rtrim((string) hex2bin(substr($pdf, $a + $b + 1, $c - ($a + $b) - 2)), "\0"));

        $valida = openssl_cms_verify((string) $datos, OPENSSL_CMS_NOVERIFY | OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED, null, [], null, null, null, (string) $firma, OPENSSL_ENCODING_DER);
        while (openssl_error_string() !== false) {
            // limpia la cola de errores de OpenSSL (carga del almacén por defecto)
        }
        @unlink((string) $datos);
        @unlink((string) $firma);

        return $valida === true;
    }
}
