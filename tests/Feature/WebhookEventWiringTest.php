<?php

declare(strict_types=1);

use App\Enums\WebhookEvent;
use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Models\WebhookEndpoint;
use App\Services\OrderCreationService;
use App\Services\PurchaseOrderProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Orders\Models\PurchaseOrderProduct;
use Modules\Orders\Models\PurchaseOrderStatus;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: string}
 */
function webhookWiringWarehouse(string $suffix): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "webhook-wiring-{$suffix}",
        'name' => "Webhook Wiring {$suffix}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Webhook Wiring Warehouse {$suffix}",
        'currency' => 'EUR',
    ]);

    $url = "https://example.test/webhook-wiring-{$suffix}";

    return [$warehouse, $url];
}

/**
 * @param  list<WebhookEvent>  $events
 */
function webhookWiringEndpoint(Warehouse $warehouse, string $url, array $events): WebhookEndpoint
{
    return WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'url' => $url,
        'is_active' => true,
        'subscribed_events' => array_map(fn (WebhookEvent $event): string => $event->value, $events),
    ]);
}

/**
 * @return list<string>
 */
function webhookWiringEventsSentTo(string $url): array
{
    return Http::recorded(fn (Request $request): bool => $request->url() === $url)
        ->map(fn (array $pair): mixed => json_decode($pair[0]->body(), true)['event'])
        ->values()
        ->all();
}

function webhookWiringProduct(Warehouse $warehouse, int $onStockQuantity): Product
{
    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-'.uniqid(),
        'product_code' => 'PROD-'.uniqid(),
        'barcode' => (string) random_int(1_000_000_000_000, 9_999_999_999_999),
        'name' => 'Webhook Wiring Product '.uniqid(),
        'description' => 'Webhook wiring description',
        'price' => 10.00,
    ]);

    StockProduct::query()->create([
        'product_id' => $product->id,
        'on_stock_quantity' => $onStockQuantity,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => $onStockQuantity,
    ]);

    return $product;
}

/**
 * @param  array<int, array{product_id: int, quantity: int}>  $items
 * @return array<string, mixed>
 */
function webhookWiringOrderPayload(OrderStatus $status, array $items): array
{
    return [
        'order_status_id' => $status->id,
        'client' => ['email' => 'webhook-wiring-customer@example.com'],
        'delivery_name' => 'Jane Doe',
        'delivery_address' => 'Main Street 1',
        'delivery_zipcode' => '1234AB',
        'delivery_city' => 'Amsterdam',
        'delivery_country' => 'NL',
        'items' => $items,
    ];
}

it('dispatches an order.created webhook when an order is created via OrderCreationService', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('order-created-api');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::OrderCreated]);

    $status = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'New',
        'color' => '#3B82F6',
        'concepted' => true,
    ]);

    $product = webhookWiringProduct($warehouse, onStockQuantity: 10);

    $order = app(OrderCreationService::class)->create($warehouse, webhookWiringOrderPayload($status, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]));

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::OrderCreated->value]);

    $delivered = Http::recorded(fn (Request $request): bool => $request->url() === $url)->first()[0];
    $body = json_decode($delivered->body(), true);

    expect($body['data']['id'])->toBe($order->id);
});

it('dispatches an order.created webhook when an order is created directly, not just through OrderCreationService', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('order-created-direct');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::OrderCreated]);

    // Simulates the Filament admin UI's CreateOrder page, which creates the
    // order directly via the model rather than through OrderCreationService -
    // proving OrderObserver::created() covers every creation path.
    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::OrderCreated->value]);
});

it('dispatches order.status_changed on every transition and order.cancelled only when newly cancelled', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('order-status-changed');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::OrderStatusChanged, WebhookEvent::OrderCancelled]);

    $confirmed = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed',
        'color' => '#10B981',
    ]);

    $cancelled = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Cancelled',
        'color' => '#EF4444',
        'cancelled' => true,
    ]);

    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    // Reset the fake's recorded requests so assertions below only reflect
    // the status transitions, not the order.created delivery above.
    Http::fake(['*' => Http::response('ok', 200)]);

    $order->update(['order_statuses_id' => $confirmed->id]);

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::OrderStatusChanged->value]);

    $order->update(['order_statuses_id' => $cancelled->id]);

    expect(webhookWiringEventsSentTo($url))->toBe([
        WebhookEvent::OrderStatusChanged->value,
        WebhookEvent::OrderStatusChanged->value,
        WebhookEvent::OrderCancelled->value,
    ]);
});

it('dispatches order.delivered only when a status newly marks the order delivered', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('order-delivered');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::OrderDelivered]);

    $confirmed = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed',
        'color' => '#10B981',
    ]);

    $delivered = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Delivered',
        'color' => '#3B82F6',
        'delivered' => true,
    ]);

    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    $order->update(['order_statuses_id' => $confirmed->id]);
    expect(webhookWiringEventsSentTo($url))->toBe([]);

    $order->update(['order_statuses_id' => $delivered->id]);
    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::OrderDelivered->value]);
});

it('dispatches purchase_order.status_changed on every transition and purchase_order.processed once Processed is reached', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('po-status-changed');
    webhookWiringEndpoint($warehouse, $url, [
        WebhookEvent::PurchaseOrderStatusChanged,
        WebhookEvent::PurchaseOrderProcessed,
    ]);

    $product = webhookWiringProduct($warehouse, onStockQuantity: 0);

    $purchaseOrder = PurchaseOrder::query()->create([
        'warehouse_id' => $warehouse->id,
        'expected_delivery_date' => now()->addDay()->toDateString(),
    ])->fresh();

    $service = app(PurchaseOrderProcessingService::class);

    $service->markPurchased($purchaseOrder, now()->addWeek());
    $purchaseOrder->refresh();

    $service->markReceived($purchaseOrder);
    $purchaseOrder->refresh();

    PurchaseOrderProduct::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'barcode' => $product->barcode,
        'reference_code' => $product->reference_code,
        'product_title' => $product->name,
        'scanned' => true,
    ]);

    $service->evaluateScanCompletion($purchaseOrder);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Scanned);

    $service->markProcessed($purchaseOrder);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Processed);

    expect(webhookWiringEventsSentTo($url))->toBe([
        WebhookEvent::PurchaseOrderStatusChanged->value,
        WebhookEvent::PurchaseOrderStatusChanged->value,
        WebhookEvent::PurchaseOrderStatusChanged->value,
        WebhookEvent::PurchaseOrderStatusChanged->value,
        WebhookEvent::PurchaseOrderProcessed->value,
    ]);
});

it('dispatches backorder.created when a shortfall creates a backorder', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('backorder-created');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::BackorderCreated]);

    $newStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'New',
        'color' => '#3B82F6',
        'concepted' => true,
    ]);

    OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Backordered',
        'color' => '#F59E0B',
        'on_hold' => true,
    ]);

    $product = webhookWiringProduct($warehouse, onStockQuantity: 1);

    app(OrderCreationService::class)->create($warehouse, webhookWiringOrderPayload($newStatus, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]));

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::BackorderCreated->value]);
});

it('dispatches backorder.fulfilled when a restock fully fulfills a backorder, from inside the fulfillment transaction', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('backorder-fulfilled');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::BackorderFulfilled]);

    $confirmedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed',
        'color' => '#10B981',
    ]);

    $backorderedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Backordered',
        'color' => '#F59E0B',
        'on_hold' => true,
    ]);

    $product = webhookWiringProduct($warehouse, onStockQuantity: 0);

    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    $orderProduct = OrderProduct::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 5,
        'barcode' => $product->barcode,
        'reference_code' => $product->reference_code,
        'price' => 9.99,
    ]);

    $order->update(['order_statuses_id' => $backorderedStatus->id]);

    Backorder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
        'order_product_id' => $orderProduct->id,
        'product_id' => $product->id,
        'intended_order_statuses_id' => $confirmedStatus->id,
        'quantity_ordered' => 5,
        'quantity_fulfilled' => 0,
        'status' => BackorderStatus::Open,
    ]);

    // Reset the fake so only the restock's deliveries below are observed.
    Http::fake(['*' => Http::response('ok', 200)]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Scanned,
        'expected_delivery_date' => now()->addDay()->toDateString(),
    ]);

    for ($i = 0; $i < 5; $i++) {
        PurchaseOrderProduct::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'reference_code' => $product->reference_code,
            'product_title' => $product->name,
            'scanned' => true,
        ]);
    }

    app(PurchaseOrderProcessingService::class)->markProcessed($purchaseOrder->fresh());

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::BackorderFulfilled->value]);
});

it('dispatches backorder.cancelled when an order with open backorders is cancelled', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$warehouse, $url] = webhookWiringWarehouse('backorder-cancelled');
    webhookWiringEndpoint($warehouse, $url, [WebhookEvent::BackorderCancelled]);

    $backorderedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Backordered',
        'color' => '#F59E0B',
        'on_hold' => true,
    ]);

    $confirmedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed',
        'color' => '#10B981',
    ]);

    $cancelledStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Cancelled',
        'color' => '#EF4444',
        'cancelled' => true,
    ]);

    $product = webhookWiringProduct($warehouse, onStockQuantity: 0);

    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    $orderProduct = OrderProduct::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 5,
        'barcode' => $product->barcode,
        'reference_code' => $product->reference_code,
        'price' => 9.99,
    ]);

    $order->update(['order_statuses_id' => $backorderedStatus->id]);

    Backorder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
        'order_product_id' => $orderProduct->id,
        'product_id' => $product->id,
        'intended_order_statuses_id' => $confirmedStatus->id,
        'quantity_ordered' => 5,
        'quantity_fulfilled' => 0,
        'status' => BackorderStatus::Open,
    ]);

    // Reset the fake so only the cancellation's deliveries below are observed.
    Http::fake(['*' => Http::response('ok', 200)]);

    $order->update(['order_statuses_id' => $cancelledStatus->id]);

    expect(webhookWiringEventsSentTo($url))->toBe([WebhookEvent::BackorderCancelled->value]);
});
