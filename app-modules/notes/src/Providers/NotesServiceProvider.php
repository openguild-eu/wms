<?php

declare(strict_types=1);

namespace Modules\Notes\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Notes\Models\Note;
use Modules\Notes\Observers\NoteObserver;

class NotesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Note::observe(NoteObserver::class);
    }
}
