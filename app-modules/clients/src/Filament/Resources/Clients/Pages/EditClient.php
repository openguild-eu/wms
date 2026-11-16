<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Clients\Filament\Resources\Clients\ClientResource;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $navigationLabel = 'General';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return null;
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-relation-manager', relationship: 'addresses');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
