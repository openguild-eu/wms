<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Notes\Models\Concerns\HasNotes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseOrder extends Model implements HasActivityLogTitle
{
    use BelongsToWarehouse;
    use HasNotes;
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'status',
        'comments',
        'expected_delivery_date',
        'received_date',
        'generated_year_purchase_order_id',
        'generated_custom_purchase_order_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('purchase_order')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Purchase order {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return (string) ($this->generated_custom_purchase_order_id ?: "Purchase order #{$this->getKey()}");
    }

    public function isFullyScanned(): bool
    {
        return $this->products()->where('scanned', false)->doesntExist();
    }

    public function canTransitionTo(PurchaseOrderStatus $target): bool
    {
        return in_array($target, $this->status->allowedNextStatuses(), true);
    }

    /**
     * Deferral-eligible incoming quantity for a product, grouped by expected
     * delivery date. Shared by OrderStatusTransitionService::calculateReservedQuantity()
     * and the "incoming stock" UI on the product/stock side.
     *
     * @return Collection<int, PurchaseOrderIncomingBatch>
     */
    public static function incomingBatchesForProduct(int $productId): Collection
    {
        return DB::table('purchase_orders_products')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_orders_products.purchase_order_id')
            ->where('purchase_orders_products.product_id', $productId)
            ->whereIn('purchase_orders.status', [
                PurchaseOrderStatus::Purchased->value,
                PurchaseOrderStatus::Received->value,
                PurchaseOrderStatus::Scanned->value,
            ])
            ->select('purchase_orders.expected_delivery_date')
            ->get()
            ->groupBy('expected_delivery_date')
            ->map(fn (Collection $rows): int => $rows->count())
            ->sortKeys()
            ->map(fn (int $qty, string $date): PurchaseOrderIncomingBatch => new PurchaseOrderIncomingBatch($date, $qty))
            ->values();
    }

    /**
     * @return array{filterableAttributes: list<string>, sortableAttributes: list<string>, searchableAttributes: list<string>}
     */
    public static function getSearchableSettings(): array
    {
        return [
            'filterableAttributes' => [
                'warehouse_id',
                'status',
            ],
            'sortableAttributes' => [
                'created_at',
                'updated_at',
                'expected_delivery_date',
            ],
            'searchableAttributes' => [
                'generated_custom_purchase_order_id',
                'comments',
            ],
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PurchaseOrderProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(PurchaseOrderProduct::class)->orderBy('barcode');
    }

    /**
     * @return HasMany<PurchaseOrderFailedProduct, $this>
     */
    public function failedProducts(): HasMany
    {
        return $this->hasMany(PurchaseOrderFailedProduct::class);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'generated_custom_purchase_order_id' => $this->generated_custom_purchase_order_id,
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'status' => $this->status->value,
            'comments' => $this->comments,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date',
            'received_date' => 'date',
            'status' => PurchaseOrderStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
