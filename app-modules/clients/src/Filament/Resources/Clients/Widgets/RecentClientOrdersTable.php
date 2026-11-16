<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Orders\Filament\Resources\Orders\Columns\GeneratedCustomOrderIdColumn;
use Modules\Orders\Filament\Resources\Orders\Columns\OrderStatusColumn;
use Modules\Orders\Filament\Resources\Orders\Columns\TotalPriceColumn;
use Modules\Orders\Filament\Resources\Orders\Columns\TotalQuantityColumn;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;

class RecentClientOrdersTable extends TableWidget
{
    public ?Client $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent Orders'))
            ->headerActions([
                Action::make('viewAllOrders')
                    ->label(__('View all orders'))
                    ->icon('heroicon-m-arrow-right')
                    ->url(fn (): string => ClientResource::getUrl('orders', ['record' => $this->record])),
            ])
            ->query(
                Order::query()
                    ->where('client_id', $this->record?->getKey())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                GeneratedCustomOrderIdColumn::make(),
                OrderStatusColumn::make(),
                TotalQuantityColumn::make(),
                TotalPriceColumn::make(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
