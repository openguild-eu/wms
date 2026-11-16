<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\Product;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: string}
 */
function createBackorderApiContext(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-backorders',
        'name' => 'Api Backorders',
    ]);

    app()->instance('current_subdomain', $subdomain);
    URL::forceRootUrl('http://api-backorders.wms.test');

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Main Warehouse',
        'currency' => 'EUR',
    ]);

    $token = 'test-plaintext-token';

    ApiKey::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Storefront',
        'key_hash' => hash('sha256', $token),
        'is_active' => true,
    ]);

    return [$warehouse, $token];
}

function createForeignBackorderWarehouse(): Warehouse
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-backorders-foreign',
        'name' => 'Api Backorders Foreign',
    ]);

    return Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Foreign Warehouse',
        'currency' => 'EUR',
    ]);
}

/**
 * @return array<string, string>
 */
function backorderApiHeaders(string $token): array
{
    return [
        'Host' => 'api-backorders.wms.test',
        'X-Api-Key' => $token,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createBackorderRecord(Warehouse $warehouse, array $overrides = []): Backorder
{
    $intendedStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Confirmed-'.uniqid(),
        'color' => '#10B981',
        'reserve_stock' => true,
    ]);

    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-'.uniqid(),
        'product_code' => 'PROD-'.uniqid(),
        'barcode' => (string) random_int(1_000_000_000_000, 9_999_999_999_999),
        'name' => 'Backordered Product '.uniqid(),
        'description' => 'Demo description',
    ]);

    // Preload the warehouse relation so OrderObserver::creating() can derive
    // generated_year_order_id/generated_custom_order_id without re-querying
    // the warehouse through the TenantScope-guarded Warehouse::find(), which
    // would silently miss a warehouse outside the currently bound tenant.
    $order = new Order(['warehouse_id' => $warehouse->id]);
    $order->setRelation('warehouse', $warehouse);
    $order->save();

    $orderProduct = OrderProduct::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 4,
        'barcode' => $product->barcode,
        'reference_code' => $product->reference_code,
        'price' => 9.99,
    ]);

    return Backorder::factory()->create(array_merge([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
        'order_product_id' => $orderProduct->id,
        'product_id' => $product->id,
        'intended_order_statuses_id' => $intendedStatus->id,
        'quantity_ordered' => 4,
        'quantity_fulfilled' => 0,
    ], $overrides));
}

it('rejects a request without an api key', function () {
    createBackorderApiContext();

    $response = $this->getJson('/api/v1/backorders', ['Host' => 'api-backorders.wms.test']);

    $response->assertStatus(401);
});

it('rejects a request with an invalid api key', function () {
    createBackorderApiContext();

    $response = $this->getJson('/api/v1/backorders', backorderApiHeaders('not-a-real-token'));

    $response->assertStatus(401);
});

it('lists only backorders belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createBackorderApiContext();
    $foreignWarehouse = createForeignBackorderWarehouse();

    $ownBackorder = createBackorderRecord($warehouse);
    createBackorderRecord($foreignWarehouse);

    $response = $this->getJson('/api/v1/backorders', backorderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $ownBackorder->id);
    $response->assertJsonPath('data.0.quantity_remaining', 4);
});

it('paginates backorders in pages of 50', function () {
    [$warehouse, $token] = createBackorderApiContext();

    for ($i = 0; $i < 55; $i++) {
        createBackorderRecord($warehouse);
    }

    $response = $this->getJson('/api/v1/backorders', backorderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(50, 'data');
    $response->assertJsonPath('meta.total', 55);
    $response->assertJsonPath('meta.per_page', 50);
});

it('shows a backorder belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createBackorderApiContext();
    $backorder = createBackorderRecord($warehouse);

    $response = $this->getJson("/api/v1/backorders/{$backorder->id}", backorderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonPath('data.id', $backorder->id);
    $response->assertJsonPath('data.status', 'open');
});

it('returns 404 when fetching a backorder belonging to a different warehouse', function () {
    [, $token] = createBackorderApiContext();
    $foreignWarehouse = createForeignBackorderWarehouse();
    $foreignBackorder = createBackorderRecord($foreignWarehouse);

    $response = $this->getJson("/api/v1/backorders/{$foreignBackorder->id}", backorderApiHeaders($token));

    $response->assertStatus(404);
});
