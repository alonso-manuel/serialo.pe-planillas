<?php

namespace App\Services\Licencia;

use App\Domain\Licencia\ClaveLicencia;
use App\Domain\Licencia\ClaveLicenciaInvalida;
use App\Domain\Licencia\EstadoLicencia;
use App\Domain\Licencia\Licencia;
use App\Domain\Licencia\Recurso;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Instalador\Instalacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Lee la clave guardada, la evalúa (firma, dominio, prueba) y aplica los límites del plan.
 * Los límites cuentan solo lo activo: empresas y usuarios activos, trabajadores sin cese vigente.
 */
class LicenciaActual
{
    public function __construct(private readonly Instalacion $instalacion) {}

    public function omitida(): bool
    {
        return (bool) config('licencia.omitir');
    }

    /** @param string|null $host dominio de la petición; null (cola, comandos) no revisa el dominio */
    public function estado(?string $host = null): EstadoLicencia
    {
        if ($this->omitida()) {
            return EstadoLicencia::omitida();
        }

        // Sin memoria entre llamadas: un proceso largo (la cola) ve al instante una clave nueva.
        return EstadoLicencia::evaluar(
            clave: $this->clave(),
            clavePublica: (string) config('licencia.clave_publica'),
            host: $host,
            instaladoEn: $this->instalacion->instaladoEn()?->toDateTimeImmutable(),
            hoy: CarbonImmutable::now()->toDateTimeImmutable(),
            prueba: $this->configPrueba(),
        );
    }

    public function clave(): ?string
    {
        $archivo = (string) config('licencia.archivo');
        $clave = is_file($archivo) ? trim((string) file_get_contents($archivo)) : '';

        return $clave === '' ? null : $clave;
    }

    /**
     * Verifica y guarda una clave nueva (pantalla de licencia o instalador). Queda en la auditoría.
     *
     * @throws ClaveLicenciaInvalida
     */
    public function guardar(string $clave, string $host): Licencia
    {
        $licencia = ClaveLicencia::leer($clave, (string) config('licencia.clave_publica'));

        if (! $licencia->permiteDominio($host)) {
            throw new ClaveLicenciaInvalida(
                "Esta clave es para {$licencia->dominio} y el sistema está en ".Licencia::normalizarHost($host).'. Pide a Serialo.pe una clave para este dominio.',
            );
        }

        $anterior = $this->licenciaGuardada();
        $archivo = (string) config('licencia.archivo');
        if (! is_dir(dirname($archivo))) {
            mkdir(dirname($archivo), 0755, true);
        }
        file_put_contents($archivo, (string) preg_replace('/\s+/', '', $clave).PHP_EOL, LOCK_EX);

        $this->auditar($anterior, $licencia);

        return $licencia;
    }

    /** @return array<string, int> recurso => cantidad activa */
    public function uso(): array
    {
        $uso = [];
        foreach (Recurso::cases() as $recurso) {
            $uso[$recurso->value] = $this->cantidad($recurso);
        }

        return $uso;
    }

    /**
     * Antes de crear o reactivar: lanza un aviso si se pasaría del límite del plan.
     *
     * @throws LimiteLicenciaExcedido
     */
    public function asegurarCupo(Recurso $recurso, int $nuevos = 1): void
    {
        $estado = $this->estado();
        $limite = $estado->limite($recurso);

        if ($limite === null || $nuevos <= 0) {
            return;
        }

        $actuales = $this->cantidad($recurso);
        if ($actuales + $nuevos <= $limite) {
            return;
        }

        $plan = $estado->licencia !== null ? "Tu licencia {$estado->licencia->plan}" : 'La prueba';
        $libres = max(0, $limite - $actuales);
        $detalle = $nuevos > 1 && $libres > 0 ? " Ahora puedes agregar {$libres} más." : '';
        $accion = $recurso === Recurso::Trabajadores ? 'registra el cese de alguno' : 'desactiva alguno';

        throw new LimiteLicenciaExcedido(
            "{$plan} permite hasta {$limite} {$recurso->plural($limite)} y ya tienes {$actuales}.{$detalle} "
            ."Para continuar, {$accion} o amplía tu plan con Serialo.pe.",
        );
    }

    private function cantidad(Recurso $recurso): int
    {
        return match ($recurso) {
            Recurso::Empresas => Empresa::query()->where('activo', true)->count(),
            Recurso::Usuarios => User::query()->where('activo', true)->count(),
            Recurso::Trabajadores => Trabajador::query()
                ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>', CarbonImmutable::today()))
                ->count(),
        };
    }

    private function licenciaGuardada(): ?Licencia
    {
        $clave = $this->clave();

        try {
            return $clave === null ? null : ClaveLicencia::leer($clave, (string) config('licencia.clave_publica'));
        } catch (ClaveLicenciaInvalida) {
            return null;
        }
    }

    private function auditar(?Licencia $anterior, Licencia $nueva): void
    {
        if (Auditoria::estaPausada()) {
            return;
        }

        $resumen = fn (?Licencia $l) => $l === null ? null : "{$l->plan} · {$l->cliente} · {$l->dominio} · actualizaciones hasta {$l->actualizacionesHasta}";

        Auditoria::create([
            'user_id' => Auth::guard('web')->id(),
            'empresa_id' => null,
            'auditable_type' => 'licencia',
            'auditable_id' => 0,
            'descripcion' => mb_substr("Licencia {$nueva->plan} de {$nueva->cliente}", 0, 255),
            'accion' => $anterior === null ? 'creado' : 'actualizado',
            'cambios' => ['licencia' => [$resumen($anterior), $resumen($nueva)]],
            'ip' => app()->bound('request') ? request()->ip() : null,
        ]);
    }

    /** @return array{dias: int, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null} */
    private function configPrueba(): array
    {
        $entero = fn (mixed $v) => $v === null ? null : (int) $v;
        /** @var array<string, mixed> $prueba */
        $prueba = (array) config('licencia.prueba');

        return [
            'dias' => (int) ($prueba['dias'] ?? 15),
            'max_empresas' => $entero($prueba['max_empresas'] ?? null),
            'max_usuarios' => $entero($prueba['max_usuarios'] ?? null),
            'max_trabajadores' => $entero($prueba['max_trabajadores'] ?? null),
        ];
    }
}
