<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Orders\Models\Order;
use Modules\Picklists\Models\Picklist;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: string}
 */
function createPicklistApiContext(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-picklists',
        'name' => 'Api Picklists',
    ]);

    app()->instance('current_subdomain', $subdomain);
    URL::forceRootUrl('http://api-picklists.wms.test');

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

function createForeignPicklistWarehouse(): Warehouse
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-picklists-foreign',
        'name' => 'Api Picklists Foreign',
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
function picklistApiHeaders(string $token): array
{
    return [
        'Host' => 'api-picklists.wms.test',
        'X-Api-Key' => $token,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createPicklistRecord(Warehouse $warehouse, array $overrides = []): Picklist
{
    // Preload the warehouse relation so OrderObserver::creating() can derive
    // generated_year_order_id/generated_custom_order_id without re-querying
    // the warehouse through the TenantScope-guarded Warehouse::find(), which
    // would silently miss a warehouse outside the currently bound tenant.
    $order = new Order(['warehouse_id' => $warehouse->id]);
    $order->setRelation('warehouse', $warehouse);
    $order->save();

    return Picklist::query()->create(array_merge([
        'order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'generated_year_picklist_id' => 2026,
        'generated_custom_picklist_id' => 'PICK-'.uniqid(),
    ], $overrides));
}

it('rejects a request without an api key', function () {
    createPicklistApiContext();

    $response = $this->getJson('/api/v1/picklists', ['Host' => 'api-picklists.wms.test']);

    $response->assertStatus(401);
});

it('rejects a request with an invalid api key', function () {
    createPicklistApiContext();

    $response = $this->getJson('/api/v1/picklists', picklistApiHeaders('not-a-real-token'));

    $response->assertStatus(401);
});

it('lists only picklists belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createPicklistApiContext();
    $foreignWarehouse = createForeignPicklistWarehouse();

    $ownPicklist = createPicklistRecord($warehouse);
    createPicklistRecord($foreignWarehouse);

    $response = $this->getJson('/api/v1/picklists', picklistApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $ownPicklist->id);
});

it('paginates picklists in pages of 50', function () {
    [$warehouse, $token] = createPicklistApiContext();

    for ($i = 0; $i < 55; $i++) {
        createPicklistRecord($warehouse, ['generated_custom_picklist_id' => "PICK-{$i}"]);
    }

    $response = $this->getJson('/api/v1/picklists', picklistApiHeaders($token));

    $response->assertOk();
    $response->assertJsonCount(50, 'data');
    $response->assertJsonPath('meta.total', 55);
    $response->assertJsonPath('meta.per_page', 50);
});

it('shows a picklist belonging to the authenticated warehouse', function () {
    [$warehouse, $token] = createPicklistApiContext();
    $picklist = createPicklistRecord($warehouse);

    $response = $this->getJson("/api/v1/picklists/{$picklist->id}", picklistApiHeaders($token));

    $response->assertOk();
    $response->assertJsonPath('data.id', $picklist->id);
});

it('returns 404 when fetching a picklist belonging to a different warehouse', function () {
    [, $token] = createPicklistApiContext();
    $foreignWarehouse = createForeignPicklistWarehouse();
    $foreignPicklist = createPicklistRecord($foreignWarehouse);

    $response = $this->getJson("/api/v1/picklists/{$foreignPicklist->id}", picklistApiHeaders($token));

    $response->assertStatus(404);
});
