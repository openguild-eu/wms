<?php

declare(strict_types=1);

namespace Modules\Backorders\Services;

use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Events\BackorderCancelled;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;

class BackorderCancellationService
{
    /**
     * Cancel every still-outstanding backorder for an order that has just
     * been cancelled. Without this, an open/partially-fulfilled backorder
     * keeps competing for stock on future restocks, and - if it ever reaches
     * "fully fulfilled" - BackorderFulfillmentService::releaseOrderIfFullyFulfilled()
     * would try to move the (now final-state) cancelled order back to its
     * intended status, which OrderObserver rejects with an uncaught
     * OrderStatusLockedException.
     */
    public function cancelForOrder(Order $order): void
    {
        $causerId = auth()->id();
        $causerId = is_int($causerId) ? $causerId : null;

        Backorder::query()
            ->where('order_id', $order->getKey())
            ->whereIn('status', [BackorderStatus::Open, BackorderStatus::PartiallyFulfilled])
            ->get()
            ->each(function (Backorder $backorder) use ($causerId): void {
                $backorder->update([
                    'status' => BackorderStatus::Cancelled,
                    'resolved_at' => now(),
                ]);

                BackorderCancelled::dispatch($backorder->withoutRelations(), $causerId);
            });
    }
}
