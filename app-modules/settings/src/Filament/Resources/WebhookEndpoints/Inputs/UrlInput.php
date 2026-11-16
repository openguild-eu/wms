<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs;

use Filament\Forms\Components\TextInput;

class UrlInput
{
    public static function make(): TextInput
    {
        return TextInput::make('url')
            ->label(__('URL'))
            ->required()
            ->url()
            ->maxLength(255);
    }
}
