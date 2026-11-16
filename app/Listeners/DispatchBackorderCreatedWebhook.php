<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Http\Resources\BackorderResource;
use App\Models\Warehouse;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Backorders\Events\BackorderCreated;

class DispatchBackorderCreatedWebhook implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private readonly WebhookDispatcher $webhookDispatcher) {}

    public function handle(BackorderCreated $event): void
    {
        $backorder = $event->backorder->fresh();

        if (! $backorder) {
            return;
        }

        $warehouse = $backorder->warehouse;

        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $this->webhookDispatcher->dispatch(
            $warehouse,
            WebhookEvent::BackorderCreated,
            (new BackorderResource($backorder))->resolve(),
        );
    }
}
