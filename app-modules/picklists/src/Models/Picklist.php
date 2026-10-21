<?php

declare(strict_types=1);

namespace Modules\Picklists\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Notes\Models\Concerns\HasNotes;
use Modules\Orders\Models\Order;
use Modules\Products\Models\StockLocation;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Picklist extends Model implements HasActivityLogTitle
{
    use BelongsToWarehouse;
    use HasNotes;
    use LogsActivity;

    protected $fillable = ['id', 'order_id', 'warehouse_id', 'completed', 'comments', 'generated_custom_picklist_id', 'back_order'];

    public function productsQuantity(): int
    {
        return (int) $this->hasMany(PicklistProduct::class)->sum('scanned');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PicklistProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(PicklistProduct::class)
            ->with('stockLocation')
            ->orderByRaw('stock_location_id is null')
            ->orderBy(
                StockLocation::query()
                    ->select('rank')
                    ->whereColumn('id', 'picklists_products.stock_location_id')
            )
            ->orderBy('barcode');
    }

    /**
     * @return HasMany<PicklistFailedProduct, $this>
     */
    public function failedProducts(): HasMany
    {
        return $this->hasMany(PicklistFailedProduct::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function productsCombined(): array
    {
        $products = $this->hasMany(PicklistProduct::class)->orderBy('barcode')->get();

        $array = [];
        foreach ($products as $product) {
            $barcode = (string) $product->barcode;

            if (isset($array[$barcode])) {
                $array[$barcode]['quantity'] = (int) $array[$barcode]['quantity'] + 1;
                if ($product->scanned) {
                    $array[$barcode]['total_quantity_scanned'] = (int) $array[$barcode]['total_quantity_scanned'] + 1;
                }
            } else {
                $array[$barcode] = $product->toArray();
                $array[$barcode]['quantity'] = 1;
                $array[$barcode]['total_quantity_scanned'] = $product->scanned ? 1 : 0;
            }
        }

        return $array;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('picklist')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Picklist {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return (string) ($this->generated_custom_picklist_id ?: "Picklist #{$this->getKey()}");
    }
}
