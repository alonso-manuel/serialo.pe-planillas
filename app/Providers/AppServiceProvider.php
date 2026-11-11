<?php

namespace App\Providers;

use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\FirmaDigital;
use App\Models\Liquidacion;
use App\Models\LiquidacionCts;
use App\Models\Novedad;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\RentaOtroEmpleador;
use App\Models\TasaAfp;
use App\Models\Trabajador;
use App\Models\TrabajadorHistorial;
use App\Models\User;
use App\Models\VacacionGoce;
use App\Services\Dni\ConsultaDni;
use App\Services\Dni\ConsultaDniDecolecta;
use App\Services\Dni\ConsultaDniEnCache;
use App\Services\Dni\ConsultaDniNinguna;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Licencia\LicenciaActual;
use App\Services\Ruc\ConsultaRuc;
use App\Services\Ruc\ConsultaRucCombinada;
use App\Services\Ruc\ConsultaRucDecolecta;
use App\Services\Ruc\ConsultaRucEnCache;
use App\Services\Ruc\ConsultaRucNinguna;
use App\Services\Ruc\ConsultaRucOpenRuc;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(EmpresaActiva::class);
        $this->app->scoped(LicenciaActual::class);

        // Proveedor de consulta RUC según config('services.ruc.driver'), con caché de 24 h.
        $this->app->bind(ConsultaRuc::class, function (Application $app): ConsultaRuc {
            $token = config('services.decolecta.token');
            $token = is_string($token) && $token !== '' ? $token : null;

            $proveedor = match (config('services.ruc.driver')) {
                'openruc' => $token === null
                    ? new ConsultaRucOpenRuc
                    : new ConsultaRucCombinada(new ConsultaRucOpenRuc, new ConsultaRucDecolecta($token)),
                'decolecta' => new ConsultaRucDecolecta($token),
                default => new ConsultaRucNinguna,
            };

            return new ConsultaRucEnCache($proveedor, $app->make(Repository::class));
        });

        // Proveedor de consulta DNI (RENIEC), con caché corta (dato personal).
        $this->app->bind(ConsultaDni::class, function (Application $app): ConsultaDni {
            $token = config('services.decolecta.token');

            $proveedor = config('services.dni.driver') === 'decolecta' && is_string($token) && $token !== ''
                ? new ConsultaDniDecolecta($token)
                : new ConsultaDniNinguna;

            return new ConsultaDniEnCache($proveedor, $app->make(Repository::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Envío de boletas: respeta el límite de correos del hosting (config/planillas.php).
        RateLimiter::for('correos-boletas', fn () => Limit::perMinute(max(1, (int) config('planillas.correos_por_minuto'))));

        // Nombres cortos y estables para auditorias.auditable_type (no dependen del namespace).
        Relation::morphMap([
            'empresa' => Empresa::class,
            'establecimiento' => Establecimiento::class,
            'usuario' => User::class,
            'trabajador' => Trabajador::class,
            'trabajador_historial' => TrabajadorHistorial::class,
            'novedad' => Novedad::class,
            'planilla_linea' => PlanillaLinea::class,
            'parametro' => Parametro::class,
            'tasa_afp' => TasaAfp::class,
            'concepto' => Concepto::class,
            'periodo' => Periodo::class,
            'liquidacion_cts' => LiquidacionCts::class,
            'vacacion' => VacacionGoce::class,
            'liquidacion' => Liquidacion::class,
            'renta_otro_empleador' => RentaOtroEmpleador::class,
            'firma_digital' => FirmaDigital::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
