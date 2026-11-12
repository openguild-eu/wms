<?php

declare(strict_types=1);

namespace Modules\Backorders\Filament\Resources\Backorders;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Backorders\Filament\Resources\Backorders\Pages\ListBackorders;
use Modules\Backorders\Filament\Resources\Backorders\Tables\BackordersTable;
use Modules\Backorders\Models\Backorder;

class BackorderResource extends Resource
{
    protected static ?string $model = Backorder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return BackordersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackorders::route('/'),
        ];
    }
}
