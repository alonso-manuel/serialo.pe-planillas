<?php

namespace App\Services\Respaldo;

use App\Models\Central\Estudio;
use App\Support\ModoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Respaldos de la base de datos en uso: la de la instalación única, la de un estudio (si hay
 * un estudio activo en tenancy) o la central del VPS.
 *
 * - Se guardan en el disco `local` bajo `respaldos/`. En modo SaaS ese disco ya es la carpeta del
 *   estudio (`storage/estudio-<id>`), así que cada estudio solo ve y descarga los suyos.
 * - Copia fuera del servidor: si `planillas.respaldos.disco_externo` nombra un disco de
 *   `config/filesystems.php`, se copia ahí en `<prefijo>/<archivo>` (prefijo = estudio o dominio).
 * - Conserva los de los últimos `planillas.respaldos.dias` días; nunca borra el más reciente.
 */
class Respaldos
{
    public const CARPETA = 'respaldos';

    private const PATRON = '/^respaldo-\d{8}-\d{6}(-\d+)?\.sql\.gz$/';

    public function __construct(private readonly VolcadoBaseDatos $volcado) {}

    /** @return array{archivo: string, tamano: int, tablas: int, filas: int, externo: bool} */
    public function crear(): array
    {
        $disco = $this->disco();
        $disco->makeDirectory(self::CARPETA);

        $base = 'respaldo-'.CarbonImmutable::now('America/Lima')->format('Ymd-His');
        $archivo = $base.'.sql.gz';
        for ($n = 2; $disco->exists(self::CARPETA.'/'.$archivo); $n++) {
            $archivo = "{$base}-{$n}.sql.gz";
        }

        $ruta = $disco->path(self::CARPETA.'/'.$archivo);
        $resumen = $this->volcado->volcar(DB::connection(), $ruta);

        $externo = $this->copiarFuera($archivo, $ruta);
        $this->podar();

        return ['archivo' => $archivo, 'tamano' => (int) filesize($ruta), ...$resumen, 'externo' => $externo];
    }

    /** @return list<array{archivo: string, tamano: int, fecha: CarbonImmutable}> */
    public function listar(): array
    {
        $disco = $this->disco();
        $lista = [];

        foreach ($disco->files(self::CARPETA) as $ruta) {
            $archivo = basename($ruta);
            if (preg_match(self::PATRON, $archivo) === 1) {
                $lista[] = [
                    'archivo' => $archivo,
                    'tamano' => $disco->size($ruta),
                    'fecha' => CarbonImmutable::createFromTimestamp($disco->lastModified($ruta), 'America/Lima'),
                ];
            }
        }

        usort($lista, fn (array $a, array $b) => strcmp($b['archivo'], $a['archivo']));

        return $lista;
    }

    /** Ruta absoluta de un respaldo existente; null si el nombre no es válido o no existe. */
    public function ruta(string $archivo): ?string
    {
        if (preg_match(self::PATRON, $archivo) !== 1 || ! $this->disco()->exists(self::CARPETA.'/'.$archivo)) {
            return null;
        }

        return $this->disco()->path(self::CARPETA.'/'.$archivo);
    }

    /** Borra los respaldos más antiguos que los días configurados (local y fuera del servidor). */
    public function podar(): int
    {
        $limite = CarbonImmutable::now('America/Lima')->subDays(max(1, (int) config('planillas.respaldos.dias', 14)));
        $borrados = 0;

        foreach (array_slice($this->listar(), 1) as $r) { // el más reciente siempre se queda
            if ($r['fecha']->lt($limite)) {
                $this->disco()->delete(self::CARPETA.'/'.$r['archivo']);
                $borrados++;

                if (($externo = $this->discoExterno()) !== null) {
                    rescue(fn () => $externo->delete($this->prefijo().'/'.$r['archivo']), report: false);
                }
            }
        }

        return $borrados;
    }

    /** «850 B», «12.4 KB», «3.1 MB» (sin depender de la extensión intl, que falta en algunos hostings). */
    public static function tamano(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $valor = (float) $bytes;
        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($valor, 1, '.', '')).' '.$unidades[$i];
    }

    /** Carpeta en el disco externo: el estudio en SaaS, `central` para la base central, o el dominio en una instalación única. */
    public function prefijo(): string
    {
        if (tenancy()->initialized && tenant() instanceof Estudio) {
            return 'estudio-'.tenant()->getTenantKey();
        }

        if (ModoSistema::saas()) {
            return 'central';
        }

        return Str::slug(str_replace('.', '-', (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'instalacion')));
    }

    private function copiarFuera(string $archivo, string $ruta): bool
    {
        $externo = $this->discoExterno();
        if ($externo === null) {
            return false;
        }

        $flujo = fopen($ruta, 'rb');

        try {
            return $flujo !== false && $externo->writeStream($this->prefijo().'/'.$archivo, $flujo);
        } catch (Throwable $e) {
            // El respaldo local ya existe: un fallo del disco externo se registra, no detiene nada.
            Log::error('No se pudo copiar el respaldo fuera del servidor: '.$e->getMessage(), ['archivo' => $archivo]);

            return false;
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk('local');
    }

    private function discoExterno(): ?Filesystem
    {
        $nombre = config('planillas.respaldos.disco_externo');

        return is_string($nombre) && $nombre !== '' ? Storage::disk($nombre) : null;
    }
}
