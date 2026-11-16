<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs;

use App\Enums\WebhookEvent;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Support\Arr;

class SubscribedEventsInput
{
    public static function make(): Select
    {
        return Select::make('subscribed_events')
            ->label(__('Subscribed events'))
            ->multiple()
            ->options(collect(WebhookEvent::cases())->mapWithKeys(
                fn (WebhookEvent $event): array => [$event->value => $event->getLabel()],
            ))
            ->required()
            ->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $validEvents = Arr::pluck(WebhookEvent::cases(), 'value');

                foreach ((array) $value as $event) {
                    if (! in_array($event, $validEvents, true)) {
                        $fail(__('The selected :attribute is invalid.', ['attribute' => __('subscribed events')]));

                        return;
                    }
                }
            });
    }
}
