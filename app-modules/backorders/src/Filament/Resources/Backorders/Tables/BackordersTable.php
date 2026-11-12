<?php

declare(strict_types=1);

namespace Modules\Backorders\Filament\Resources\Backorders\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;

class BackordersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.generated_custom_order_id')
                    ->label(__('Order'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable(),
                TextColumn::make('quantity_ordered')
                    ->label(__('Ordered'))
                    ->sortable(),
                TextColumn::make('quantity_fulfilled')
                    ->label(__('Fulfilled'))
                    ->sortable(),
                TextColumn::make('quantity_remaining')
                    ->label(__('Remaining')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Backordered since'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label(__('Resolved'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'open' => __('Open'),
                        'partially_fulfilled' => __('Partially fulfilled'),
                        'fulfilled' => __('Fulfilled'),
                        'cancelled' => __('Cancelled'),
                    ]),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->visible(fn (Backorder $record): bool => ! in_array($record->status, [BackorderStatus::Fulfilled, BackorderStatus::Cancelled], true))
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (Backorder $record): void {
                        $record->update([
                            'status' => BackorderStatus::Cancelled,
                            'resolved_at' => now(),
                        ]);
                    }),
            ]);
    }
}
