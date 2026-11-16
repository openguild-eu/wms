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
 * @return array{0: Warehouse, 1: OrderStatus, 2: OrderStatus}
 */
function createPartialAllocationWarehouse(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'backorder-partial-allocation-'.uniqid(),
        'name' => 'Backorder Partial Allocation',
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
function createPartialAllocationProduct(Warehouse $warehouse): array
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

function createPartialAllocationBackorderedOrder(
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

function processPartialAllocationRestockPurchaseOrder(Warehouse $warehouse, Product $product, int $quantity): PurchaseOrder
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

it('does not double-allocate a short restock across multiple competing backorders', function () {
    [$warehouse, $confirmedStatus, $backorderedStatus] = createPartialAllocationWarehouse();
    [$product] = createPartialAllocationProduct($warehouse);

    // Three backorders for 10 units each, oldest first. A restock of only 4
    // units arrives - not even enough to fully cover the oldest one.
    $oldest = createPartialAllocationBackorderedOrder($warehouse, $product, 10, $backorderedStatus, $confirmedStatus, '2026-11-01 10:00:00');
    $middle = createPartialAllocationBackorderedOrder($warehouse, $product, 10, $backorderedStatus, $confirmedStatus, '2026-11-02 10:00:00');
    $newest = createPartialAllocationBackorderedOrder($warehouse, $product, 10, $backorderedStatus, $confirmedStatus, '2026-11-03 10:00:00');

    processPartialAllocationRestockPurchaseOrder($warehouse, $product, 4);

    $oldest->refresh();
    $middle->refresh();
    $newest->refresh();

    expect($oldest->status)->toBe(BackorderStatus::PartiallyFulfilled);
    expect($oldest->quantity_fulfilled)->toBe(4);

    // The 4 restocked units were already promised to the oldest backorder -
    // nothing is left for middle/newest.
    expect($middle->quantity_fulfilled)->toBe(0);
    expect($middle->status)->toBe(BackorderStatus::Open);
    expect($newest->quantity_fulfilled)->toBe(0);
    expect($newest->status)->toBe(BackorderStatus::Open);

    // Total fulfilled across all backorders must never exceed physical stock received.
    $totalFulfilled = $oldest->quantity_fulfilled + $middle->quantity_fulfilled + $newest->quantity_fulfilled;
    expect($totalFulfilled)->toBe(4);
});
