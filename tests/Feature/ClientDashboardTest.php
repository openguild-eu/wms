<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Clients\Filament\Resources\Clients\Pages\EditClient;
use Modules\Clients\Filament\Resources\Clients\Pages\ViewClient;
use Modules\Clients\Filament\Resources\Clients\Widgets\RecentClientOrdersTable;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAddresses;
use Modules\Orders\Models\Order;
use Modules\Users\Models\User;

uses(RefreshDatabase::class);

function createClientDashboardTestContext(): Warehouse
{
    static $counter = 0;
    $counter++;

    $subdomain = Subdomain::query()->create([
        'subdomain' => "client-dashboard-{$counter}",
        'name' => "Client Dashboard {$counter}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "CD{$counter} Warehouse",
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Warehouse Manager',
        'email' => "manager-cd-{$counter}@example.com",
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return $warehouse;
}

it('registers the view page as the base record route on ClientResource', function () {
    expect(ClientResource::getPages())->toHaveKey('view');
});

it('shows the client\'s general information and address summary on the dashboard', function () {
    $warehouse = createClientDashboardTestContext();

    $client = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'dashboard-client@example.com',
        'company' => 'Phil & Phae',
    ]);

    $deliveryAddress = ClientAddresses::query()->create([
        'client_id' => $client->id,
        'gender' => 'male',
        'name' => 'Warehouse Delivery',
        'address' => 'Dock Street 1',
        'city' => 'Rotterdam',
    ]);

    $client->update(['delivery_client_address_id' => $deliveryAddress->id]);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSuccessful()
        ->assertSee('dashboard-client@example.com')
        ->assertSee('Phil & Phae')
        ->assertSee('Dock Street 1')
        ->assertSee('Rotterdam');
});

it('lists only the client\'s own recent orders on the dashboard', function () {
    $warehouse = createClientDashboardTestContext();

    $client = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'client@example.com',
    ]);

    $otherClient = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'other@example.com',
    ]);

    $order = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'client_id' => $client->id,
    ]);

    $otherOrder = Order::query()->create([
        'warehouse_id' => $warehouse->id,
        'client_id' => $otherClient->id,
    ]);

    Livewire::test(RecentClientOrdersTable::class, ['record' => $client])
        ->assertSuccessful()
        ->loadTable()
        ->assertCanSeeTableRecords([$order])
        ->assertCanNotSeeTableRecords([$otherOrder]);
});

it('redirects back to the client dashboard after saving general information', function () {
    $warehouse = createClientDashboardTestContext();

    $client = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'edit-client@example.com',
    ]);

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->fillForm(['email' => 'updated-client@example.com'])
        ->call('save')
        ->assertRedirect(ClientResource::getUrl('view', ['record' => $client]));
});
