<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use App\Listeners\ProcessOrderStatusTransition;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Orders\Events\OrderStatusChanged;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Orders\Observers\OrderObserver;
use Modules\Orders\Observers\OrderProductObserver;
use Modules\Orders\Observers\PurchaseOrderObserver;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'orders');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Event::listen(OrderStatusChanged::class, ProcessOrderStatusTransition::class);

        // DispatchOrderCreatedWebhook, DispatchOrderStatusChangedWebhook, and
        // DispatchPurchaseOrderStatusChangedWebhook are deliberately *not*
        // registered here. app/Listeners is auto-discovered by Laravel (see
        // bootstrap/app.php -> Application::configure()'s default
        // ->withEvents(), which is always applied) via reflection on each
        // listener's handle() method - they get attached automatically. The
        // explicit Event::listen() above for ProcessOrderStatusTransition is
        // actually redundant with that same auto-discovery (verified via
        // Event::getRawListeners()): it only goes unnoticed because that
        // listener's side effects are idempotent. Webhook dispatch is not
        // idempotent, so registering these listeners both ways would send
        // every webhook twice.
        Order::observe(OrderObserver::class);
        OrderProduct::observe(OrderProductObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
    }
}
