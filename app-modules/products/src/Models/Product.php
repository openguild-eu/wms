<?php

declare(strict_types=1);

namespace Modules\Products\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Brands\Models\Brand;
use Modules\Notes\Models\Concerns\HasNotes;
use Modules\Settings\Models\Attribute;
use Modules\Settings\Models\VatRate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model implements HasActivityLogTitle
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
        'reference_code',
        'price',
        'product_code',
        'stock_unlimited',
        'barcode',
        'name',
        'weight',
        'height',
        'length',
        'hs_code',
        'country_of_origin',
        'description',
        'vat_rate_id',
        'brand_id',
        'product_category_id',
        'image',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Product {$eventName}");
    }

    public function getActivityLogTitle(): string
    {
        return (string) ($this->name ?: $this->reference_code ?: "Product #{$this->getKey()}");
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return HasMany<ProductAttribute, $this>
     */
    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * @return BelongsToMany<Attribute, $this, Pivot, 'pivot'>
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')->withTimestamps();
    }

    /**
     * @return HasOne<StockProduct, $this>
     */
    public function stockProduct(): HasOne
    {
        return $this->hasOne(StockProduct::class);
    }

    /**
     * @return BelongsToMany<StockLocation, $this, Pivot, 'pivot'>
     */
    public function stockLocations(): BelongsToMany
    {
        return $this->belongsToMany(StockLocation::class, 'stock_location_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Activity log entries for this product's `StockProduct` row, surfaced on
     * the "Stock History" tab. `StockProduct`'s own changes are logged under
     * `subject_type = StockProduct`, not `Product`, so this is a distinct
     * relation from `activitiesAsSubject()`.
     *
     * @return HasManyThrough<Activity, StockProduct, $this>
     */
    public function stockActivities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Activity::class,
            StockProduct::class,
            'product_id',
            'subject_id',
            'id',
            'id',
        )->where('subject_type', StockProduct::class);
    }
}
