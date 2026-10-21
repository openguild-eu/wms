<?php

declare(strict_types=1);

namespace Modules\Picklists\Filament\Resources\Picklists;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Picklists\Filament\Infolists\Components\FailedProductsTable;
use Modules\Picklists\Filament\Infolists\Components\ProductsTable;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ListPicklists;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ManagePicklistActivities;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ManagePicklistNotes;
use Modules\Picklists\Filament\Resources\Picklists\Pages\ViewPicklist;
use Modules\Picklists\Filament\Resources\Picklists\Schemas\PicklistForm;
use Modules\Picklists\Filament\Resources\Picklists\Tables\PicklistsTable;
use Modules\Picklists\Models\Picklist;

class PicklistResource extends Resource
{
    protected static ?string $model = Picklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewPicklist::class,
            ManagePicklistNotes::class,
            ManagePicklistActivities::class,
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PicklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PicklistsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Picklist')
                    ->schema([
                        TextEntry::make('generated_custom_picklist_id')
                            ->label('ID'),
                        TextEntry::make('products_count')
                            ->label('Products')
                            ->state(fn (Picklist $record): int => $record->products->count()),
                        TextEntry::make('scanned_count')
                            ->label('Scanned')
                            ->state(fn (Picklist $record): string => $record->products->where('scanned', true)->count().' / '.$record->products->count()),
                        TextEntry::make('completed')
                            ->formatStateUsing(fn (bool|int|null $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('comments'),
                    ])
                    ->columns(2),
                Section::make('Order Summary')
                    ->schema([
                        TextEntry::make('order.generated_custom_order_id')
                            ->label('Order'),
                        TextEntry::make('order.orderStatus.name')
                            ->label('Order status'),
                        TextEntry::make('order.client.email')
                            ->label('Client email'),
                        TextEntry::make('order.delivery_name'),
                        TextEntry::make('order.delivery_city'),
                        TextEntry::make('order.delivery_country'),
                    ])
                    ->columns(3),
                Section::make('Failed Scans')
                    ->columnSpan('full')
                    ->schema([
                        FailedProductsTable::make('failedProducts')
                            ->default([]),
                    ]),
                Section::make('Products')
                    ->columnSpan('full')
                    ->schema([
                        ProductsTable::make('products')
                            ->default([]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'order.orderStatus',
                'order.client',
                'products',
                'failedProducts',
            ])
            ->withCount([
                'products',
                'products as scanned_products_count' => fn ($query) => $query->where('scanned', true),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPicklists::route('/'),
            'view' => ViewPicklist::route('/{record}'),
            'notes' => ManagePicklistNotes::route('/{record}/notes'),
            'history' => ManagePicklistActivities::route('/{record}/history'),
        ];
    }
}
