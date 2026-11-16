<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Pages;

use App\Models\WebhookEndpoint;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Modules\Settings\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;

class EditWebhookEndpoint extends EditRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateSecret')
                ->label(__('Regenerate secret'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('The current secret will stop working immediately. Any delivery integration using it must be updated.'))
                ->action(function (WebhookEndpoint $record): void {
                    $secret = 'whsec_'.Str::random(40);

                    $record->update(['secret' => $secret]);

                    Notification::make()
                        ->title('Secret regenerated')
                        ->body("Copy this secret now: {$secret}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
