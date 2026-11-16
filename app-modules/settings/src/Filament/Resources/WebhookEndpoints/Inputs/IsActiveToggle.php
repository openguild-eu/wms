<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs;

use Filament\Forms\Components\Toggle;

class IsActiveToggle
{
    public static function make(): Toggle
    {
        return Toggle::make('is_active')
            ->inline(false)
            ->default(true)
            ->required();
    }
}
