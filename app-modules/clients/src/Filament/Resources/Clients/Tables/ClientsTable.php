<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Modules\Clients\Filament\Resources\Clients\Columns\BillAddressColumn;
use Modules\Clients\Filament\Resources\Clients\Columns\DeliveryAddressColumn;
use Modules\Clients\Filament\Resources\Clients\Columns\EmailColumn;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                EmailColumn::make(),
                DeliveryAddressColumn::make(),
                BillAddressColumn::make(),
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
