<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Orders\Models\PurchaseOrderStatus;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: string}
 */
function createPurchaseOrderApiContext(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-purchase-orders',
        'name' => 'Api Purchase Orders',
    ]);

    app()->instance('current_subdomain', $subdomain);
    URL::forceRootUrl('http://api-purchase-orders.wms.test');

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

function createForeignPurchaseOrderWarehouse(): Warehouse
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-purchase-orders-foreign',
        'name' => 'Api Purchase Orders Foreign',
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
function purchaseOrderApiHeaders(string $token): array
{
    return [
        'Host' => 'api-purchase-orders.wms.test',
        'X-Api-Key' => $token,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createPurchaseOrderRecord(Warehouse $warehouse, array $overrides = []): PurchaseOrder
{
    return PurchaseOrder::query()->create(array_merge([
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Concept,
        'generated_year_purchase_order_id' => 2026,
        'generated_custom_purchase_order_id' => 'PO-'.uniqid(),
    ], $overrides));
}

it('rejects a request without an api key', function () {
    createPurchaseOrderApiContext();

    $response = $this->getJson('/api/v1/purchase-orders', ['Host' => 'api-purchase-orders.wms.test']);

    $response->assertStatus(401);
});

it('rejects a request with an invalid api key', function () {
    createPurchaseOrderApiContext();

    $response = $this->getJson('/api/v1/purchase-orders', purchaseOrderApiHeaders('not-a-real-token'));

    $response->assertStatus(401);
});

it('lists only purchase orders belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createPurchaseOrderApiContext();
    $foreignWarehouse = createForeignPurchaseOrderWarehouse();

    $ownPurchaseOrder = createPurchaseOrderRecord($warehouse);
    createPurchaseOrderRecord($foreignWarehouse);

    $response = $this->getJson('/api/v1/purchase-orders', purchaseOrderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $ownPurchaseOrder->id);
});

it('paginates purchase orders in pages of 50', function () {
    [$warehouse, $token] = createPurchaseOrderApiContext();

    for ($i = 0; $i < 55; $i++) {
        createPurchaseOrderRecord($warehouse, ['generated_custom_purchase_order_id' => "PO-{$i}"]);
    }

    $response = $this->getJson('/api/v1/purchase-orders', purchaseOrderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(50, 'data');
    $response->assertJsonPath('meta.total', 55);
    $response->assertJsonPath('meta.per_page', 50);
});

it('shows a purchase order belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createPurchaseOrderApiContext();
    $purchaseOrder = createPurchaseOrderRecord($warehouse);

    $response = $this->getJson("/api/v1/purchase-orders/{$purchaseOrder->id}", purchaseOrderApiHeaders($token));

    $response->assertOk();
    $response->assertJsonPath('data.id', $purchaseOrder->id);
});

it('returns 404 when fetching a purchase order belonging to a different warehouse', function () {
    [, $token] = createPurchaseOrderApiContext();
    $foreignWarehouse = createForeignPurchaseOrderWarehouse();
    $foreignPurchaseOrder = createPurchaseOrderRecord($foreignWarehouse);

    $response = $this->getJson("/api/v1/purchase-orders/{$foreignPurchaseOrder->id}", purchaseOrderApiHeaders($token));

    $response->assertStatus(404);
});
