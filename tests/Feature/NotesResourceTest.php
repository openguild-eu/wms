<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientNotes;
use Modules\Clients\Models\Client;
use Modules\Notes\Filament\Resources\Notes\NoteResource;
use Modules\Notes\Filament\Resources\Notes\Pages\ListNotes;
use Modules\Notes\Models\Note;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\Orders\Pages\ManageOrderNotes;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrderNotes;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ManagePicklistNotes;
use Modules\Picklists\Filament\Resources\Picklists\PicklistResource;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Filament\Resources\Products\Pages\ManageProductNotes;
use Modules\Products\Models\Product;
use Modules\Users\Models\User;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Warehouse}
 */
function createNotesResourceContext(string $suffix): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "notes-resource-{$suffix}",
        'name' => "Notes Resource {$suffix}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Notes Resource Warehouse {$suffix}",
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Notes Resource User {$suffix}",
        'email' => "notes-resource-user-{$suffix}@example.com",
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('posts a general message with no subject from the central page', function () {
    [$user, $warehouse] = createNotesResourceContext('general');

    Livewire::test(ListNotes::class)
        ->fillForm(['body' => 'Stock arrived'])
        ->call('postMessage')
        ->assertOk();

    $note = Note::query()->where('body', 'Stock arrived')->first();

    expect($note)->not->toBeNull()
        ->and($note->warehouse_id)->toBe($warehouse->id)
        ->and($note->user_id)->toBe($user->id)
        ->and($note->subject_type)->toBeNull()
        ->and($note->subject_id)->toBeNull();
});

it('posts a message linked to a subject from the central page', function () {
    [, $warehouse] = createNotesResourceContext('linked');

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);

    Livewire::test(ListNotes::class)
        ->fillForm([
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'body' => 'Called about this order',
        ])
        ->call('postMessage')
        ->assertOk();

    $note = Note::query()->where('body', 'Called about this order')->first();

    expect($note)->not->toBeNull()
        ->and($note->subject_type)->toBe(Order::class)
        ->and($note->subject_id)->toBe($order->id);
});

it('rejects an empty message', function () {
    createNotesResourceContext('empty');

    Livewire::test(ListNotes::class)
        ->fillForm(['body' => ''])
        ->call('postMessage')
        ->assertHasFormErrors(['body' => 'required']);

    expect(Note::query()->count())->toBe(0);
});

it('posts a note on a record via its own Notes tab', function (string $pageClass, Closure $makeRecord) {
    [$user, $warehouse] = createNotesResourceContext('per-record');

    $record = $makeRecord($warehouse);

    Livewire::test($pageClass, ['record' => $record->getKey()])
        ->fillForm(['body' => 'Called the client'])
        ->call('postMessage')
        ->assertOk();

    $note = Note::query()->where('body', 'Called the client')->first();

    expect($note)->not->toBeNull()
        ->and($note->subject_type)->toBe($record::class)
        ->and($note->subject_id)->toBe($record->getKey())
        ->and($note->warehouse_id)->toBe($warehouse->id)
        ->and($note->user_id)->toBe($user->id);
})->with([
    'Order' => [ManageOrderNotes::class, fn (Warehouse $warehouse): Order => Order::query()->create([
        'warehouse_id' => $warehouse->id,
    ])],
    'PurchaseOrder' => [ManagePurchaseOrderNotes::class, fn (Warehouse $warehouse): PurchaseOrder => PurchaseOrder::query()->create([
        'warehouse_id' => $warehouse->id,
        'expected_delivery_date' => now()->addDay()->toDateString(),
    ])],
    'Product' => [ManageProductNotes::class, fn (Warehouse $warehouse): Product => Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-NOTE-001',
        'product_code' => 'PROD-NOTE-001',
        'barcode' => '9000000000001',
        'name' => 'Demo Product',
        'description' => 'Demo description',
    ])],
    'Picklist' => [ManagePicklistNotes::class, fn (Warehouse $warehouse): Picklist => Picklist::query()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => Order::query()->create(['warehouse_id' => $warehouse->id])->id,
    ])],
    'Client' => [ManageClientNotes::class, fn (Warehouse $warehouse): Client => Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'client@example.com',
    ])],
]);

it("links a note's subject to the correct resource page", function () {
    [, $warehouse] = createNotesResourceContext('subject-link');

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);
    $orderNote = $order->notes()->create(['body' => 'Order note']);

    $picklist = Picklist::query()->create([
        'warehouse_id' => $warehouse->id,
        'order_id' => $order->id,
    ]);
    $picklistNote = $picklist->notes()->create(['body' => 'Picklist note']);

    expect(NoteResource::getSubjectUrl($orderNote))
        ->toBe(OrderResource::getUrl('edit', ['record' => $order]))
        ->and(NoteResource::getSubjectUrl($picklistNote))
        ->toBe(PicklistResource::getUrl('view', ['record' => $picklist]))
        ->and(NoteResource::getSubjectNotesUrl($orderNote))
        ->toBe(OrderResource::getUrl('notes', ['record' => $order]))
        ->and(NoteResource::getSubjectNotesUrl($picklistNote))
        ->toBe(PicklistResource::getUrl('notes', ['record' => $picklist]));
});

it('shows a subject note linked to its record in the feed', function () {
    [, $warehouse] = createNotesResourceContext('feed-subject');

    $client = Client::query()->create([
        'warehouse_id' => $warehouse->id,
        'email' => 'feed-client@example.com',
    ]);
    $client->notes()->create(['body' => 'Client note']);

    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'General note']);

    Livewire::test(ListNotes::class)
        ->assertOk()
        ->assertSee('Client note')
        ->assertSee('General note')
        ->assertSee($client->getActivityLogTitle());
});

it('groups notes by date, newest first within a day', function () {
    [, $warehouse] = createNotesResourceContext('grouping');

    $now = now();

    test()->travelTo($now->copy()->subDay());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Yesterday message']);

    test()->travelTo($now->copy());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Today earlier message']);

    test()->travelTo($now->copy()->addSecond());
    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'Today later message']);

    Livewire::test(ListNotes::class)
        ->assertSeeInOrder([
            'Today later message',
            'Today earlier message',
            'Yesterday message',
        ]);
});

it('scopes notes to the current tenant warehouse', function () {
    [, $warehouseA] = createNotesResourceContext('tenant-a');

    Note::query()->create(['warehouse_id' => $warehouseA->id, 'body' => 'Warehouse A note']);

    [, $warehouseB] = createNotesResourceContext('tenant-b');

    Note::query()->create(['warehouse_id' => $warehouseB->id, 'body' => 'Warehouse B note']);

    Filament::setTenant($warehouseA);

    $bodies = Note::query()->pluck('body');

    expect($bodies)->toContain('Warehouse A note')
        ->and($bodies)->not->toContain('Warehouse B note');
});

it('shows only that record\'s own notes on its Notes tab, as a feed', function () {
    [, $warehouse] = createNotesResourceContext('record-feed');

    $order = Order::query()->create(['warehouse_id' => $warehouse->id]);
    $order->notes()->create(['body' => 'Called about this order']);

    $otherOrder = Order::query()->create(['warehouse_id' => $warehouse->id]);
    $otherOrder->notes()->create(['body' => 'Unrelated order note']);

    Note::query()->create(['warehouse_id' => $warehouse->id, 'body' => 'General board message']);

    Livewire::test(ManageOrderNotes::class, ['record' => $order->getKey()])
        ->assertOk()
        ->assertSee('Called about this order')
        ->assertDontSee('Unrelated order note')
        ->assertDontSee('General board message');
});

it('registers a Notes page for every per-record resource', function () {
    expect(OrderResource::getPages())->toHaveKey('notes')
        ->and(PicklistResource::getPages())->toHaveKey('notes')
        ->and(ClientResource::getPages())->toHaveKey('notes');
});
