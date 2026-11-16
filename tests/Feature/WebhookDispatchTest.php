<?php

declare(strict_types=1);

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\WebhookDeliveryFailedException;
use App\Jobs\DispatchWebhookDelivery;
use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function createWebhookDispatchWarehouse(string $suffix): Warehouse
{
    $subdomain = Subdomain::query()->create([
        'subdomain' => "webhook-dispatch-{$suffix}",
        'name' => "Webhook Dispatch {$suffix}",
    ]);

    return Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => "Webhook Dispatch Warehouse {$suffix}",
        'currency' => 'EUR',
    ]);
}

it('only creates deliveries for active endpoints subscribed to the event in the given warehouse', function () {
    Queue::fake();

    $warehouse = createWebhookDispatchWarehouse('scope');
    $otherWarehouse = createWebhookDispatchWarehouse('scope-other');

    $matching = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'is_active' => false,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCancelled->value],
    ]);

    WebhookEndpoint::factory()->create([
        'warehouse_id' => $otherWarehouse->id,
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    app(WebhookDispatcher::class)->dispatch($warehouse, WebhookEvent::OrderCreated, ['order_id' => 123]);

    expect(WebhookDelivery::query()->count())->toBe(1);

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->webhook_endpoint_id)->toBe($matching->id)
        ->and($delivery->event)->toBe(WebhookEvent::OrderCreated->value)
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(0)
        ->and($delivery->payload)->toMatchArray([
            'event' => WebhookEvent::OrderCreated->value,
            'data' => ['order_id' => 123],
        ]);

    Queue::assertPushed(
        DispatchWebhookDelivery::class,
        fn (DispatchWebhookDelivery $job): bool => $job->delivery->is($delivery)
    );
});

it('delivers successfully, stores the response, and signs the payload with the expected HMAC header', function () {
    Http::fake([
        'https://example.test/webhook' => Http::response('{"ok":true}', 200),
    ]);

    $warehouse = createWebhookDispatchWarehouse('success');
    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'url' => 'https://example.test/webhook',
        'secret' => 'whsec_test-secret-value',
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => WebhookEvent::OrderCreated->value,
        'payload' => [
            'event' => WebhookEvent::OrderCreated->value,
            'timestamp' => '2026-11-16T10:00:00+00:00',
            'data' => ['order_id' => 123],
        ],
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    (new DispatchWebhookDelivery($delivery))->handle();

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Success)
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->response_body)->toBe('{"ok":true}');

    Http::assertSent(function (Request $request) use ($delivery): bool {
        $rawBody = $request->body();
        $signatureHeader = $request->header('X-WMS-Signature')[0] ?? '';

        if (! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $signatureHeader, $matches)) {
            return false;
        }

        [, $timestamp, $signature] = $matches;

        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'whsec_test-secret-value');

        return $request->url() === 'https://example.test/webhook'
            && $rawBody === json_encode($delivery->payload)
            && $request->hasHeader('Content-Type', 'application/json')
            && hash_equals($expectedSignature, $signature);
    });
});

it('increments attempts and stays pending with a scheduled retry while attempts remain', function () {
    Http::fake([
        'https://example.test/webhook-fail' => Http::response('Internal Server Error', 500),
    ]);

    $warehouse = createWebhookDispatchWarehouse('retry');
    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'url' => 'https://example.test/webhook-fail',
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $job = new DispatchWebhookDelivery($delivery);

    expect(fn () => $job->handle())->toThrow(WebhookDeliveryFailedException::class);

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_code)->toBe(500)
        ->and($delivery->response_body)->toBe('Internal Server Error')
        ->and($delivery->next_retry_at)->not->toBeNull()
        ->and($delivery->next_retry_at->diffInSeconds(now(), true))->toBeLessThanOrEqual(65);
});

it('records a connection exception as a failed attempt without a response code', function () {
    Http::fake(function (): never {
        throw new ConnectionException('Connection timed out');
    });

    $warehouse = createWebhookDispatchWarehouse('connection-error');
    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'url' => 'https://example.test/webhook-unreachable',
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $job = new DispatchWebhookDelivery($delivery);

    expect(fn () => $job->handle())->toThrow(ConnectionException::class);

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_code)->toBeNull()
        ->and($delivery->response_body)->toBe('Connection timed out')
        ->and($delivery->next_retry_at)->not->toBeNull();
});

it('marks the delivery as terminally failed once the queue exhausts all retry attempts', function () {
    Http::fake([
        'https://example.test/webhook-dead-letter' => Http::response('Internal Server Error', 500),
    ]);

    $warehouse = createWebhookDispatchWarehouse('exhausted');
    $endpoint = WebhookEndpoint::factory()->create([
        'warehouse_id' => $warehouse->id,
        'url' => 'https://example.test/webhook-dead-letter',
        'is_active' => true,
        'subscribed_events' => [WebhookEvent::OrderCreated->value],
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $job = new DispatchWebhookDelivery($delivery);

    $lastException = null;

    // Simulate every attempt the queue will make (tries = 5): each one
    // should keep the delivery Pending and increment attempts, with a
    // scheduled retry for every attempt except the last.
    for ($attempt = 1; $attempt <= $job->tries; $attempt++) {
        try {
            $job->handle();
        } catch (Throwable $exception) {
            $lastException = $exception;
        }

        $delivery->refresh();

        expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
            ->and($delivery->attempts)->toBe($attempt);

        if ($attempt < $job->tries) {
            expect($delivery->next_retry_at)->not->toBeNull();
        } else {
            expect($delivery->next_retry_at)->toBeNull();
        }
    }

    // Once the queue has exhausted $tries attempts, it calls failed().
    $job->failed($lastException);

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe($job->tries)
        ->and($delivery->next_retry_at)->toBeNull();
});
