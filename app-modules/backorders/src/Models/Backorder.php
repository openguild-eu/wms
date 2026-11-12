<?php

declare(strict_types=1);

namespace Modules\Backorders\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Backorders\Database\Factories\BackorderFactory;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\Product;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Backorder extends Model implements HasActivityLogTitle
{
    use BelongsToWarehouse;

    /** @use HasFactory<BackorderFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'order_id',
        'order_product_id',
        'product_id',
        'intended_order_statuses_id',
        'quantity_ordered',
        'quantity_fulfilled',
        'status',
        'resolved_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('backorder')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Backorder {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return "Backorder for {$this->product?->name} (Order #{$this->order_id})";
    }

    public function getActivityLogParent(): ?Order
    {
        return $this->order;
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderProduct, $this>
     */
    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<OrderStatus, $this>
     */
    public function intendedOrderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'intended_order_statuses_id');
    }

    /**
     * @return Attribute<int, never>
     */
    protected function quantityRemaining(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, $this->quantity_ordered - $this->quantity_fulfilled),
        );
    }

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_fulfilled' => 'integer',
            'status' => BackorderStatus::class,
            'resolved_at' => 'datetime',
        ];
    }
}
