<?php

declare(strict_types=1);

namespace Modules\Backorders\Providers;

use Illuminate\Support\ServiceProvider;

class BackordersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
