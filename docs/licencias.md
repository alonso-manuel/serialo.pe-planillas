# Licencias

Cada instalación necesita una **clave de licencia** emitida por Serialo.pe. La clave dice para quién es, qué plan tiene,
sus límites y para qué dominio vale, y va firmada: el sistema puede verificarla pero no fabricarla.

## Formato

`SERIALO-<datos>.<firma>`, ambos en base64url:

- `datos`: JSON `{cliente, plan, max_empresas, max_usuarios, max_trabajadores, dominio, emitida, actualizaciones_hasta}`.
  Los límites `null` son ilimitados; las fechas en `AAAA-MM-DD`.
- `firma`: Ed25519 (`sodium_crypto_sign_detached`) sobre el JSON.

La **clave pública** está en `config/licencia.php`. La **privada** vive solo en el generador de licencias de Serialo.pe
(`C:\Users\Alonso\serialo-licencias\`, fuera de este repositorio). Código: `app/Domain/Licencia` (puro) y
`app/Services/Licencia/LicenciaActual.php`.

## Estados

| Estado          | Cuándo                                                        | Efecto                                                    |
| --------------- | ------------------------------------------------------------- | --------------------------------------------------------- |
| Activa          | Clave válida para este dominio                                | Funciona normal, con los límites del plan                 |
| Prueba          | Sin clave, durante 15 días desde la instalación               | Funciona normal, con los límites de prueba (plan Empresa) |
| Sin licencia    | Sin clave y terminó la prueba (o no hay marca de instalación) | Solo lectura                                              |
| Clave no válida | Firma incorrecta, datos alterados o incompletos               | Solo lectura                                              |
| Otro dominio    | La clave es para otro dominio                                 | Solo lectura                                              |

- El dominio vale exacto y con o sin `www.`; se ignoran mayúsculas y puerto.
- **Al vencer el plan anual el sistema sigue funcionando completo**: `actualizaciones_hasta` solo controla si el
  actualizador puede descargar versiones nuevas.
- La prueba y sus límites se configuran en `config/licencia.php` (`prueba`), no en el código.
- En `local` y `testing` la verificación se omite (`LICENCIA_OMITIR`), igual que el instalador.

## Solo lectura

Middleware `ModoSoloLectura`: bloquea POST, PUT, PATCH y DELETE de usuarios con sesión y muestra el motivo
(aviso emergente o 423 en JSON). Se puede consultar y descargar todo (boletas, PLAME, reportes). Siguen funcionando
el ingreso, la salida y **Administración → Licencia** para registrar una clave. Nunca se borra nada.
Una franja arriba de cada página avisa el modo solo lectura o los días de prueba que quedan.

## Límites del plan

Cuentan **solo lo activo**: empresas y usuarios activos, y trabajadores sin cese vigente (un cese futuro todavía cuenta).
Desactivar o cesar libera cupo. Se revisan al:

- crear o reactivar una empresa (`GestionEmpresas`) o un usuario (`GestionUsuarios`);
- registrar un trabajador, revertir un cese o confirmar una carga masiva (antes de consumir la vista previa: si no
  alcanza, se puede corregir el archivo o liberar cupo y volver a confirmar).

Si se supera, se muestra un aviso (`LimiteLicenciaExcedido`) y no se guarda nada. Si ya se estaba por encima
(p. ej. al bajar de plan), lo existente se sigue usando; solo se impide agregar más.

## Emitir una licencia (Serialo.pe)

En `C:\Users\Alonso\serialo-licencias\` (ver su `LEEME.md`):

```bash
php emitir.php --cliente="Estudio Contable Ejemplo S.A.C." --plan=estudio --dominio=planillas.ejemplo.pe
```

Planes: `empresa` (1 empresa, 2 usuarios, 50 trabajadores), `estudio` (40 empresas, 3 usuarios), `estudio-pro`
(empresas ilimitadas, 10 usuarios) o `personalizado` con `--empresas`, `--usuarios`, `--trabajadores`.
Renovación o cambio de dominio = emitir una clave nueva; el cliente la pega en **Administración → Licencia**.

## Límite de la protección

El código PHP se entrega abierto: alguien con conocimientos podría quitar la verificación. La licencia frena el uso
casual, deja claro qué se compró y respalda el soporte y las actualizaciones; no es un candado absoluto.
