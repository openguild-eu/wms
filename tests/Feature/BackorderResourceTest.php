<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Filament\Resources\Backorders\Pages\ListBackorders;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\Product;
use Modules\Users\Models\User;

uses(RefreshDatabase::class);

function createBackorderResourceWarehouse(string $slug): Warehouse
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "backorder-resource-{$slug}",
        'name' => "Backorder Resource {$slug}",
    ]);

    return Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Warehouse {$slug}",
        'currency' => 'EUR',
    ]);
}

function actAsBackorderResourceTenant(Warehouse $warehouse): void
{
    app()->instance('current_subdomain', $warehouse->subdomain);

    $user = User::query()->create([
        'subdomain_id' => $warehouse->subdomain_id,
        'name' => 'Warehouse Manager',
        'email' => 'backorder-manager-'.uniqid().'@example.com',
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);
}

function createBackorderResourceRecord(Warehouse $warehouse, BackorderStatus $status = BackorderStatus::Open): Backorder
{
    $intendedStatus = OrderStatus::query()->firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'name' => 'Confirmed'],
        ['color' => '#10B981', 'reserve_stock' => true],
    );

    $backorderedStatus = OrderStatus::query()->firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'name' => 'Backordered'],
        ['color' => '#F59E0B', 'on_hold' => true],
    );

    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-'.uniqid(),
        'product_code' => 'PROD-'.uniqid(),
        'barcode' => (string) random_int(1_000_000_000_000, 9_999_999_999_999),
        'name' => 'Listed Product '.uniqid(),
        'description' => 'Listed description',
    ]);

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);

    $orderProduct = OrderProduct::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 4,
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
        'quantity_ordered' => 4,
        'quantity_fulfilled' => $status === BackorderStatus::Fulfilled ? 4 : 0,
        'status' => $status,
    ]);
}

it('lists backorders for the current tenant warehouse only', function () {
    $warehouse = createBackorderResourceWarehouse('own');
    $otherWarehouse = createBackorderResourceWarehouse('other');

    $ownBackorder = createBackorderResourceRecord($warehouse);
    $foreignBackorder = createBackorderResourceRecord($otherWarehouse);

    actAsBackorderResourceTenant($warehouse);

    Livewire::test(ListBackorders::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$ownBackorder])
        ->assertCanNotSeeTableRecords([$foreignBackorder]);
});

it('filters by status', function () {
    $warehouse = createBackorderResourceWarehouse('filter');

    $openBackorder = createBackorderResourceRecord($warehouse, BackorderStatus::Open);
    $cancelledBackorder = createBackorderResourceRecord($warehouse, BackorderStatus::Cancelled);

    actAsBackorderResourceTenant($warehouse);

    Livewire::test(ListBackorders::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$openBackorder, $cancelledBackorder])
        ->filterTable('status', BackorderStatus::Cancelled->value)
        ->assertCanSeeTableRecords([$cancelledBackorder])
        ->assertCanNotSeeTableRecords([$openBackorder]);
});

it('cancels a backorder without changing its order', function () {
    $warehouse = createBackorderResourceWarehouse('cancel');
    $backorder = createBackorderResourceRecord($warehouse);

    $order = Order::query()->findOrFail($backorder->order_id);
    $orderAttributesBefore = $order->getAttributes();

    actAsBackorderResourceTenant($warehouse);

    Livewire::test(ListBackorders::class)
        ->loadTable()
        ->callAction(TestAction::make('cancel')->table($backorder));

    $backorder->refresh();

    expect($backorder->status)->toBe(BackorderStatus::Cancelled);
    expect($backorder->resolved_at)->not->toBeNull();
    expect(Order::query()->findOrFail($order->id)->getAttributes())->toBe($orderAttributesBefore);
});

it('hides the cancel action once a backorder is fulfilled or cancelled', function () {
    $warehouse = createBackorderResourceWarehouse('hidden');

    $openBackorder = createBackorderResourceRecord($warehouse, BackorderStatus::Open);
    $fulfilledBackorder = createBackorderResourceRecord($warehouse, BackorderStatus::Fulfilled);
    $cancelledBackorder = createBackorderResourceRecord($warehouse, BackorderStatus::Cancelled);

    actAsBackorderResourceTenant($warehouse);

    Livewire::test(ListBackorders::class)
        ->loadTable()
        ->assertActionVisible(TestAction::make('cancel')->table($openBackorder))
        ->assertActionHidden(TestAction::make('cancel')->table($fulfilledBackorder))
        ->assertActionHidden(TestAction::make('cancel')->table($cancelledBackorder));
});
