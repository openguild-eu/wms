<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Clients\Filament\Resources\Clients\Pages\CreateClient;
use Modules\Clients\Filament\Resources\Clients\Pages\EditClient;
use Modules\Clients\Filament\Resources\Clients\Pages\ListClients;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientActivities;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientNotes;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientOrders;
use Modules\Clients\Filament\Resources\Clients\Schemas\ClientForm;
use Modules\Clients\Filament\Resources\Clients\Tables\ClientsTable;
use Modules\Clients\Models\Client;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditClient::class,
            ManageClientOrders::class,
            ManageClientNotes::class,
            ManageClientActivities::class,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
            'orders' => ManageClientOrders::route('/{record}/orders'),
            'notes' => ManageClientNotes::route('/{record}/notes'),
            'history' => ManageClientActivities::route('/{record}/history'),
        ];
    }
}
