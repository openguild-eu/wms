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
use Modules\Picklists\Models\Picklist;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: OrderStatus, 2: OrderStatus}
 */
function createBackorderFulfillmentWarehouse(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'backorder-fulfillment-'.uniqid(),
        'name' => 'Backorder Fulfillment',
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

    return [$warehouse, $confirmedStatus, $backorderedStatus];
}

/**
 * @return array{0: Product, 1: StockProduct}
 */
function createBackorderFulfillmentProduct(Warehouse $warehouse, int $onStockQuantity = 0): array
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
        'on_stock_quantity' => $onStockQuantity,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => $onStockQuantity,
    ]);

    return [$product, $stockProduct];
}

function createBackorderedOrder(
    Warehouse $warehouse,
    Product $product,
    int $quantity,
    OrderStatus $backorderedStatus,
    OrderStatus $intendedStatus,
    string $backorderedAt,
): Backorder {
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

    return Backorder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
        'order_product_id' => $orderProduct->id,
        'product_id' => $product->id,
        'intended_order_statuses_id' => $intendedStatus->id,
        'quantity_ordered' => $quantity,
        'quantity_fulfilled' => 0,
        'status' => BackorderStatus::Open,
        'created_at' => $backorderedAt,
        'updated_at' => $backorderedAt,
    ]);
}

function processRestockPurchaseOrder(Warehouse $warehouse, Product $product, int $quantity): PurchaseOrder
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

it('fully fulfills and releases a single backorder when enough stock arrives', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus] = createBackorderFulfillmentWarehouse();
    [$product, $stockProduct] = createBackorderFulfillmentProduct($warehouse);

    $backorder = createBackorderedOrder($warehouse, $product, 5, $backorderedStatus, $confirmedStatus, '2026-11-01 10:00:00');

    processRestockPurchaseOrder($warehouse, $product, 5);

    $backorder->refresh();

    expect($backorder->status)->toBe(BackorderStatus::Fulfilled);
    expect($backorder->resolved_at)->not->toBeNull();
    expect($backorder->quantity_fulfilled)->toBe($backorder->quantity_ordered);
    expect($backorder->order->order_statuses_id)->toBe($backorder->intended_order_statuses_id);
    expect(Picklist::query()->where('order_id', $backorder->order_id)->exists())->toBeTrue();

    $stockProduct->refresh();
    expect($stockProduct->on_stock_quantity)->toBe(5);
    expect($stockProduct->free_on_stock_quantity)->toBe(0);
});

it('allocates stock FIFO across two competing backorders for the same product', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus] = createBackorderFulfillmentWarehouse();
    [$product] = createBackorderFulfillmentProduct($warehouse);

    // The newer backorder is inserted first (lower id) so the test fails if
    // allocation ever follows insertion order instead of created_at.
    $newerBackorder = createBackorderedOrder($warehouse, $product, 8, $backorderedStatus, $confirmedStatus, '2026-11-02 10:00:00');
    $olderBackorder = createBackorderedOrder($warehouse, $product, 10, $backorderedStatus, $confirmedStatus, '2026-11-01 10:00:00');

    processRestockPurchaseOrder($warehouse, $product, 12);

    $olderBackorder->refresh();
    $newerBackorder->refresh();

    expect($olderBackorder->status)->toBe(BackorderStatus::Fulfilled);
    expect($olderBackorder->quantity_fulfilled)->toBe(10);
    expect($olderBackorder->order->order_statuses_id)->toBe($confirmedStatus->id);

    expect($newerBackorder->status)->toBe(BackorderStatus::PartiallyFulfilled);
    expect($newerBackorder->quantity_fulfilled)->toBe(2);
    expect($newerBackorder->resolved_at)->toBeNull();
    expect($newerBackorder->order->order_statuses_id)->toBe($backorderedStatus->id);
});

it('tops up a partially fulfilled backorder on a later restock', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus] = createBackorderFulfillmentWarehouse();
    [$product] = createBackorderFulfillmentProduct($warehouse);

    $newerBackorder = createBackorderedOrder($warehouse, $product, 8, $backorderedStatus, $confirmedStatus, '2026-11-02 10:00:00');
    createBackorderedOrder($warehouse, $product, 10, $backorderedStatus, $confirmedStatus, '2026-11-01 10:00:00');

    processRestockPurchaseOrder($warehouse, $product, 12);

    $newerBackorder->refresh();
    expect($newerBackorder->status)->toBe(BackorderStatus::PartiallyFulfilled);
    expect($newerBackorder->quantity_fulfilled)->toBe(2);

    processRestockPurchaseOrder($warehouse, $product, 6);

    $newerBackorder->refresh();

    expect($newerBackorder->status)->toBe(BackorderStatus::Fulfilled);
    expect($newerBackorder->quantity_fulfilled)->toBe(8);
    expect($newerBackorder->resolved_at)->not->toBeNull();
    expect($newerBackorder->order->order_statuses_id)->toBe($confirmedStatus->id);
});

it('does not touch backorders for unrelated products', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus] = createBackorderFulfillmentWarehouse();
    [$restockedProduct] = createBackorderFulfillmentProduct($warehouse);
    [$unrelatedProduct, $unrelatedStockProduct] = createBackorderFulfillmentProduct($warehouse, onStockQuantity: 50);

    $unrelatedBackorder = createBackorderedOrder($warehouse, $unrelatedProduct, 5, $backorderedStatus, $confirmedStatus, '2026-11-01 10:00:00');

    processRestockPurchaseOrder($warehouse, $restockedProduct, 10);

    $unrelatedBackorder->refresh();
    $unrelatedStockProduct->refresh();

    expect($unrelatedBackorder->status)->toBe(BackorderStatus::Open);
    expect($unrelatedBackorder->quantity_fulfilled)->toBe(0);
    expect($unrelatedBackorder->order->order_statuses_id)->toBe($backorderedStatus->id);
    expect($unrelatedStockProduct->free_on_stock_quantity)->toBe(50);
});
