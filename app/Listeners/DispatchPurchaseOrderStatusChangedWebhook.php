<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Warehouse;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Orders\Events\PurchaseOrderStatusChanged;
use Modules\Orders\Models\PurchaseOrderStatus;

class DispatchPurchaseOrderStatusChangedWebhook implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private readonly WebhookDispatcher $webhookDispatcher) {}

    public function handle(PurchaseOrderStatusChanged $event): void
    {
        $purchaseOrder = $event->purchaseOrder->fresh();

        if (! $purchaseOrder) {
            return;
        }

        $warehouse = $purchaseOrder->warehouse;

        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $payload = (new PurchaseOrderResource($purchaseOrder))->resolve();

        $this->webhookDispatcher->dispatch($warehouse, WebhookEvent::PurchaseOrderStatusChanged, $payload);

        if ($event->newStatus === PurchaseOrderStatus::Processed) {
            $this->webhookDispatcher->dispatch($warehouse, WebhookEvent::PurchaseOrderProcessed, $payload);
        }
    }
}
