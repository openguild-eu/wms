<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Orders\Filament\Resources\Orders\Pages\CreateOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use Modules\Orders\Filament\Resources\Orders\Pages\ManageOrderActivities;
use Modules\Orders\Filament\Resources\Orders\Pages\ManageOrderNotes;
use Modules\Orders\Filament\Resources\Orders\Schemas\OrderForm;
use Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use Modules\Orders\Models\Order;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditOrder::class,
            ManageOrderNotes::class,
            ManageOrderActivities::class,
        ]);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->orderStatus?->canDeleteOrder() ?? true;
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderProductRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
            'notes' => ManageOrderNotes::route('/{record}/notes'),
            'history' => ManageOrderActivities::route('/{record}/history'),
        ];
    }
}
