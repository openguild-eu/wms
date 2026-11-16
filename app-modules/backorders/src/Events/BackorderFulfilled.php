<?php

declare(strict_types=1);

namespace Modules\Backorders\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Backorders\Models\Backorder;

class BackorderFulfilled implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Backorder $backorder,
        public ?int $causerId = null,
    ) {}
}
