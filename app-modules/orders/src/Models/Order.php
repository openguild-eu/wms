<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clients\Models\Client;
use Modules\Notes\Models\Concerns\HasNotes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model implements HasActivityLogTitle
{
    use BelongsToWarehouse;
    use HasNotes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'client_id',
        'order_statuses_id',
        'generated_year_order_id',
        'generated_custom_order_id',
        'discount',
        'custom_order_id',
        'invoice_name',
        'invoice_address',
        'invoice_zipcode',
        'invoice_region',
        'invoice_city',
        'invoice_country',
        'delivery_name',
        'delivery_address',
        'delivery_zipcode',
        'delivery_region',
        'delivery_city',
        'delivery_country',
        'telephone_number',
        'email',
        'comments',
        'delivery_date',
    ];

    /**
     * @return array{filterableAttributes: list<string>, sortableAttributes: list<string>, searchableAttributes: list<string>}
     */
    public static function getSearchableSettings(): array
    {
        return [
            'filterableAttributes' => [
                'warehouse_id',
                'client_id',
                'order_statuses_id',
                'completed',
                'picked',
                'cancelled',
                'delivered',
                'on_hold',
            ],
            'sortableAttributes' => [
                'created_at',
                'updated_at',
            ],
            'searchableAttributes' => [
                'generated_custom_order_id',
                'custom_order_id',
                'invoice_name',
                'delivery_name',
                'email',
                'comments',
            ],
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('order')
            ->logOnly([
                'warehouse_id',
                'client_id',
                'order_statuses_id',
                'generated_year_order_id',
                'generated_custom_order_id',
                'discount',
                'custom_order_id',
                'completed',
                'picked',
                'cancelled',
                'delivered',
                'on_hold',
                'invoice_name',
                'invoice_address',
                'invoice_zipcode',
                'invoice_region',
                'invoice_city',
                'invoice_country',
                'delivery_name',
                'delivery_address',
                'delivery_zipcode',
                'delivery_region',
                'delivery_city',
                'delivery_country',
                'telephone_number',
                'email',
                'comments',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Order {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return (string) ($this->generated_custom_order_id ?: $this->custom_order_id ?: "Order #{$this->getKey()}");
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'client_id' => $this->client_id,
            'order_statuses_id' => $this->order_statuses_id,
            'generated_custom_order_id' => $this->generated_custom_order_id,
            'custom_order_id' => $this->custom_order_id,
            'invoice_name' => $this->invoice_name,
            'delivery_name' => $this->delivery_name,
            'email' => $this->email,
            'completed' => (bool) $this->completed,
            'picked' => (bool) $this->picked,
            'cancelled' => (bool) $this->cancelled,
            'delivered' => (bool) $this->delivered,
            'on_hold' => (bool) $this->on_hold,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<OrderStatus, $this>
     */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_statuses_id');
    }

    /**
     * @return HasMany<OrderProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'picked' => 'boolean',
            'cancelled' => 'boolean',
            'delivered' => 'boolean',
            'on_hold' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
