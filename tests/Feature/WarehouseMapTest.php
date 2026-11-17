<?php

declare(strict_types=1);

use App\Filament\Pages\WarehouseMap;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Products\Models\StockLocation;
use Modules\Users\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * @return array{0: User, 1: Warehouse}
 */
function createWarehouseMapContext(string $suffix, ?float $floorWidth = 20.0, ?float $floorHeight = 10.0): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "warehouse-map-{$suffix}",
        'name' => "Warehouse Map {$suffix}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Warehouse Map Warehouse {$suffix}",
        'currency' => 'EUR',
        'floor_width' => $floorWidth,
        'floor_height' => $floorHeight,
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Warehouse Map Manager {$suffix}",
        'email' => "warehouse-map-manager-{$suffix}@example.com",
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('allows any authenticated user to load the map page', function () {
    createWarehouseMapContext('access');

    Livewire::test(WarehouseMap::class)->assertOk();
});

it('persists x, y, width and height for a location in the current tenant', function () {
    [, $warehouse] = createWarehouseMapContext('persist');

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('saveLocationLayout', $location->id, 2.5, 3.5, 1.5, 1.0);

    $location->refresh();

    expect((float) $location->x)->toBe(2.5)
        ->and((float) $location->y)->toBe(3.5)
        ->and((float) $location->width)->toBe(1.5)
        ->and((float) $location->height)->toBe(1.0);
});

it('clamps width and height up to a minimum of 0.1', function () {
    [, $warehouse] = createWarehouseMapContext('min-size');

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('saveLocationLayout', $location->id, 0.0, 0.0, 0.0, -5.0);

    $location->refresh();

    expect((float) $location->width)->toBe(0.1)
        ->and((float) $location->height)->toBe(0.1);
});

it('clamps a square exactly to the floor edge without letting it overflow', function () {
    [, $warehouse] = createWarehouseMapContext('boundary', floorWidth: 10.0, floorHeight: 10.0);

    $exactFit = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Exact fit',
        'rank' => 1,
    ]);

    $overflowing = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Overflowing',
        'rank' => 2,
    ]);

    $livewire = Livewire::test(WarehouseMap::class);

    // Requesting a position that exactly fits the floor should be left alone.
    $livewire->call('saveLocationLayout', $exactFit->id, 8.0, 8.0, 2.0, 2.0);
    $exactFit->refresh();

    expect((float) $exactFit->x)->toBe(8.0)
        ->and((float) $exactFit->y)->toBe(8.0);

    // Requesting a position that would overflow past the floor's edge must
    // be pulled back to fit exactly, not merely reduced.
    $livewire->call('saveLocationLayout', $overflowing->id, 9.0, 9.0, 2.0, 2.0);
    $overflowing->refresh();

    expect((float) $overflowing->x)->toBe(8.0)
        ->and((float) $overflowing->y)->toBe(8.0);
});

it('does not clamp x/y against floor bounds when the warehouse has no floor size set', function () {
    [, $warehouse] = createWarehouseMapContext('no-floor-size', floorWidth: null, floorHeight: null);

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('saveLocationLayout', $location->id, 500.0, 500.0, 2.0, 2.0);

    $location->refresh();

    expect((float) $location->x)->toBe(500.0)
        ->and((float) $location->y)->toBe(500.0);
});

it('404s when saving the layout of a location belonging to a different warehouse', function () {
    createWarehouseMapContext('tenant-a');

    $otherSubdomain = Subdomain::query()->create([
        'subdomain' => 'warehouse-map-tenant-b',
        'name' => 'Warehouse Map Tenant B',
    ]);

    $warehouseB = Warehouse::query()->create([
        'subdomain_id' => $otherSubdomain->id,
        'name' => 'Warehouse Map Warehouse B',
        'currency' => 'EUR',
    ]);

    $locationInWarehouseB = StockLocation::query()->create([
        'warehouse_id' => $warehouseB->id,
        'name' => 'Foreign location',
        'rank' => 1,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('saveLocationLayout', $locationInWarehouseB->id, 1.0, 1.0, 1.0, 1.0)
        ->assertNotFound();

    expect($locationInWarehouseB->refresh()->x)->toBeNull();
});

it('does not add a new activity log entry when saving identical layout values twice', function () {
    [, $warehouse] = createWarehouseMapContext('idempotent');

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
    ]);

    $livewire = Livewire::test(WarehouseMap::class);

    $livewire->call('saveLocationLayout', $location->id, 2.0, 2.0, 1.0, 1.0);
    $countAfterFirst = Activity::query()->where('subject_type', $location->getMorphClass())
        ->where('subject_id', $location->id)
        ->count();

    $livewire->call('saveLocationLayout', $location->id, 2.0, 2.0, 1.0, 1.0);
    $countAfterSecond = Activity::query()->where('subject_type', $location->getMorphClass())
        ->where('subject_id', $location->id)
        ->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('returns locations ordered by rank, including both placed and unplaced ones', function () {
    [, $warehouse] = createWarehouseMapContext('get-locations');

    $second = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Second',
        'rank' => 2,
        'x' => 1.0,
        'y' => 1.0,
        'width' => 1.0,
        'height' => 1.0,
    ]);

    $first = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'First',
        'rank' => 1,
    ]);

    $page = new WarehouseMap;

    $locations = $page->getLocations();

    expect($locations->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($locations->firstWhere('id', $first->id)->x)->toBeNull()
        ->and($locations->firstWhere('id', $second->id)->x)->not->toBeNull();
});

it('removes a location from the map by clearing its layout', function () {
    [, $warehouse] = createWarehouseMapContext('remove');

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
        'x' => 2.0,
        'y' => 3.0,
        'width' => 1.5,
        'height' => 1.0,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('removeLocationFromMap', $location->id);

    $location->refresh();

    expect($location->exists)->toBeTrue()
        ->and($location->x)->toBeNull()
        ->and($location->y)->toBeNull()
        ->and($location->width)->toBeNull()
        ->and($location->height)->toBeNull();
});

it('404s when removing a location belonging to a different warehouse from the map', function () {
    createWarehouseMapContext('remove-tenant-a');

    $otherSubdomain = Subdomain::query()->create([
        'subdomain' => 'warehouse-map-remove-tenant-b',
        'name' => 'Warehouse Map Remove Tenant B',
    ]);

    $warehouseB = Warehouse::query()->create([
        'subdomain_id' => $otherSubdomain->id,
        'name' => 'Warehouse Map Remove Warehouse B',
        'currency' => 'EUR',
    ]);

    $locationInWarehouseB = StockLocation::query()->create([
        'warehouse_id' => $warehouseB->id,
        'name' => 'Foreign location',
        'rank' => 1,
        'x' => 2.0,
        'y' => 3.0,
        'width' => 1.0,
        'height' => 1.0,
    ]);

    Livewire::test(WarehouseMap::class)
        ->call('removeLocationFromMap', $locationInWarehouseB->id)
        ->assertNotFound();

    expect((float) $locationInWarehouseB->refresh()->x)->toBe(2.0);
});

it('lists unplaced locations in the sidebar', function () {
    [, $warehouse] = createWarehouseMapContext('sidebar');

    StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Unplaced Shelf',
        'rank' => 1,
    ]);

    Livewire::test(WarehouseMap::class)
        ->assertSee('Unplaced Locations')
        ->assertSee('Unplaced Shelf')
        ->assertSee('Remove From Map');
});

it('updates the floor size from the map page', function () {
    [, $warehouse] = createWarehouseMapContext('floor-size', null, null);

    Livewire::test(WarehouseMap::class)
        ->callAction('editFloorSize', data: [
            'floor_width' => 30,
            'floor_height' => 15,
        ])
        ->assertHasNoActionErrors();

    $warehouse->refresh();

    expect((float) $warehouse->floor_width)->toBe(30.0)
        ->and((float) $warehouse->floor_height)->toBe(15.0);
});

it('requires both floor dimensions when editing the floor size', function () {
    createWarehouseMapContext('floor-size-required');

    Livewire::test(WarehouseMap::class)
        ->callAction('editFloorSize', data: [
            'floor_width' => null,
            'floor_height' => null,
        ])
        ->assertHasActionErrors([
            'floor_width' => 'required',
            'floor_height' => 'required',
        ]);
});

it('keeps placed locations inside the floor when it shrinks', function () {
    [, $warehouse] = createWarehouseMapContext('floor-shrink', 20.0, 10.0);

    $location = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-1',
        'rank' => 1,
        'x' => 15.0,
        'y' => 8.0,
        'width' => 4.0,
        'height' => 6.0,
    ]);

    $unplaced = StockLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'A-2',
        'rank' => 2,
    ]);

    Livewire::test(WarehouseMap::class)
        ->callAction('editFloorSize', data: [
            'floor_width' => 10,
            'floor_height' => 5,
        ]);

    $location->refresh();
    $unplaced->refresh();

    expect((float) $location->x)->toBe(6.0)
        ->and((float) $location->y)->toBe(0.0)
        ->and((float) $location->width)->toBe(4.0)
        ->and((float) $location->height)->toBe(5.0)
        ->and($unplaced->x)->toBeNull();
});

it('saves a walking route by reassigning ranks in the clicked order', function () {
    [, $warehouse] = createWarehouseMapContext('route');

    $first = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => '1', 'rank' => 1]);
    $second = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => '2', 'rank' => 2]);
    $third = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => '3', 'rank' => 3]);

    $ranks = Livewire::test(WarehouseMap::class)
        ->instance()
        ->saveRoute([$first->id, $third->id, $second->id]);

    expect($ranks)->toBe([$first->id => 1, $third->id => 2, $second->id => 3])
        ->and($first->refresh()->rank)->toBe(1)
        ->and($third->refresh()->rank)->toBe(2)
        ->and($second->refresh()->rank)->toBe(3);
});

it('keeps locations missing from the route after it in their current order', function () {
    [, $warehouse] = createWarehouseMapContext('route-rest');

    $a = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'A', 'rank' => 1]);
    $b = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'B', 'rank' => 2]);
    $c = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'C', 'rank' => 3]);
    $d = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'D', 'rank' => 4]);

    Livewire::test(WarehouseMap::class)
        ->call('saveRoute', [$d->id, $b->id]);

    expect($d->refresh()->rank)->toBe(1)
        ->and($b->refresh()->rank)->toBe(2)
        ->and($a->refresh()->rank)->toBe(3)
        ->and($c->refresh()->rank)->toBe(4);
});

it('rejects a route containing a location from a different warehouse without changing ranks', function () {
    [, $warehouse] = createWarehouseMapContext('route-tenant-a');

    $own = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'Own', 'rank' => 1]);
    $ownSecond = StockLocation::query()->create(['warehouse_id' => $warehouse->id, 'name' => 'Own 2', 'rank' => 2]);

    $otherSubdomain = Subdomain::query()->create([
        'subdomain' => 'warehouse-map-route-tenant-b',
        'name' => 'Warehouse Map Route Tenant B',
    ]);

    $warehouseB = Warehouse::query()->create([
        'subdomain_id' => $otherSubdomain->id,
        'name' => 'Warehouse Map Route Warehouse B',
        'currency' => 'EUR',
    ]);

    $foreign = StockLocation::query()->create(['warehouse_id' => $warehouseB->id, 'name' => 'Foreign', 'rank' => 1]);

    Livewire::test(WarehouseMap::class)
        ->call('saveRoute', [$ownSecond->id, $foreign->id])
        ->assertNotFound();

    expect($own->refresh()->rank)->toBe(1)
        ->and($ownSecond->refresh()->rank)->toBe(2);
});
