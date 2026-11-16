<?php

declare(strict_types=1);

namespace Modules\Orders\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Orders\Models\PurchaseOrderStatus;

class PurchaseOrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public ?PurchaseOrderStatus $previousStatus,
        public PurchaseOrderStatus $newStatus,
        public ?int $causerId = null,
    ) {}
}
