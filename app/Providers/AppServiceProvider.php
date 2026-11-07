<?php

namespace App\Providers;

use App\Services\Empresas\EmpresaActiva;
use App\Services\Ruc\ConsultaRuc;
use App\Services\Ruc\ConsultaRucDecolecta;
use App\Services\Ruc\ConsultaRucEnCache;
use App\Services\Ruc\ConsultaRucNinguna;
use App\Services\Ruc\ConsultaRucOpenRuc;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
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
