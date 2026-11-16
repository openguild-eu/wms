<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Columns;

use App\Models\WebhookEndpoint;
use Filament\Tables\Columns\TextColumn;

class SubscribedEventsCountColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('subscribed_events')
            ->label(__('Subscribed events'))
            ->state(fn (WebhookEndpoint $record): int => count($record->subscribed_events ?? []));
    }
}
