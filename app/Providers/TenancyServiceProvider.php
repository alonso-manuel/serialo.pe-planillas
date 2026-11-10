<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

/**
 * Multitenant de stancl/tenancy (modo SaaS). En modo «unica» estos eventos nunca se disparan
 * porque no se crean estudios ni se inicializa la tenencia.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /** @return array<class-string, list<mixed>> */
    public function events(): array
    {
        return [
            // Al crear un estudio: su base, sus tablas y los catálogos legales (en el mismo proceso).
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(fn (Events\TenantCreated $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(fn (Events\TenantDeleted $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }
                Event::listen($event, $listener);
            }
        }
    }
}
