<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Exceptions\WebhookDeliveryFailedException;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DispatchWebhookDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The maximum number of characters stored for a response/error body.
     */
    private const RESPONSE_BODY_LIMIT = 2000;

    /**
     * The HTTP request timeout, in seconds.
     */
    private const REQUEST_TIMEOUT = 10;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * The delays (in seconds) to wait before each retry attempt:
     * 1 minute, 5 minutes, 30 minutes, 2 hours, 24 hours.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 86400];
    }

    public function handle(): void
    {
        $delivery = $this->delivery->fresh();

        if (! $delivery || $delivery->status !== WebhookDeliveryStatus::Pending) {
            return;
        }

        $endpoint = $delivery->webhookEndpoint;

        if (! $endpoint) {
            return;
        }

        $rawBody = (string) json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", (string) $endpoint->secret);

        try {
            $response = Http::withBody($rawBody, 'application/json')
                ->withHeaders([
                    'X-WMS-Signature' => "t={$timestamp},v1={$signature}",
                ])
                ->timeout(self::REQUEST_TIMEOUT)
                ->post($endpoint->url);
        } catch (ConnectionException $exception) {
            $this->recordFailedAttempt($delivery, null, $exception->getMessage());

            throw $exception;
        }

        if ($response->successful()) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Success,
                'response_code' => $response->status(),
                'response_body' => Str::limit($response->body(), self::RESPONSE_BODY_LIMIT, ''),
            ])->save();

            return;
        }

        $this->recordFailedAttempt($delivery, $response->status(), $response->body());

        throw new WebhookDeliveryFailedException(
            "Webhook delivery #{$delivery->getKey()} failed with response status {$response->status()}."
        );
    }

    /**
     * Mark the delivery as exhausted once the queue gives up retrying it.
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = $this->delivery->fresh();

        if (! $delivery || $delivery->status !== WebhookDeliveryStatus::Pending) {
            return;
        }

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'next_retry_at' => null,
        ])->save();
    }

    /**
     * Record a failed delivery attempt and, if retries remain, the time the
     * next one is expected to run. The delivery's status is intentionally
     * left as Pending here - it only becomes terminal (Failed) once the
     * queue has exhausted all attempts and calls failed() above.
     */
    private function recordFailedAttempt(WebhookDelivery $delivery, ?int $responseCode, ?string $responseBody): void
    {
        $attempts = $delivery->attempts + 1;
        $willRetry = $attempts < $this->tries;

        $delivery->forceFill([
            'attempts' => $attempts,
            'response_code' => $responseCode,
            'response_body' => $responseBody !== null
                ? Str::limit($responseBody, self::RESPONSE_BODY_LIMIT, '')
                : null,
            'next_retry_at' => $willRetry
                ? now()->addSeconds($this->backoff()[$attempts - 1])
                : null,
        ])->save();
    }
}
