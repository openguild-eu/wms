<?php

declare(strict_types=1);

namespace Modules\Products\Filament\Resources\Products;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProductStock;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Filament\Resources\Products\Pages\ManageProductHistory;
use Modules\Products\Filament\Resources\Products\Pages\ManageProductNotes;
use Modules\Products\Filament\Resources\Products\RelationManagers\StockLocationsRelationManager;
use Modules\Products\Filament\Resources\Products\Schemas\ProductForm;
use Modules\Products\Filament\Resources\Products\Tables\ProductsTable;
use Modules\Products\Models\Product;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditProduct::class,
            EditProductStock::class,
            ManageProductNotes::class,
            ManageProductHistory::class,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StockLocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
            'edit-stock' => EditProductStock::route('/{record}/edit/stock'),
            'notes' => ManageProductNotes::route('/{record}/notes'),
            'history' => ManageProductHistory::route('/{record}/history'),
        ];
    }
}
