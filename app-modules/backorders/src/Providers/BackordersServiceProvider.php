<?php

declare(strict_types=1);

namespace Modules\Backorders\Providers;

use Illuminate\Support\ServiceProvider;

class BackordersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // DispatchBackorderCreatedWebhook, DispatchBackorderFulfilledWebhook,
        // and DispatchBackorderCancelledWebhook are intentionally not wired
        // up here with Event::listen() - see the comment in
        // OrdersServiceProvider::boot() for why: app/Listeners is
        // auto-discovered by Laravel by default, and registering these
        // listeners both ways would send every webhook twice.
    }
}
