<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: OrderStatus, 2: OrderStatus}
 */
function createBackorderCreationWarehouse(bool $withBackorderedStatus = true): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'backorder-creation-'.uniqid(),
        'name' => 'Backorder Creation',
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Main Warehouse',
        'currency' => 'EUR',
    ]);

    $newStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'New',
        'color' => '#3B82F6',
        'concepted' => true,
    ]);

    $backorderedStatus = new OrderStatus;

    if ($withBackorderedStatus) {
        $backorderedStatus = OrderStatus::query()->create([
            'warehouse_id' => $warehouse->id,
            'name' => 'Backordered',
            'color' => '#F59E0B',
            'on_hold' => true,
        ]);
    }

    return [$warehouse, $newStatus, $backorderedStatus];
}

function createBackorderCreationProduct(Warehouse $warehouse, int $freeQuantity, bool $stockUnlimited = false): Product
{
    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-'.uniqid(),
        'product_code' => 'PROD-'.uniqid(),
        'barcode' => (string) random_int(1_000_000_000_000, 9_999_999_999_999),
        'name' => 'Backorder Product '.uniqid(),
        'description' => 'Backorder description',
        'price' => 10.00,
        'stock_unlimited' => $stockUnlimited,
    ]);

    StockProduct::query()->create([
        'product_id' => $product->id,
        'on_stock_quantity' => $freeQuantity,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => $freeQuantity,
    ]);

    return $product;
}

/**
 * @param  array<int, array{product_id: int, quantity: int}>  $items
 * @return array<string, mixed>
 */
function backorderCreationPayload(OrderStatus $status, array $items): array
{
    return [
        'order_status_id' => $status->id,
        'client' => ['email' => 'customer@example.com'],
        'delivery_name' => 'Jane Doe',
        'delivery_address' => 'Main Street 1',
        'delivery_zipcode' => '1234AB',
        'delivery_city' => 'Amsterdam',
        'delivery_country' => 'NL',
        'items' => $items,
    ];
}

it('creates a backorder and holds the order when stock is insufficient', function () {
    [$warehouse, $newStatus, $backorderedStatus] = createBackorderCreationWarehouse();
    $product = createBackorderCreationProduct($warehouse, freeQuantity: 1);

    $order = app(OrderCreationService::class)->create($warehouse, backorderCreationPayload($newStatus, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]));

    expect($order->exists)->toBeTrue();
    expect($order->fresh()->order_statuses_id)->toBe($backorderedStatus->id);

    $backorder = Backorder::query()->where('order_id', $order->id)->sole();

    expect($backorder->product_id)->toBe($product->id);
    expect($backorder->quantity_ordered)->toBe(5);
    expect($backorder->quantity_fulfilled)->toBe(0);
    expect($backorder->status)->toBe(BackorderStatus::Open);
    expect($backorder->intended_order_statuses_id)->toBe($newStatus->id);
    expect($backorder->warehouse_id)->toBe($warehouse->id);
    expect($backorder->orderProduct->product_id)->toBe($product->id);
});

it('creates one backorder per insufficient line when an order has multiple products', function () {
    [$warehouse, $newStatus, $backorderedStatus] = createBackorderCreationWarehouse();
    $shortProductA = createBackorderCreationProduct($warehouse, freeQuantity: 1);
    $shortProductB = createBackorderCreationProduct($warehouse, freeQuantity: 2);
    $coveredProduct = createBackorderCreationProduct($warehouse, freeQuantity: 10);

    $order = app(OrderCreationService::class)->create($warehouse, backorderCreationPayload($newStatus, [
        ['product_id' => $shortProductA->id, 'quantity' => 5],
        ['product_id' => $shortProductB->id, 'quantity' => 3],
        ['product_id' => $coveredProduct->id, 'quantity' => 2],
    ]));

    expect($order->fresh()->order_statuses_id)->toBe($backorderedStatus->id);

    $backorders = Backorder::query()->where('order_id', $order->id)->get();

    expect($backorders)->toHaveCount(2);
    expect($backorders->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$shortProductA->id, $shortProductB->id])->sort()->values()->all());
    expect($backorders->firstWhere('product_id', $shortProductA->id)->quantity_ordered)->toBe(5);
    expect($backorders->firstWhere('product_id', $shortProductB->id)->quantity_ordered)->toBe(3);
});

it('does not create a backorder when stock is sufficient', function () {
    [$warehouse, $newStatus] = createBackorderCreationWarehouse();
    $product = createBackorderCreationProduct($warehouse, freeQuantity: 10);

    $order = app(OrderCreationService::class)->create($warehouse, backorderCreationPayload($newStatus, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]));

    expect($order->fresh()->order_statuses_id)->toBe($newStatus->id);
    expect(Backorder::query()->count())->toBe(0);
});

it('never backorders a stock_unlimited product', function () {
    [$warehouse, $newStatus] = createBackorderCreationWarehouse();
    $product = createBackorderCreationProduct($warehouse, freeQuantity: 0, stockUnlimited: true);

    $order = app(OrderCreationService::class)->create($warehouse, backorderCreationPayload($newStatus, [
        ['product_id' => $product->id, 'quantity' => 500],
    ]));

    expect($order->fresh()->order_statuses_id)->toBe($newStatus->id);
    expect(Backorder::query()->count())->toBe(0);
});

it('throws when the warehouse has no Backordered order status configured', function () {
    [$warehouse, $newStatus] = createBackorderCreationWarehouse(withBackorderedStatus: false);
    $product = createBackorderCreationProduct($warehouse, freeQuantity: 1);

    app(OrderCreationService::class)->create($warehouse, backorderCreationPayload($newStatus, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]));
})->throws(RuntimeException::class, "missing its 'Backordered' order status");
