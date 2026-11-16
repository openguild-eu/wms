<?php

declare(strict_types=1);

use App\Enums\WebhookEvent;
use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Models\WebhookEndpoint;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\EditWebhookEndpoint;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use Modules\Users\Models\User;

/**
 * @return array{0: User, 1: Warehouse}
 */
function createWebhookEndpointResourceContext(string $suffix): array
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "webhook-endpoint-{$suffix}",
        'name' => "Webhook Endpoint {$suffix}",
    ]);

    app()->instance('current_subdomain', $subdomain);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Webhook Endpoint Warehouse {$suffix}",
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Webhook Endpoint Manager {$suffix}",
        'email' => "webhook-endpoint-manager-{$suffix}@example.com",
        'password' => 'password',
    ]);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($warehouse);

    return [$user, $warehouse];
}

it('allows any authenticated user to view the list, create and edit pages', function () {
    [, $warehouse] = createWebhookEndpointResourceContext('access');

    $endpoint = WebhookEndpoint::factory()->create(['warehouse_id' => $warehouse->id]);

    Livewire::test(ListWebhookEndpoints::class)->assertOk();
    Livewire::test(CreateWebhookEndpoint::class)->assertOk();
    Livewire::test(EditWebhookEndpoint::class, ['record' => $endpoint->getKey()])->assertOk();
});

it('scopes webhook endpoints to the active tenant warehouse', function () {
    [, $warehouseA] = createWebhookEndpointResourceContext('tenant-a');
    $endpointA = WebhookEndpoint::factory()->create(['warehouse_id' => $warehouseA->id]);

    [, $warehouseB] = createWebhookEndpointResourceContext('tenant-b');
    $endpointB = WebhookEndpoint::factory()->create(['warehouse_id' => $warehouseB->id]);

    Livewire::test(ListWebhookEndpoints::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$endpointB])
        ->assertCanNotSeeTableRecords([$endpointA]);
});

it('validates the create form data', function (array $data, array $errors) {
    createWebhookEndpointResourceContext('validation');

    Livewire::test(CreateWebhookEndpoint::class)
        ->fillForm([
            'name' => 'Valid Name',
            'url' => 'https://example.com/webhook',
            'subscribed_events' => [WebhookEvent::OrderCreated->value],
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified()
        ->assertNoRedirect();
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters' => [['name' => str_repeat('a', 256)], ['name' => 'max']],
    '`url` is required' => [['url' => null], ['url' => 'required']],
    '`url` must be a valid URL' => [['url' => 'not-a-url'], ['url' => 'url']],
    '`subscribed_events` is required' => [['subscribed_events' => []], ['subscribed_events' => 'required']],
]);

it('rejects a subscribed event that is not in the WebhookEvent enum', function () {
    createWebhookEndpointResourceContext('invalid-event');

    Livewire::test(CreateWebhookEndpoint::class)
        ->fillForm([
            'name' => 'Valid Name',
            'url' => 'https://example.com/webhook',
            'subscribed_events' => ['not.a.real.event'],
        ])
        ->call('create')
        ->assertHasFormErrors(['subscribed_events'])
        ->assertNotNotified()
        ->assertNoRedirect();
});

it('creates a webhook endpoint for the current tenant with a generated secret and reveals it once', function () {
    [, $warehouse] = createWebhookEndpointResourceContext('create');

    Livewire::test(CreateWebhookEndpoint::class)
        ->fillForm([
            'name' => 'Order sync',
            'url' => 'https://example.com/webhook',
            'subscribed_events' => [WebhookEvent::OrderCreated->value, WebhookEvent::OrderCancelled->value],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $endpoint = WebhookEndpoint::query()->where('name', 'Order sync')->sole();

    expect($endpoint->warehouse_id)->toBe($warehouse->id)
        ->and($endpoint->subscribed_events)->toBe([WebhookEvent::OrderCreated->value, WebhookEvent::OrderCancelled->value])
        ->and($endpoint->secret)->toStartWith('whsec_');
});

it('round-trips the secret through the encrypted cast without storing it as plaintext', function () {
    [, $warehouse] = createWebhookEndpointResourceContext('secret-roundtrip');

    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'secret' => 'whsec_plaintext-secret-value',
    ]);

    $rawSecret = DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret');

    expect($rawSecret)->not->toBe('whsec_plaintext-secret-value')
        ->and($endpoint->fresh()->secret)->toBe('whsec_plaintext-secret-value');
});

it('regenerates the secret and reveals the new value once', function () {
    [, $warehouse] = createWebhookEndpointResourceContext('regenerate');

    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'secret' => 'whsec_original-secret',
    ]);

    Livewire::test(EditWebhookEndpoint::class, ['record' => $endpoint->getKey()])
        ->callAction('regenerateSecret')
        ->assertNotified();

    $endpoint->refresh();

    expect($endpoint->secret)->not->toBe('whsec_original-secret')
        ->and($endpoint->secret)->toStartWith('whsec_');
});
