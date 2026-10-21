<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\PurchaseOrders;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Orders\Filament\Infolists\Components\PurchaseOrderFailedProductsTable;
use Modules\Orders\Filament\Infolists\Components\PurchaseOrderProductsTable;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\CommentsEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\CreatedAtEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\ExpectedDeliveryDateEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\GeneratedCustomPurchaseOrderIdEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\ProductsCountEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\ReceivedDateEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\ScannedCountEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Entries\StatusEntry;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrderActivities;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrderNotes;
use Modules\Orders\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use Modules\Orders\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Modules\Orders\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use Modules\Orders\Models\PurchaseOrder;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewPurchaseOrder::class,
            EditPurchaseOrder::class,
            ManagePurchaseOrderNotes::class,
            ManagePurchaseOrderActivities::class,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Purchase Order'))
                    ->schema([
                        GeneratedCustomPurchaseOrderIdEntry::make(),
                        StatusEntry::make(),
                        ProductsCountEntry::make(),
                        ScannedCountEntry::make(),
                        ExpectedDeliveryDateEntry::make(),
                        ReceivedDateEntry::make(),
                        CreatedAtEntry::make(),
                        CommentsEntry::make(),
                    ])
                    ->columns(2),
                Section::make(__('Failed Scans'))
                    ->columnSpan('full')
                    ->schema([
                        PurchaseOrderFailedProductsTable::make('failedProducts'),
                    ]),
                Section::make(__('Products'))
                    ->columnSpan('full')
                    ->schema([
                        PurchaseOrderProductsTable::make('products'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'products',
                'failedProducts',
            ])
            ->withCount([
                'products',
                'products as scanned_products_count' => fn ($query) => $query->where('scanned', true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
            'notes' => ManagePurchaseOrderNotes::route('/{record}/notes'),
            'history' => ManagePurchaseOrderActivities::route('/{record}/history'),
        ];
    }
}
