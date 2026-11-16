<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Services\PurchaseOrderProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * @return array{0: Warehouse, 1: OrderStatus, 2: OrderStatus, 3: OrderStatus}
 */
function createBackorderCancellationWarehouse(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'backorder-cancellation-'.uniqid(),
        'name' => 'Backorder Cancellation',
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Main Warehouse',
        'currency' => 'EUR',
    ]);

    $confirmedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed',
        'color' => '#10B981',
        'generate_picklist' => true,
        'reserve_stock' => true,
    ]);

    $backorderedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Backordered',
        'color' => '#F59E0B',
        'on_hold' => true,
    ]);

    $cancelledStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Cancelled',
        'color' => '#EF4444',
        'cancelled' => true,
    ]);

    return [$warehouse, $confirmedStatus, $backorderedStatus, $cancelledStatus];
}

/**
 * @return array{0: Product, 1: StockProduct}
 */
function createBackorderCancellationProduct(Warehouse $warehouse): array
{
    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-'.uniqid(),
        'product_code' => 'PROD-'.uniqid(),
        'barcode' => (string) random_int(1_000_000_000_000, 9_999_999_999_999),
        'name' => 'Restock Product '.uniqid(),
        'description' => 'Restock description',
    ]);

    $stockProduct = StockProduct::query()->create([
        'product_id' => $product->id,
        'on_stock_quantity' => 0,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => 0,
    ]);

    return [$product, $stockProduct];
}

function createCancellableBackorderedOrder(
    Warehouse $warehouse,
    Product $product,
    int $quantity,
    OrderStatus $backorderedStatus,
    OrderStatus $intendedStatus,
): Order {
    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'delivery_date' => '2026-12-01',
    ]);

    $orderProduct = OrderProduct::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => $quantity,
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
        'intended_order_statuses_id' => $intendedStatus->id,
        'quantity_ordered' => $quantity,
        'quantity_fulfilled' => 0,
        'status' => BackorderStatus::Open,
    ]);

    return $order;
}

function processCancellationRestockPurchaseOrder(Warehouse $warehouse, Product $product, int $quantity): PurchaseOrder
{
    $purchaseOrder = PurchaseOrder::query()->create([
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Scanned,
        'expected_delivery_date' => '2026-11-01',
    ]);

    for ($i = 0; $i < $quantity; $i++) {
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

    return $purchaseOrder;
}

it('cancels the open backorder when its order is cancelled', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus, $cancelledStatus] = createBackorderCancellationWarehouse();
    [$product] = createBackorderCancellationProduct($warehouse);

    $order = createCancellableBackorderedOrder($warehouse, $product, 5, $backorderedStatus, $confirmedStatus);

    $order->update(['order_statuses_id' => $cancelledStatus->id]);

    $backorder = Backorder::query()->where('order_id', $order->id)->sole();

    expect($backorder->status)->toBe(BackorderStatus::Cancelled);
    expect($backorder->resolved_at)->not->toBeNull();
});

it('does not let a cancelled order\'s zombie backorder compete for restocked product and does not crash purchase order processing', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus, $cancelledStatus] = createBackorderCancellationWarehouse();
    [$product] = createBackorderCancellationProduct($warehouse);

    $cancelledOrder = createCancellableBackorderedOrder($warehouse, $product, 5, $backorderedStatus, $confirmedStatus);
    $cancelledOrder->update(['order_statuses_id' => $cancelledStatus->id]);

    $liveOrder = createCancellableBackorderedOrder($warehouse, $product, 5, $backorderedStatus, $confirmedStatus);

    // Should not throw, and should fulfill the live order's backorder instead
    // of the cancelled order's.
    processCancellationRestockPurchaseOrder($warehouse, $product, 5);

    $liveBackorder = Backorder::query()->where('order_id', $liveOrder->id)->sole();
    $cancelledBackorder = Backorder::query()->where('order_id', $cancelledOrder->id)->sole();

    expect($liveBackorder->status)->toBe(BackorderStatus::Fulfilled);
    expect($liveBackorder->order->order_statuses_id)->toBe($confirmedStatus->id);

    expect($cancelledBackorder->status)->toBe(BackorderStatus::Cancelled);
    expect($cancelledBackorder->quantity_fulfilled)->toBe(0);
    expect($cancelledBackorder->order->order_statuses_id)->toBe($cancelledStatus->id);
});
