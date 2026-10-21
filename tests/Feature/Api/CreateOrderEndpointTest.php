<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Clients\Models\Client;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;

uses(RefreshDatabase::class);

/**
 * @return array{0: Warehouse, 1: string, 2: Product, 3: StockProduct, 4: OrderStatus}
 */
function createApiOrderContext(int $onStockQuantity = 10, bool $stockUnlimited = false): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'api-orders',
        'name' => 'Api Orders',
    ]);

    app()->instance('current_subdomain', $subdomain);

    // Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::prepareUrlForRequest()
    // builds every test request's URL via the `url()` helper, which resolves
    // its host from the UrlGenerator's cached root — not from a `Host`
    // header passed to postJson(). Symfony's Request::create() then derives
    // HTTP_HOST from that URL, so an explicit 'Host' header in
    // apiOrderHeaders() below is never actually seen by IdentifySubdomain
    // unless the root URL is forced to match it (config('app.url') alone is
    // read once at boot and does not retroactively change the cached root).
    URL::forceRootUrl('http://api-orders.wms.test');

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

    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-001',
        'product_code' => 'PROD-001',
        'barcode' => '1111111111111',
        'name' => 'Demo Product',
        'description' => 'Demo description',
        'price' => 19.99,
        'stock_unlimited' => $stockUnlimited,
    ]);

    $stockProduct = StockProduct::query()->create([
        'product_id' => $product->id,
        'on_stock_quantity' => $onStockQuantity,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => $onStockQuantity,
    ]);

    $defaultStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Concept',
        'color' => '#22c55e',
    ]);

    return [$warehouse, $token, $product, $stockProduct, $defaultStatus];
}

/**
 * @return array<string, string>
 */
function apiOrderHeaders(string $token): array
{
    return [
        'Host' => 'api-orders.wms.test',
        'X-Api-Key' => $token,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validOrderPayload(Product $product, OrderStatus $status, array $overrides = []): array
{
    return array_merge([
        'order_status_id' => $status->id,
        'client' => [
            'email' => 'customer@example.com',
        ],
        'delivery_name' => 'Jane Doe',
        'delivery_address' => 'Main Street 1',
        'delivery_zipcode' => '1234AB',
        'delivery_city' => 'Amsterdam',
        'delivery_country' => 'NL',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ], $overrides);
}

it('creates an order with price/name/barcode always derived from the product record', function () {
    [$warehouse, $token, $product, , $status] = createApiOrderContext();

    $payload = validOrderPayload($product, $status, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'price' => 0.01],
        ],
    ]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertCreated();

    $this->assertDatabaseHas('orders', [
        'warehouse_id' => $warehouse->id,
        'delivery_name' => 'Jane Doe',
        'invoice_name' => 'Jane Doe',
    ]);

    $this->assertDatabaseHas('order_products', [
        'product_id' => $product->id,
        'quantity' => 2,
        'name' => $product->name,
        'barcode' => $product->barcode,
    ]);

    $orderProduct = OrderProduct::query()->where('product_id', $product->id)->firstOrFail();
    expect((float) $orderProduct->price)->toBe(19.99);
});

it('is idempotent when the same custom_order_id is submitted twice', function () {
    [, $token, $product, , $status] = createApiOrderContext();

    $payload = validOrderPayload($product, $status, ['custom_order_id' => 'SHOP-1001']);

    $first = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));
    $first->assertCreated();

    $second = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));
    $second->assertOk();

    expect($first->json('data.id'))->toBe($second->json('data.id'));
    $this->assertDatabaseCount('orders', 1);
});

it('rejects an order when requested quantity exceeds available stock', function () {
    [, $token, $product, , $status] = createApiOrderContext(onStockQuantity: 1);

    $payload = validOrderPayload($product, $status, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
    ]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Insufficient stock for one or more items.',
        'errors' => [
            'items' => [
                ['product_id' => $product->id, 'requested' => 5, 'available' => 1],
            ],
        ],
    ]);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_products', 0);
    $this->assertDatabaseCount('clients', 0);
});

it('allows a stock_unlimited product to exceed available stock', function () {
    [, $token, $product, , $status] = createApiOrderContext(onStockQuantity: 1, stockUnlimited: true);

    $payload = validOrderPayload($product, $status, [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 500],
        ],
    ]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertCreated();
});

it('finds an existing client by email without overwriting existing fields', function () {
    [$warehouse, $token, $product, , $status] = createApiOrderContext();

    $client = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'email' => 'repeat-customer@example.com',
        'company' => 'Original Co',
    ]);

    $payload = validOrderPayload($product, $status, [
        'client' => [
            'email' => 'repeat-customer@example.com',
            'company' => 'Different Co',
        ],
    ]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertCreated();
    $this->assertDatabaseCount('clients', 1);

    expect($client->fresh()->company)->toBe('Original Co');
});

it('creates a new client when the email does not already exist', function () {
    [, $token, $product, , $status] = createApiOrderContext();

    $payload = validOrderPayload($product, $status, [
        'client' => ['email' => 'brand-new@example.com'],
    ]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertCreated();
    $this->assertDatabaseHas('clients', ['email' => 'brand-new@example.com']);
});

it('triggers the existing picklist pipeline when the chosen status requires it', function () {
    [$warehouse, $token, $product] = createApiOrderContext();

    $pickingStatus = OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Ready to Pick',
        'color' => '#22c55e',
        'generate_picklist' => true,
        'reserve_stock' => true,
    ]);

    $payload = validOrderPayload($product, $pickingStatus);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertCreated();

    $orderId = $response->json('data.id');

    expect(Picklist::query()->where('order_id', $orderId)->exists())->toBeTrue();
});

it('rejects an order status that belongs to a different warehouse', function () {
    [, $token, $product, , $status] = createApiOrderContext();

    $otherSubdomain = Subdomain::query()->create(['subdomain' => 'other', 'name' => 'Other']);
    $otherWarehouse = Warehouse::query()->create([
        'subdomain_id' => $otherSubdomain->id,
        'name' => 'Other Warehouse',
        'currency' => 'EUR',
    ]);
    $otherStatus = OrderStatus::query()->create([
        'warehouse_id' => $otherWarehouse->id,
        'name' => 'Foreign Status',
        'color' => '#000000',
    ]);

    $payload = validOrderPayload($product, $status, ['order_status_id' => $otherStatus->id]);

    $response = $this->postJson('/api/v1/orders', $payload, apiOrderHeaders($token));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('order_status_id');
});

it('rejects requests without a valid api key', function () {
    [, , $product, , $status] = createApiOrderContext();

    $payload = validOrderPayload($product, $status);

    $response = $this->postJson('/api/v1/orders', $payload, ['Host' => 'api-orders.wms.test']);

    $response->assertStatus(401);
    $this->assertDatabaseCount('orders', 0);
});
