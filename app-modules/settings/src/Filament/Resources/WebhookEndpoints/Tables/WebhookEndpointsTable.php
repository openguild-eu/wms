<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Columns\CreatedAtColumn;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Columns\IsActiveColumn;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Columns\NameColumn;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Columns\SubscribedEventsCountColumn;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Columns\UrlColumn;

class WebhookEndpointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                NameColumn::make(),
                UrlColumn::make(),
                IsActiveColumn::make(),
                SubscribedEventsCountColumn::make(),
                CreatedAtColumn::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
