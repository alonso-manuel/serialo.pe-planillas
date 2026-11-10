<?php

namespace App\Console\Commands;

use App\Models\Central\Estudio;
use App\Services\Respaldo\Respaldos;
use App\Support\ModoSistema;
use Illuminate\Console\Command;
use Throwable;

/**
 * Respaldo de la base de datos (programado cada día, ver routes/console.php).
 * Instalación única: su base. Modo SaaS: la base central y la de cada estudio, cada una en su carpeta.
 */
class CrearRespaldos extends Command
{
    protected $signature = 'respaldos:crear {--estudio= : Solo este estudio (modo SaaS)}';

    protected $description = 'Respalda la base de datos (en SaaS: la central y la de cada estudio)';

    public function handle(Respaldos $respaldos): int
    {
        if (! ModoSistema::saas()) {
            return $this->respaldar('Base de datos', fn () => $respaldos->crear()) ? self::SUCCESS : self::FAILURE;
        }

        $soloEstudio = $this->option('estudio');
        $bien = true;

        if ($soloEstudio === null) {
            $bien = $this->respaldar('Base central', fn () => $respaldos->crear());
        }

        $estudios = [];
        $consulta = Estudio::query()->orderBy('id');
        if (is_string($soloEstudio)) {
            $consulta->whereKey($soloEstudio);
        }
        foreach ($consulta->get() as $estudio) {
            if ($estudio instanceof Estudio) {
                $estudios[] = $estudio;
            }
        }

        if ($soloEstudio !== null && $estudios === []) {
            $this->error("No existe el estudio «{$soloEstudio}».");

            return self::FAILURE;
        }

        foreach ($estudios as $estudio) {
            $bien = $this->respaldar("Estudio {$estudio->id}", fn () => $estudio->run(fn () => $respaldos->crear())) && $bien;
        }

        return $bien ? self::SUCCESS : self::FAILURE;
    }

    /** @param  callable(): array{archivo: string, tamano: int, tablas: int, filas: int, externo: bool}  $crear */
    private function respaldar(string $que, callable $crear): bool
    {
        try {
            $r = $crear();
        } catch (Throwable $e) {
            report($e);
            $this->error("{$que}: falló — {$e->getMessage()}");

            return false;
        }

        $this->line(sprintf(
            '%s: %s (%s, %d tablas, %d filas)%s',
            $que, $r['archivo'], Respaldos::tamano($r['tamano']), $r['tablas'], $r['filas'], $r['externo'] ? ' · copiado fuera del servidor' : '',
        ));

        return true;
    }
}
