<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients;

use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Clients\Filament\Resources\Clients\Pages\CreateClient;
use Modules\Clients\Filament\Resources\Clients\Pages\EditClient;
use Modules\Clients\Filament\Resources\Clients\Pages\ListClients;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientActivities;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientNotes;
use Modules\Clients\Filament\Resources\Clients\Pages\ManageClientOrders;
use Modules\Clients\Filament\Resources\Clients\Pages\ViewClient;
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
            ViewClient::class,
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('General Information'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('company')
                            ->label(__('Company')),
                        TextEntry::make('email')
                            ->label(__('Email')),
                        IconEntry::make('active')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('vat_number')
                            ->label(__('VAT Number'))
                            ->placeholder(__('Not set')),
                        TextEntry::make('coc_number')
                            ->label(__('CoC Number'))
                            ->placeholder(__('Not set')),
                        TextEntry::make('debtor_number')
                            ->label(__('Debtor Number'))
                            ->placeholder(__('Not set')),
                        TextEntry::make('iban_number')
                            ->label(__('IBAN Number'))
                            ->placeholder(__('Not set')),
                        TextEntry::make('comments')
                            ->label(__('Comments'))
                            ->placeholder(__('Not set'))
                            ->columnSpanFull(),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make(__('Delivery Address'))
                            ->schema(self::addressEntries('clientDeliveryAddress')),
                        Section::make(__('Billing Address'))
                            ->schema(self::addressEntries('clientBillAddress')),
                    ]),
            ]);
    }

    /**
     * @return array<TextEntry>
     */
    protected static function addressEntries(string $relationship): array
    {
        return [
            TextEntry::make("{$relationship}.name")
                ->label(__('Name'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.company")
                ->label(__('Company'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.address")
                ->label(__('Address'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.zipcode")
                ->label(__('Zipcode'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.city")
                ->label(__('City'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.region")
                ->label(__('Region'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.country")
                ->label(__('Country'))
                ->placeholder(__('Not set')),
            TextEntry::make("{$relationship}.telephone_number")
                ->label(__('Telephone Number'))
                ->placeholder(__('Not set')),
        ];
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
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
            'orders' => ManageClientOrders::route('/{record}/orders'),
            'notes' => ManageClientNotes::route('/{record}/notes'),
            'history' => ManageClientActivities::route('/{record}/history'),
        ];
    }
}
