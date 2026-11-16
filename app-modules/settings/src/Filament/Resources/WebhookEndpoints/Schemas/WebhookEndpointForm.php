<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Schemas;

use Filament\Schemas\Schema;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs\IsActiveToggle;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs\NameInput;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs\SubscribedEventsInput;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Inputs\UrlInput;

class WebhookEndpointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                NameInput::make(),
                UrlInput::make(),
                SubscribedEventsInput::make(),
                IsActiveToggle::make(),
            ]);
    }
}
