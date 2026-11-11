# Firma digital de documentos laborales

Cada empresa puede registrar el **certificado digital de su representante legal** (`.pfx` / `.p12`, emitido por
RENIEC u otra entidad acreditada). Con él, sus documentos laborales en PDF salen **firmados digitalmente** (PAdES
básico, `adbe.pkcs7.detached`): Adobe Reader y otros lectores muestran quién firmó y avisan si el archivo se modificó
después. Base: D. Leg. 1310, que permite sustituir la firma a mano del empleador por la firma digital (Ley 27269) ⚠
confirmar con el contador o abogado.

## Registrar el certificado

**Empresas → editar la empresa → Firma digital** (solo administrador):

1. Elegir el archivo `.pfx` o `.p12` (hasta 100 KB) y escribir su clave.
2. Se valida al momento: clave correcta, que traiga la clave privada, que no esté vencido ni por empezar.
3. Se muestra titular, documento, emisor, vigencia y huella SHA-256. Aviso desde 30 días antes de vencer.
4. **Reemplazar** (certificado renovado) o **Quitar firma**.

Seguridad: el certificado y su clave se guardan **cifrados** con `APP_KEY` (casts `encrypted`), nunca vuelven a la
pantalla y no entran a la auditoría (registrar, reemplazar y quitar sí quedan registrados, sin secretos). La clave se
guarda porque se firma sin intervención (p. ej. el envío masivo de boletas). Si se pierde `APP_KEY`, hay que volver a
registrar el certificado.

## Qué se firma

| Documento                        | Cuándo se firma            | Motivo en la firma                 |
| -------------------------------- | -------------------------- | ---------------------------------- |
| Boleta de pago (una, lote y zip) | Periodo **cerrado**        | Boleta de pago                     |
| Boleta enviada por correo        | Periodo cerrado            | Boleta de pago                     |
| Hoja de liquidación              | Liquidación **cerrada**    | Liquidación de beneficios sociales |
| Carta de liberación de CTS       | Liquidación cerrada        | Carta de liberación de CTS         |
| Certificado de trabajo           | Siempre                    | Certificado de trabajo             |
| Constancia de depósito de CTS    | Liquidación de CTS cerrada | Constancia de depósito de CTS      |

- Los **borradores** no se firman. Sin certificado o con el certificado **vencido**, salen sin firma digital (con el
  espacio para firmar a mano), como antes.
- El recuadro del empleador muestra «Firmado digitalmente por … · DNI … · fecha» solo cuando el PDF va firmado. Si la
  firma fallara, no se entrega un PDF que diga estar firmado: se muestra el error.
- ⚠ **Boletas por correo**: sin certificado van protegidas con contraseña (DNI del trabajador). Con certificado van
  **firmadas y sin contraseña**, porque un PDF cifrado no admite la firma después de generado.
- Los reportes no se firman (son de uso interno).

## Técnica

- `App\Services\Empresas\FirmaDigitalPdf`: lee y valida el certificado, guarda (`FirmaDigital`, tabla
  `firmas_digitales`, una por empresa), da el sello visible (`sello()`) y firma el PDF ya generado por dompdf
  (`firmar()`) con [ddn/sapp](https://github.com/dealfonso/sapp) — LGPL-3.0, se usa como librería sin modificar.
- Plantillas: `resources/views/pdf/partials/firma-digital.blade.php` dentro del recuadro del empleador.
- Tests: `tests/Feature/Empresas/FirmaDigitalTest.php` con certificados **autofirmados generados en cada ejecución**
  (`tests/Concerns/ConCertificado.php`); verifican la firma con OpenSSL y que un byte cambiado la invalida.
- Fuera de alcance por ahora: sello de tiempo (TSA) y validación a largo plazo (LTV); la conformidad del trabajador
  irá con el portal del trabajador (v2.0).
