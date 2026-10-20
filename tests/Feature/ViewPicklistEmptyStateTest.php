<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Orders\Models\Order;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ViewPicklist;
use Modules\Picklists\Models\Picklist;
use Modules\Picklists\Models\PicklistProduct;
use Modules\Users\Models\User;

/**
 * @return array{0: User, 1: Warehouse}
 */
function createViewPicklistEmptyStateContext(): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'view-picklist-empty',
        'name' => 'View Picklist Empty',
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'View Picklist Empty Warehouse',
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Picklist Viewer',
        'email' => 'picklist-empty-viewer@example.com',
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('renders the Failed Scans table for a picklist with zero failed products', function () {
    [, $warehouse] = createViewPicklistEmptyStateContext();

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);

    $picklist = Picklist::query()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
    ]);

    PicklistProduct::query()->create([
        'picklist_id' => $picklist->id,
        'barcode' => 'BC-OK',
        'product_title' => 'Fine Product',
    ]);

    Livewire::test(ViewPicklist::class, ['record' => $picklist->getKey()])
        ->assertOk()
        ->assertSee('No failed scans');
});

it('renders the Products table for a picklist with zero products', function () {
    [, $warehouse] = createViewPicklistEmptyStateContext();

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);

    $picklist = Picklist::query()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
    ]);

    Livewire::test(ViewPicklist::class, ['record' => $picklist->getKey()])
        ->assertOk()
        ->assertSee('No products found')
        ->assertSee('No failed scans');
});
