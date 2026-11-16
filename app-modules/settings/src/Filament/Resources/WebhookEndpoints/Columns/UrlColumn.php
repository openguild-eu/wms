<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Columns;

use Filament\Tables\Columns\TextColumn;

class UrlColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('url')
            ->label(__('URL'))
            ->searchable()
            ->limit(50);
    }
}
