<?php

declare(strict_types=1);

use App\Filament\Pages\Notes;
use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Notes\Models\Note;
use Modules\Users\Models\User;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Warehouse}
 */
function createNotesPageContext(string $suffix): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "notes-page-{$suffix}",
        'name' => "Notes Page {$suffix}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Notes Page Warehouse {$suffix}",
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Notes Page User {$suffix}",
        'email' => "notes-page-user-{$suffix}@example.com",
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('posts a message and attributes it to the current warehouse and user', function () {
    [$user, $warehouse] = createNotesPageContext('post');

    Livewire::test(Notes::class)
        ->fillForm(['body' => 'Stock arrived'])
        ->call('postMessage')
        ->assertOk();

    expect(Note::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('user_id', $user->id)
        ->where('body', 'Stock arrived')
        ->exists())->toBeTrue();
});

it('rejects an empty message', function () {
    createNotesPageContext('empty');

    Livewire::test(Notes::class)
        ->fillForm(['body' => ''])
        ->call('postMessage')
        ->assertHasFormErrors(['body' => 'required']);

    expect(Note::query()->count())->toBe(0);
});

it('groups messages by date, newest date first, and newest message first within a day', function () {
    [, $warehouse] = createNotesPageContext('order');

    $now = now();

    test()->travelTo($now->copy()->subDay());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Yesterday message']);

    test()->travelTo($now->copy());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Today earlier message']);

    test()->travelTo($now->copy()->addSecond());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Today later message']);

    Livewire::test(Notes::class)
        ->assertOk()
        ->assertSeeInOrder([
            'Today later message',
            'Today earlier message',
            'Yesterday message',
        ]);
});

it('scopes messages to the current tenant warehouse', function () {
    [, $warehouseA] = createNotesPageContext('tenant-a');

    Note::query()->create(['warehouse_id' => $warehouseA->id, 'body' => 'Warehouse A note']);

    $subdomainB = Subdomain::query()->create([
        'subdomain' => 'notes-page-tenant-b',
        'name' => 'Notes Page Tenant B',
    ]);
    app()->instance('current_subdomain', $subdomainB);
    $warehouseB = Warehouse::query()->create([
        'subdomain_id' => $subdomainB->id,
        'name' => 'Notes Page Warehouse B',
        'currency' => 'EUR',
    ]);
    Note::query()->create(['warehouse_id' => $warehouseB->id, 'body' => 'Warehouse B note']);

    Filament::setTenant($warehouseA);

    $bodies = Note::query()->pluck('body');

    expect($bodies)->toContain('Warehouse A note')
        ->and($bodies)->not->toContain('Warehouse B note');
});
