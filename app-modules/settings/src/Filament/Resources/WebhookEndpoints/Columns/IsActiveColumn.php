<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Columns;

use Filament\Tables\Columns\IconColumn;

class IsActiveColumn
{
    public static function make(): IconColumn
    {
        return IconColumn::make('is_active')
            ->boolean()
            ->label(__('Active'));
    }
}
