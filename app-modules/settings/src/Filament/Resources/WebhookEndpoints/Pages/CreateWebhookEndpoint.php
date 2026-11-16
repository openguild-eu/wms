<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Modules\Settings\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;

class CreateWebhookEndpoint extends CreateRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    protected ?string $plainTextSecret = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            abort(403);
        }

        $secret = 'whsec_'.Str::random(40);
        $this->plainTextSecret = $secret;

        $data['warehouse_id'] = $tenant->getKey();
        $data['secret'] = $secret;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->plainTextSecret) {
            return;
        }

        Notification::make()
            ->title('Webhook endpoint created')
            ->body("Copy this secret now: {$this->plainTextSecret}")
            ->success()
            ->persistent()
            ->send();
    }
}
