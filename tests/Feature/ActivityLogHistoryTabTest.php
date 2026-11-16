<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Models\WebhookEndpoint;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Brands\Filament\Resources\Brands\Pages\ManageBrandActivities;
use Modules\Brands\Models\Brand;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientActivities;
use Modules\Clients\Models\Client;
use Modules\Orders\Filament\Resources\Orders\Pages\ManageOrderActivities;
use Modules\Orders\Filament\Resources\OrderStatuses\Pages\ManageOrderStatusActivities;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrderActivities;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderStatus;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ManagePicklistActivities;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Filament\Resources\Products\Pages\ManageProductHistory;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;
use Modules\Settings\Filament\Resources\ApiKeys\Pages\ManageApiKeyActivities;
use Modules\Settings\Filament\Resources\AttributeGroups\Pages\ManageAttributeGroupActivities;
use Modules\Settings\Filament\Resources\VatRates\Pages\ManageVatRateActivities;
use Modules\Settings\Filament\Resources\Warehouses\Pages\ManageWarehouseActivities;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\ManageWebhookEndpointActivities;
use Modules\Settings\Models\AttributeGroup;
use Modules\Settings\Models\VatRate;
use Modules\Users\Filament\Resources\Users\Pages\ManageUserActivities;
use Modules\Users\Models\User;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Warehouse}
 */
function createActivityLogHistoryContext(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'activity-history',
        'name' => 'Activity History',
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Main Warehouse',
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'History Viewer',
        'email' => 'history@example.com',
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('renders the History tab without error for every audited resource', function (string $pageClass, Closure $makeRecord) {
    [$user, $warehouse] = createActivityLogHistoryContext();

    $record = $makeRecord($warehouse, $user);

    Livewire::test($pageClass, ['record' => $record->getKey()])
        ->assertOk();
})->with([
    'Order' => [ManageOrderActivities::class, fn (Warehouse $warehouse) => Order::query()->create([
        'warehouse_id' => $warehouse->id,
    ])],
    'Product' => [ManageProductHistory::class, fn (Warehouse $warehouse) => Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-001',
        'product_code' => 'PROD-001',
        'barcode' => '1111111111111',
        'name' => 'Demo Product',
        'description' => 'Demo description',
    ])],
    'Client' => [ManageClientActivities::class, fn (Warehouse $warehouse) => Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'client@example.com',
    ])],
    'PurchaseOrder' => [ManagePurchaseOrderActivities::class, fn (Warehouse $warehouse) => PurchaseOrder::query()->create([
        'warehouse_id' => $warehouse->id,
        'expected_delivery_date' => now()->addDay()->toDateString(),
    ])],
    'Warehouse' => [ManageWarehouseActivities::class, fn (Warehouse $warehouse) => $warehouse],
    'ApiKey' => [ManageApiKeyActivities::class, fn (Warehouse $warehouse) => ApiKey::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Storefront Key',
        'key_hash' => hash('sha256', 'secret-token'),
    ])],
    'WebhookEndpoint' => [ManageWebhookEndpointActivities::class, fn (Warehouse $warehouse) => WebhookEndpoint::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Order sync',
        'url' => 'https://example.com/webhook',
        'secret' => 'whsec_secret-token',
        'subscribed_events' => ['order.created'],
    ])],
    'VatRate' => [ManageVatRateActivities::class, fn (Warehouse $warehouse) => VatRate::query()->forceCreate([
        'warehouse_id' => $warehouse->id,
        'name' => 'Standard',
        'rate' => 21,
    ])],
    'AttributeGroup' => [ManageAttributeGroupActivities::class, fn (Warehouse $warehouse) => AttributeGroup::query()->forceCreate([
        'warehouse_id' => $warehouse->id,
        'name' => 'Size',
    ])],
    'User' => [ManageUserActivities::class, fn (Warehouse $warehouse, User $user) => $user],
    'Brand' => [ManageBrandActivities::class, fn (Warehouse $warehouse) => Brand::query()->forceCreate([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'BRAND-001',
        'name' => 'Acme',
        'description' => 'Demo brand',
    ])],
    'Picklist' => [ManagePicklistActivities::class, fn (Warehouse $warehouse) => Picklist::query()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => Order::query()->create(['warehouse_id' => $warehouse->id])->id,
    ])],
    'OrderStatus' => [ManageOrderStatusActivities::class, fn (Warehouse $warehouse) => OrderStatus::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Concept',
        'color' => '#ffffff',
    ])],
    'Product with stock (via combined History tab)' => [ManageProductHistory::class, function (Warehouse $warehouse) {
        $product = Product::query()->create([
            'warehouse_id' => $warehouse->id,
            'active' => true,
            'reference_code' => 'REF-STOCK-001',
            'product_code' => 'PROD-STOCK-001',
            'barcode' => '2222222222222',
            'name' => 'Stock Demo Product',
            'description' => 'Demo description',
        ]);

        StockProduct::query()->create([
            'product_id' => $product->id,
            'on_stock_quantity' => 5,
            'reserved_quantity' => 0,
            'reserved_on_picklists' => 0,
            'free_on_stock_quantity' => 5,
        ]);

        return $product;
    }],
]);

it('combines Product and StockProduct activity into one History tab, newest first', function () {
    [, $warehouse] = createActivityLogHistoryContext();

    // activity_log.created_at is second-precision; without spacing these out,
    // ties would make the "newest first" order below non-deterministic.
    $this->travelTo(now());

    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-STOCK-002',
        'product_code' => 'PROD-STOCK-002',
        'barcode' => '3333333333333',
        'name' => 'Stock Demo Product Two',
        'description' => 'Demo description',
    ]);
    // Product's own "created" activity now exists.

    $this->travelTo(now()->addSecond());

    $stockProduct = StockProduct::query()->create([
        'product_id' => $product->id,
        'on_stock_quantity' => 5,
        'reserved_quantity' => 0,
        'reserved_on_picklists' => 0,
        'free_on_stock_quantity' => 5,
    ]);
    // StockProduct's own "created" activity now exists.

    $this->travelTo(now()->addSecond());

    $stockProduct->update(['on_stock_quantity' => 10, 'free_on_stock_quantity' => 10]);
    // StockProduct's "updated" activity now exists.

    $this->travelTo(now()->addSecond());

    $product->update(['name' => 'Renamed Product']);
    // Product's "updated" activity now exists — newest so far.

    $stockActivities = $product->fresh()->stockActivities;

    expect($stockActivities)->toHaveCount(2) // created + updated
        ->and($stockActivities->pluck('subject_type')->unique()->all())->toBe([StockProduct::class])
        ->and($product->fresh()->activitiesAsSubject()->count())->toBe(2); // Product's own created + updated

    $productActivities = $product->fresh()->activitiesAsSubject;
    $combined = $stockActivities->concat($productActivities)->sortByDesc('id')->values();

    // Every table in this app is configured with ->deferLoading() globally
    // (see App\Providers\FilamentServiceProvider), so a plain Livewire::test()
    // snapshot never executes the wire:init follow-up request that actually
    // loads rows — asserting on the table's own query results is the direct,
    // reliable way to verify the combined/ordered dataset instead.
    $livewire = Livewire::test(ManageProductHistory::class, ['record' => $product->getKey()])
        ->assertOk();

    $renderedIds = $livewire->instance()->getTable()->getRecords()->pluck('id')->values();

    expect($renderedIds->all())->toBe($combined->pluck('id')->all());
});

it('excludes the api key hash from logged attribute changes', function () {
    [, $warehouse] = createActivityLogHistoryContext();

    $apiKey = ApiKey::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Storefront Key',
        'key_hash' => hash('sha256', 'secret-token'),
    ]);

    $apiKey->update(['key_hash' => hash('sha256', 'rotated-token'), 'name' => 'Rotated Key']);

    $activity = $apiKey->activitiesAsSubject()->latest('id')->first();

    expect($activity->attribute_changes->get('attributes', []))
        ->not->toHaveKey('key_hash')
        ->toHaveKey('name');
});

it('excludes the webhook secret from logged attribute changes', function () {
    [, $warehouse] = createActivityLogHistoryContext();

    $webhookEndpoint = WebhookEndpoint::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Order sync',
        'url' => 'https://example.com/webhook',
        'secret' => 'whsec_secret-token',
        'subscribed_events' => ['order.created'],
    ]);

    $webhookEndpoint->update(['secret' => 'whsec_rotated-token', 'name' => 'Order sync renamed']);

    $activity = $webhookEndpoint->activitiesAsSubject()->latest('id')->first();

    expect($activity->attribute_changes->get('attributes', []))
        ->not->toHaveKey('secret')
        ->toHaveKey('name');
});

it('excludes the password from logged attribute changes', function () {
    [, $warehouse] = createActivityLogHistoryContext();

    $user = User::query()->create([
        'subdomain_id' => $warehouse->subdomain_id,
        'name' => 'Secret Agent',
        'email' => 'agent@example.com',
        'password' => 'password',
    ]);

    $user->update(['password' => 'new-password', 'name' => 'Secret Agent Updated']);

    $activity = $user->activitiesAsSubject()->latest('id')->first();

    expect($activity->attribute_changes->get('attributes', []))
        ->not->toHaveKey('password')
        ->toHaveKey('name');
});
