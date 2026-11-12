<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource as BaseActivityLogResource;
use App\Models\ApiKey;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Backorders\Models\Backorder;
use Modules\Brands\Models\Brand;
use Modules\Clients\Models\Client;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderStatus;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockLocation;
use Modules\Products\Models\StockProduct;
use Modules\Settings\Models\AttributeGroup;
use Modules\Settings\Models\VatRate;
use Modules\Users\Models\User;

class ActivityLogResource extends BaseActivityLogResource
{
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Warehouse) {
            return $query;
        }

        return static::scopeEloquentQueryToTenant($query, $tenant);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        if (! $tenant instanceof Warehouse) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($tenant): void {
            $query->whereHasMorph('subject', [
                ApiKey::class,
                AttributeGroup::class,
                Backorder::class,
                Brand::class,
                Client::class,
                Order::class,
                OrderStatus::class,
                Picklist::class,
                Product::class,
                PurchaseOrder::class,
                StockLocation::class,
                VatRate::class,
            ], function (Builder $subjectQuery) use ($tenant): void {
                $subjectQuery->whereBelongsTo($tenant, 'warehouse');
            })
                ->orWhereHasMorph('subject', [StockProduct::class], function (Builder $stockProductQuery) use ($tenant): void {
                    $stockProductQuery->whereHas('product', function (Builder $productQuery) use ($tenant): void {
                        $productQuery->whereBelongsTo($tenant, 'warehouse');
                    });
                })
                ->orWhereHasMorph('subject', [AttributeGroup::class], function (Builder $attributeGroupQuery): void {
                    $attributeGroupQuery->whereNull('warehouse_id');
                })
                ->orWhereHasMorph('subject', [Warehouse::class], function (Builder $warehouseQuery) use ($tenant): void {
                    $warehouseQuery->whereKey($tenant->getKey());
                })
                ->orWhereHasMorph('subject', [User::class], function (Builder $userQuery) use ($tenant): void {
                    $userQuery->whereHas('warehouses', function (Builder $warehousesQuery) use ($tenant): void {
                        $warehousesQuery->whereKey($tenant->getKey());
                    });
                });
        });
    }
}
