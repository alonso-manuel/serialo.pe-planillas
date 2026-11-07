<?php

namespace App\Providers;

use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\Novedad;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\TasaAfp;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Ruc\ConsultaRuc;
use App\Services\Ruc\ConsultaRucDecolecta;
use App\Services\Ruc\ConsultaRucEnCache;
use App\Services\Ruc\ConsultaRucNinguna;
use App\Services\Ruc\ConsultaRucOpenRuc;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

        // Proveedor de consulta RUC según config('services.ruc.driver'), con caché de 24 h.
        $this->app->bind(ConsultaRuc::class, function (Application $app): ConsultaRuc {
            $proveedor = match (config('services.ruc.driver')) {
                'openruc' => new ConsultaRucOpenRuc,
                'decolecta' => new ConsultaRucDecolecta(config('services.ruc.decolecta_token')),
                default => new ConsultaRucNinguna,
            };

            return new ConsultaRucEnCache($proveedor, $app->make(Repository::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Nombres cortos y estables para auditorias.auditable_type (no dependen del namespace).
        Relation::morphMap([
            'empresa' => Empresa::class,
            'establecimiento' => Establecimiento::class,
            'usuario' => User::class,
            'trabajador' => Trabajador::class,
            'novedad' => Novedad::class,
            'parametro' => Parametro::class,
            'tasa_afp' => TasaAfp::class,
            'concepto' => Concepto::class,
            'periodo' => Periodo::class,
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
