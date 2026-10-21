<?php

declare(strict_types=1);

namespace Modules\Clients\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Notes\Models\Concerns\HasNotes;
use Modules\Orders\Models\Order;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Client extends Model implements HasActivityLogTitle
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
        'active',
        'email',
        'vat_number',
        'coc_number',
        'debtor_number',
        'iban_number',
        'comments',
        'company',
        'bill_client_address_id',
        'delivery_client_address_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('client')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Client {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return (string) ($this->company ?: $this->email ?: "Client #{$this->getKey()}");
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<ClientAddresses, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddresses::class);
    }

    /**
     * @return BelongsTo<ClientAddresses, $this>
     */
    public function clientDeliveryAddress(): BelongsTo
    {
        return $this->belongsTo(ClientAddresses::class, 'delivery_client_address_id');
    }

    /**
     * @return BelongsTo<ClientAddresses, $this>
     */
    public function clientBillAddress(): BelongsTo
    {
        return $this->belongsTo(ClientAddresses::class, 'bill_client_address_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
