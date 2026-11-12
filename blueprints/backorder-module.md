# Blueprint: Backorder Module

## Goal

A backorder occurs when a customer order is placed for a product that doesn't
currently have enough free stock to fulfil it. Instead of rejecting the sale
(today's behavior), the WMS must register the unfulfilled demand and hold the
order until new inventory arrives via a processed Purchase Order, then
automatically release the order back into normal processing once covered.

## Resolved Business Rules (confirmed with the user — do not re-derive)

1. **Scope: universal, not opt-in.** Every order creation that can't get
   enough stock to eventually build a picklist is backordered automatically.
   There is no per-product "backorderable" flag. This **replaces** the
   current hard-rejection (`InsufficientStockException`, HTTP 422) entirely.
2. **Fulfillment trigger: automatic, FIFO by order creation date.** When a
   Purchase Order is marked Processed (stock physically increases), waiting
   backorders for the affected products are fulfilled automatically, oldest
   backorder first. No manual "release" step.
3. **Partial fulfillment is allowed.** A backorder line tracks
   `quantity_fulfilled` against `quantity_ordered` and can be topped up across
   multiple restocks. The **order** itself is only released from hold once
   **all** of its backorder lines are fully fulfilled (an order needs every
   line coverable to generate one complete picklist).
4. **Cancelling a backorder does not touch the order.** Staff can cancel an
   individual backorder line (status → `Cancelled`) from the admin panel.
   This only stops tracking that unfulfilled demand; the parent order and its
   other lines are left exactly as they are. Staff handle the order
   separately if needed.

## Scope Decisions Made By This Plan (not asked, but consequential — stated explicitly)

- **Accepted race-window limitation (v1):** partial allocation during a
  `fulfillForProduct()` run is only protected against double-allocation
  *within that single run* (via an in-memory running total under one locked
  transaction). It is not protected against a brand-new order being created
  via `OrderCreationService` in the small window between two separate
  Purchase Order processing events. This matches the fact that the *existing*
  stock-reservation system in this app (`OrderStatusTransitionService::calculateReservedQuantity()`)
  is also a recompute-from-scratch aggregate, not a per-order earmarked
  ledger — this plan does not introduce stronger guarantees than the system
  it integrates with. Do not attempt to "fix" this with extra locking/ledger
  columns; it is an intentional v1 scope boundary.
- **Trigger is `PurchaseOrderProcessingService::markProcessed()` only**, not
  `markPurchased()`/`cancel()`. Those two *can* technically change
  `free_on_stock_quantity` indirectly (by changing deferral eligibility for
  already-reserving orders) without any physical stock arriving, but "holds
  the order until new inventory arrives" is read literally: only an actual
  stock increase triggers backorder fulfillment.
- **No per-product `backorderable` flag** (superseded by rule #1 above) — do
  not add one.
- **No new Backorders → Orders coupling beyond direct method calls.** Modules
  in this app already reference each other directly with no enforced
  boundary (confirmed: `Modules\Orders`, `Modules\Products`, etc. import
  across each other freely); this plan uses plain constructor-injected
  services in the same style as the existing `PurchaseOrderProcessingService`
  → `OrderStatusTransitionService` dependency, not a new event/listener layer.
- **`getSearchableSettings()`/`toSearchableArray()` are deliberately omitted**
  from the `Backorder` model. Verified: `laravel/scout` is not a dependency,
  `config/scout.php` does not exist, and `Searchable` is used nowhere. These
  two methods exist as unenforced, inconsistently-applied cosmetic scaffolding
  on some internal models (`OrderStatus`, `Order`, `OrderProduct`,
  `PurchaseOrder`, `StockLocation`, `Note`) but not on `Product`, `Client`, or
  `StockProduct` — the models closest in kind to `Backorder`. Do not add them.
- **`Relation::enforceMorphMap()` / `Feature::discover()` are not added.**
  Verified via repo-wide grep: neither exists anywhere in this codebase today
  despite being mentioned in `.ai/guidelines/custom-rules.md`. That guideline
  is aspirational/unenforced in practice; follow actual existing convention,
  not the stale doc. Do not introduce either call in `BackordersServiceProvider`.
- **Picklist's pre-existing `back_order` boolean** (`app-modules/picklists`,
  migration `2025_09_26_100450_create_picklist.php:21`) is dead/unused
  (never set true, never read). It is unrelated to this feature. **Do not
  touch it** — leave it exactly as-is. Flag it to the user afterward as a
  candidate for separate cleanup; do not delete without separate approval.

## Source of Truth (already verified — do not re-derive)

### Module convention (`internachi/modular`)

New module at `app-modules/backorders`, namespace `Modules\Backorders\`.
Mirror `app-modules/notes` exactly:

- `app-modules/backorders/composer.json` — PSR-4: `Modules\Backorders\` →
  `src/`, `Modules\Backorders\Tests\` → `tests/`,
  `Modules\Backorders\Database\Factories\` → `database/factories/`,
  `Modules\Backorders\Database\Seeders\` → `database/seeders/`. Provider
  registered via `extra.laravel.providers`. Use `app-modules/notes/composer.json`
  as the literal template (same `require`/`minimum-stability` block).
- Root `composer.json` — add `"modules/backorders": "*"` to `require`,
  alphabetically **before** `"modules/brands"` (line ~22).
- Actual Pest tests live at top-level `tests/Feature/*Test.php`, **not**
  inside the module's own `tests/` dir — that stays `.gitkeep`-only, matching
  every existing module (confirmed via `ActivityLogResourceTest.php` and the
  fact no module's own `tests/` directory has real test files).
- Filament resources are wired via an explicit
  `->discoverResources(in: base_path('app-modules/<module>/src/Filament/Resources'), for: 'Modules\<Module>\Filament\Resources')`
  call per module in `app/Providers/Filament/AdminPanelProvider.php` — there
  is no auto-discovery across all of `app-modules/`. Currently 8 such calls
  exist (brands, clients, notes, orders, picklists, products, settings,
  users). Add a 9th for `backorders`, placed alphabetically **before** the
  `brands` line (b-a-c < b-r-a).
- `Backorders` has no model observers to register, so
  `BackordersServiceProvider::boot()` only needs `loadMigrationsFrom()`
  (mirrors `NotesServiceProvider`).

### Domain models already in the app (integrate with, do not recreate)

- **`Modules\Products\Models\Product`** (table `products`) — `belongsTo`
  `Warehouse`; `hasOne` `StockProduct` via relation name `stockProduct`;
  `stock_unlimited` boolean column skips all stock checks.
- **`Modules\Products\Models\StockProduct`** (table `stock_products`) —
  `on_stock_quantity`, `reserved_quantity`, `reserved_on_picklists`,
  `free_on_stock_quantity` (always `max(0, on_stock - reserved - reserved_on_picklists)`,
  recomputed by `OrderStatusTransitionService::recalculateFreeStock()`).
- **`Modules\Orders\Models\Order`** (table `orders`) — `belongsTo`
  `Warehouse`, `Client`, `OrderStatus`; `hasMany` `OrderProduct` via relation
  name **`products`** (not `orderProducts` — confirmed in
  `OrderStatusTransitionService`: `foreach ($order->products as $orderProduct)`).
  Column `order_statuses_id` (plural table name + `_id`, not `order_status_id`).
- **`Modules\Orders\Models\OrderProduct`** (table `order_products`) —
  `belongsTo` `Order`, `Product`, `VatRate`.
- **`Modules\Orders\Models\OrderStatus`** (table `order_statuses`) —
  `belongsTo` `Warehouse` (**per-warehouse, not global** — migration
  `app-modules/orders/database/migrations/2025_09_03_141234_create_order.php:17-31`).
  Columns: `warehouse_id`, `name`, `color`, `generate_picklist`,
  `reserve_stock`, `reduce_stock`, `concepted`, `completed`, `on_hold`,
  `delivered`, `cancelled` — all booleans, `default(false)`. No
  `is_default`/`sort` column. Seeded per-warehouse in
  `database/seeders/TenantSeeder.php::seedOrderStatuses(Warehouse $warehouse)`
  (~line 405) via a `$statuses` array of assoc arrays +
  `OrderStatus::query()->firstOrCreate(['warehouse_id' => .., 'name' => ..], $status)`.
- **`App\Models\Concerns\BelongsToWarehouse`** trait (`app/Models/Concerns/BelongsToWarehouse.php`) —
  `protected static function bootBelongsToWarehouse(): void { static::addGlobalScope(new WarehouseScope); }`.
  Used by `Order`, `Client`, `Product`. This is what makes Filament's tenant
  scoping work automatically with **no `getEloquentQuery()` override needed**
  on the Resource (confirmed: `OrderResource` has no such override).
- **`app/Services/OrderCreationService.php`** — full current content already
  read; see exact diff below. Key facts:
  - `create()` receives `$data['order_status_id']` as a **caller-supplied**
    value (the API consumer picks the initial status) and, as a deliberate
    **separate write after the creation transaction**,
    `$order->update(['order_statuses_id' => $data['order_status_id']])`.
    The comment above that line (lines 82-88) explains why: `OrderObserver`
    only dispatches `OrderStatusChanged` from its `updated()` hook guarded by
    `wasChanged('order_statuses_id')`, and that listener is
    `ShouldQueueAfterCommit` — it must stay a genuine post-transaction update.
  - `loadAndValidateStock()` (private) currently throws
    `InsufficientStockException` for the **entire request** if any line's
    `quantity > free_on_stock_quantity` (skipping products with
    `stock_unlimited = true`), **before any DB writes**.
- **`App\Exceptions\Api\InsufficientStockException`** — plain `Exception`
  with a `render()` method returning HTTP 422 JSON directly (no
  handler/bootstrap registration elsewhere — self-contained). Only thrown at
  `OrderCreationService::loadAndValidateStock()`. Becomes fully dead once this
  plan lands — **delete the file**.
- **`App\Services\OrderStatusTransitionService`** — full current content
  already read. Key facts:
  - `process(Order $order, ?OrderStatus $previousStatus, ?OrderStatus $newStatus, ?int $causerId = null)`
    is the single entry point invoked by the (unmodified) event pipeline.
    Reacts to flag *transitions* (`generate_picklist`, `reserve_stock`,
    `reduce_stock`, `cancelled`) comparing previous vs. new status.
  - `refreshReservedStockForProductIds(array $productIds): void` (public) —
    for each product id, locks its `StockProduct` row, recomputes
    `reserved_quantity` via `calculateReservedQuantity()` **from scratch**
    (sums demand from every order *currently* in a `reserve_stock = true`
    status for that product, ordered by `delivery_date`, deferring to
    incoming PO batches where applicable), then calls `recalculateFreeStock()`
    and saves. This is a full recompute, not an increment — **a backordered
    order (status `reserve_stock = false`) is completely invisible to this
    calculation**, which is exactly why a backordered order's demand does not
    reduce `free_on_stock_quantity` until it is released into a
    `reserve_stock = true` status.
  - `recalculateFreeStock(StockProduct $stockProduct): void` (public) — pure
    `free_on_stock_quantity = max(0, on_stock - reserved_quantity - reserved_on_picklists)`.
    **Do not modify this method or `calculateReservedQuantity()`** — this
    plan integrates by only ever changing `Order.order_statuses_id`, never by
    editing the pool math.
- **`App\Services\PurchaseOrderProcessingService::markProcessed()`** — full
  current content already read. Sums scanned line items by product, inside
  one `DB::transaction`, row-locks each `StockProduct`, increments
  `on_stock_quantity`, recalculates free stock, saves, flips PO status to
  `Processed`; **after** that transaction commits, calls
  `$this->orderStatusTransitionService->refreshReservedStockForProductIds($productCounts->keys()->all())`
  (line ~126-128). **This exact point — right after that call, still inside
  `markProcessed()` — is where backorder fulfillment is triggered.**
- **`Modules\Orders\Observers\OrderObserver::updated()`** — full current
  content already read. Fires only when `$order->wasChanged('order_statuses_id')`;
  dispatches `OrderStatusChanged::dispatch($order->withoutRelations(), $previousStatus, $newStatus, $causerId)`.
  A listener elsewhere (implementing `ShouldQueueAfterCommit`, per the
  `OrderCreationService` comment) picks this up and calls
  `OrderStatusTransitionService::process()`. **Because that listener is
  queued-after-commit, it is not guaranteed to run synchronously** relative
  to the current request/transaction — this matters for the fulfillment
  service's FIFO loop (see `BackorderFulfillmentService` below).
  `OrderObserver::updating()` also throws `OrderStatusLockedException` if the
  *original* status has `canChangeStatus() === false` (final states:
  delivered/cancelled/completed). The new `Backordered` status is none of
  those, so transitioning out of it is never blocked by this guard.
- **`PurchaseOrder::incomingBatchesForProduct()`** and the existing
  deferral mechanism in `calculateReservedQuantity()` are a **distinct,
  pre-existing feature** (demand-deferral for orders *already* reserving
  stock) — not touched by this plan. `tests/Feature/PurchaseOrderStockDeferralTest.php`
  and `tests/Feature/PurchaseOrderStatusTransitionsTest.php` must still pass
  unmodified; run them as a regression check (see Tests section).
- **Existing API test that must change:**
  `tests/Feature/Api/CreateOrderEndpointTest.php:163-187` — the test
  `'rejects an order when requested quantity exceeds available stock'`
  currently asserts a 422 response and that no `orders`/`order_products`/`clients`
  rows were created. This test's premise is now wrong and must be rewritten
  (see Tests section for the replacement behavior) — this is a **known,
  intentional** behavior change, not a regression.
- **No Filament Policy classes exist anywhere in this app** (verified via
  grep) and no role/permission package is installed. Resource authorization
  in this app is handled ad-hoc inside Resource classes (e.g. `OrderResource::canDelete()`).
  Follow that convention: no Policy class for `Backorder`.

### Reference model for conventions (`StockProduct`, full file already read)

Use this exact structure/style for the new `Backorder` model: `declare(strict_types=1)`,
`HasFactory` with `/** @use HasFactory<...> */`, `LogsActivity` +
`getActivitylogOptions(): LogOptions` (`useLogName()`, `logFillable()`,
`logOnlyDirty()`, `dontLogEmptyChanges()`, `setDescriptionForEvent()`),
typed `BelongsTo` relation methods with `@return BelongsTo<X, $this>` PHPDoc,
and a `casts(): array` method as the **last** method in the class (per
`.ai/guidelines/custom-rules.md`).

### Migration style (verified via `app-modules/products/database/migrations/2025_09_03_084620_create_products.php`)

`declare(strict_types=1)`; anonymous class `extends Migration`; only an `up()`
method — **no `down()` method anywhere, ever** (per `.ai/guidelines/custom-rules.md`:
never roll back in prod, always a new migration for schema changes).

## Verified API (Filament v4, via `search-docs`)

- `Filament\Tables\Columns\TextColumn::make()->badge()` — when the column's
  underlying attribute is cast to a native PHP enum implementing
  `Filament\Support\Contracts\HasColor` and `Filament\Support\Contracts\HasLabel`,
  Filament automatically reads the label and color from the enum; no
  `->color(fn...)` closure is needed on the column itself.
  Docs: https://filamentphp.com/docs/4.x/tables/columns/text#displaying-as-a-badge
  and https://filamentphp.com/docs/4.x/advanced/enums#enum-colors
- `Filament\Tables\Filters\SelectFilter::make()->options([...])` — plain
  associative array of `value => label`.
  Docs: https://filamentphp.com/docs/4.x/tables/filters/select
- `Filament\Actions\Action::make()->requiresConfirmation()` — standard
  confirmation-modal action, used for the row-level "Cancel" action.
  Docs: https://filamentphp.com/docs/4.x/actions/overview
- Confirmed-wrong components to avoid (per the planning checklist, not used
  in this plan but noted so the implementer doesn't reach for them):
  `BadgeColumn` → use `TextColumn::make()->badge()`; `BooleanColumn` → use
  `IconColumn::make()->boolean()`.

## Commands

Run these before writing code, in order:

1. Activate the `modular` Boost skill (per `CLAUDE.md`) to scaffold the
   module's composer.json/ServiceProvider/directory structure. If its
   generator doesn't produce a Filament-aware structure matching
   `app-modules/notes` (directories: `src/{Filament/Resources,Models,Enums,Services}`,
   `database/{migrations,factories,seeders}`, `tests/.gitkeep`), create those
   files/directories by hand, mirroring `app-modules/notes` exactly.
2. `php artisan make:migration create_backorders_table --no-interaction` then
   move the generated file into `app-modules/backorders/database/migrations/`
   and rename it to match the house timestamp-prefix convention (see the
   file section below for exact content — do not use the scaffolded body).
3. `php artisan make:migration seed_backordered_order_status_for_existing_warehouses --no-interaction`,
   same move/rename treatment.
4. After `composer.json` changes (root + module), run `composer update modules/backorders --no-interaction`
   (or whatever the `modular` skill's own instructions specify for
   registering a new path-repository module — follow the skill, don't guess).
5. `php artisan make:test BackorderCreationTest --pest --no-interaction`
6. `php artisan make:test BackorderFulfillmentTest --pest --no-interaction`
7. `php artisan make:test BackorderResourceTest --pest --no-interaction`
8. After all PHP files are written: `vendor/bin/pint --dirty --format agent`
9. Run the test suite sections listed in **Build & Verification** below.

There is no Filament resource scaffold command listed here deliberately —
**do not run `make:filament-resource`**. The `BackorderResource` in this plan
has only a List page (no Create/Edit), which the generator doesn't produce
cleanly; write the Resource and its single page by hand following
`OrderResource`'s file layout (`Resource.php`, `Schemas/` not needed since
there's no form, `Tables/BackordersTable.php`, `Pages/ListBackorders.php`).

## Models

### New: `Modules\Backorders\Enums\BackorderStatus`

File: `app-modules/backorders/src/Enums/BackorderStatus.php`

```php
<?php

declare(strict_types=1);

namespace Modules\Backorders\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BackorderStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::PartiallyFulfilled => __('Partially fulfilled'),
            self::Fulfilled => __('Fulfilled'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'danger',
            self::PartiallyFulfilled => 'warning',
            self::Fulfilled => 'success',
            self::Cancelled => 'gray',
        };
    }
}
```

### New: `Modules\Backorders\Models\Backorder`

File: `app-modules/backorders/src/Models/Backorder.php`

- **Table:** `backorders`
- **Fillable:** `warehouse_id`, `order_id`, `order_product_id`, `product_id`,
  `intended_order_statuses_id`, `quantity_ordered`, `quantity_fulfilled`,
  `status`, `resolved_at`.
- **Relationships:**
  - `warehouse(): BelongsTo<Warehouse, $this>` → `App\Models\Warehouse`
  - `order(): BelongsTo<Order, $this>` → `Modules\Orders\Models\Order`
  - `orderProduct(): BelongsTo<OrderProduct, $this>` → `Modules\Orders\Models\OrderProduct`
  - `product(): BelongsTo<Product, $this>` → `Modules\Products\Models\Product`
  - `intendedOrderStatus(): BelongsTo<OrderStatus, $this>` → `Modules\Orders\Models\OrderStatus`,
    foreign key `intended_order_statuses_id`
- **Accessor:** `quantity_remaining` via `Illuminate\Database\Eloquent\Casts\Attribute`:
  `get: fn (): int => max(0, $this->quantity_ordered - $this->quantity_fulfilled)`
- **Traits:** `App\Models\Concerns\BelongsToWarehouse` (tenant scoping — same
  mechanism as `Order`/`Client`/`Product`), `HasFactory`, `LogsActivity`.
- **Interface:** `AlizHarb\ActivityLog\Contracts\HasActivityLogTitle` —
  `getActivityLogTitle()` returns `"Backorder for {$this->product?->name} (Order #{$this->order_id})"`;
  `getActivityLogParent(): ?Order` returns `$this->order`.
- **`getActivitylogOptions()`:** `useLogName('backorder')`, `logFillable()`,
  `logOnlyDirty()`, `dontLogEmptyChanges()`,
  `setDescriptionForEvent(fn (string $eventName): string => "Backorder {$eventName}")`.
- **`casts()`** (last method in the class):
  `quantity_ordered` → `integer`, `quantity_fulfilled` → `integer`,
  `status` → `BackorderStatus::class`, `resolved_at` → `datetime`.

Full file:

```php
<?php

declare(strict_types=1);

namespace Modules\Backorders\Models;

use AlizHarb\ActivityLog\Contracts\HasActivityLogTitle;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Database\Factories\BackorderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
```

## File: `app-modules/backorders/database/migrations/2026_11_12_000001_create_backorders_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('order_product_id')->constrained('order_products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('intended_order_statuses_id')->constrained('order_statuses')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_fulfilled')->default(0);
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });
    }
};
```

## File: `app-modules/backorders/database/migrations/2026_11_12_000002_seed_backordered_order_status_for_existing_warehouses.php`

Data-backfill migration. `TenantSeeder::seedOrderStatuses()` only runs for
**newly created** warehouses going forward (see edit below); every
**existing** warehouse needs this row inserted once at deploy time. Uses the
query builder (not Eloquent models) to avoid any model-event side effects
inside a migration, matching how other data-sensitive operations in this app
are kept separate from Eloquent lifecycle hooks.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('warehouses')->select('id')->orderBy('id')->each(function (object $warehouse) use ($now): void {
            $exists = DB::table('order_statuses')
                ->where('warehouse_id', $warehouse->id)
                ->where('name', 'Backordered')
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('order_statuses')->insert([
                'warehouse_id' => $warehouse->id,
                'name' => 'Backordered',
                'color' => '#F59E0B',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'reduce_stock' => false,
                'concepted' => false,
                'completed' => false,
                'on_hold' => true,
                'delivered' => false,
                'cancelled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
};
```

## File: `database/seeders/TenantSeeder.php` (edit)

In `seedOrderStatuses(Warehouse $warehouse)`, add one more entry to the
`$statuses` array (same shape/order of keys as the existing `'New'` and
`'Confirmed'` entries you already read) so every **newly created** warehouse
gets this status going forward, matching the backfill migration above
exactly:

```php
[
    'name' => 'Backordered',
    'color' => '#F59E0B',
    'generate_picklist' => false,
    'reserve_stock' => false,
    'reduce_stock' => false,
    'concepted' => false,
    'completed' => false,
    'on_hold' => true,
    'delivered' => false,
    'cancelled' => false,
],
```

Insert it anywhere in the array (order doesn't matter — the `foreach` just
`firstOrCreate`s each one); logically group it near `'New'`/`'Confirmed'`.

## File: `app-modules/backorders/database/factories/BackorderFactory.php`

Data only, no logic (per `.ai/guidelines/custom-rules.md`):

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;

/**
 * @extends Factory<Backorder>
 */
class BackorderFactory extends Factory
{
    protected $model = Backorder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity_ordered' => $this->faker->numberBetween(1, 20),
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Open,
        ];
    }
}
```

## File: `app-modules/backorders/src/Providers/BackordersServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Modules\Backorders\Providers;

use Illuminate\Support\ServiceProvider;

class BackordersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
```

## File: `app-modules/backorders/src/Services/BackorderCreationService.php`

Called once, right after `OrderCreationService::create()` persists the
order's final status (see diff below). Creates one `Backorder` row per
shortfall line, matched to the just-created `OrderProduct` by `product_id`.

```php
<?php

declare(strict_types=1);

namespace Modules\Backorders\Services;

use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;

class BackorderCreationService
{
    /**
     * @param  array<int, array{product_id: int, requested: int, available: int}>  $shortfalls
     */
    public function createForOrder(Order $order, array $shortfalls, int $intendedOrderStatusId): void
    {
        $order->loadMissing('products');

        foreach ($shortfalls as $shortfall) {
            $orderProduct = $order->products->firstWhere('product_id', $shortfall['product_id']);

            if (! $orderProduct) {
                continue;
            }

            Backorder::query()->create([
                'warehouse_id' => $order->warehouse_id,
                'order_id' => $order->id,
                'order_product_id' => $orderProduct->id,
                'product_id' => $shortfall['product_id'],
                'intended_order_statuses_id' => $intendedOrderStatusId,
                'quantity_ordered' => $shortfall['requested'],
                'quantity_fulfilled' => 0,
                'status' => BackorderStatus::Open,
            ]);
        }
    }
}
```

## File: `app-modules/backorders/src/Services/BackorderFulfillmentService.php`

**Cross-requirement consistency note (per planning overview's required
check):** this is the one path in the whole plan with a real ordering hazard,
so its stages are spelled out explicitly.

- **Rule:** a product's free stock may only be handed to one waiting
  backorder at a time, oldest (`created_at`) first; a backorder is only
  resolved (and its order released) once `quantity_fulfilled >= quantity_ordered`.
- **Stages:**
  1. `PurchaseOrderProcessingService::markProcessed()` increases
     `on_stock_quantity` and calls the **existing**
     `refreshReservedStockForProductIds()` — this already correctly excludes
     backordered orders from the pool (see Source of Truth above), so
     `StockProduct->free_on_stock_quantity` at this point reflects stock that
     is genuinely free of *normal* reserving-order demand.
  2. `BackorderFulfillmentService::fulfillForProducts()` is called next (same
     request, same PHP call stack — not queued).
  3. For each affected product, lock the `StockProduct` row and the matching
     open/partially-fulfilled `Backorder` rows (oldest first), and allocate
     from a **single in-memory running total** seeded from
     `free_on_stock_quantity`, decrementing it as each backorder consumes
     stock — this is what prevents two backorders in the *same run* from
     being allocated the same units twice.
  4. When a backorder reaches `Fulfilled`, **immediately** (same transaction)
     update the order's `order_statuses_id` to its `intended_order_statuses_id`
     *if and only if every other backorder on that order is also `Fulfilled`*
     — then **synchronously** call
     `$this->orderStatusTransitionService->refreshReservedStockForProductIds([$productId])`
     again, directly, **not** relying on the `OrderStatusChanged` listener.
  5. Re-read (`refresh()`) the locked `StockProduct` row before continuing to
     the next backorder in the loop.
- **Consistency check (the actual hazard this resolves):** the
  `OrderStatusChanged` listener that normally reacts to
  `order_statuses_id` changing is `ShouldQueueAfterCommit` (per the comment
  already read in `OrderCreationService`) — it is **not guaranteed to run
  synchronously**. If step 4's recompute were left to that listener alone,
  backorder #2 in the same FIFO loop could see stale `free_on_stock_quantity`
  (not yet reduced by order #1's release) and double-allocate the same
  physical units. Calling `refreshReservedStockForProductIds()` directly
  closes this gap regardless of queue driver. Calling it twice (once here,
  once later when the queued listener eventually runs `process()` →
  `reserveStock()` → the same method) is harmless — it is a pure recompute,
  not an increment.
- **Verification:** the `BackorderFulfillmentTest` below exercises exactly
  this: two backorders for the same product, one restock that only covers
  the first — assert the first is `Fulfilled` and released, the second stays
  `Open`/`PartiallyFulfilled` with the correct `quantity_fulfilled`, and a
  **second**, later restock resolves the second.

```php
<?php

declare(strict_types=1);

namespace Modules\Backorders\Services;

use App\Services\OrderStatusTransitionService;
use Illuminate\Support\Facades\DB;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\StockProduct;

class BackorderFulfillmentService
{
    public function __construct(
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
    ) {}

    /**
     * @param  array<int, int|string>  $productIds
     */
    public function fulfillForProducts(array $productIds): void
    {
        foreach (array_unique(array_filter($productIds)) as $productId) {
            $this->fulfillForProduct((int) $productId);
        }
    }

    public function fulfillForProduct(int $productId): void
    {
        DB::transaction(function () use ($productId): void {
            $stockProduct = StockProduct::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stockProduct) {
                return;
            }

            $backorders = Backorder::query()
                ->where('product_id', $productId)
                ->whereIn('status', [BackorderStatus::Open, BackorderStatus::PartiallyFulfilled])
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($backorders as $backorder) {
                if ($stockProduct->free_on_stock_quantity <= 0) {
                    break;
                }

                $remaining = $backorder->quantity_ordered - $backorder->quantity_fulfilled;

                if ($remaining <= 0) {
                    continue;
                }

                $allocated = min($remaining, $stockProduct->free_on_stock_quantity);
                $backorder->quantity_fulfilled += $allocated;
                $backorder->status = $backorder->quantity_fulfilled >= $backorder->quantity_ordered
                    ? BackorderStatus::Fulfilled
                    : BackorderStatus::PartiallyFulfilled;

                if ($backorder->status === BackorderStatus::Fulfilled) {
                    $backorder->resolved_at = now();
                }

                $backorder->save();

                if ($backorder->status === BackorderStatus::Fulfilled) {
                    $this->releaseOrderIfFullyFulfilled($backorder, $productId);
                    $stockProduct->refresh();
                }
            }
        });
    }

    private function releaseOrderIfFullyFulfilled(Backorder $fulfilledBackorder, int $productId): void
    {
        $order = $fulfilledBackorder->order;

        if (! $order) {
            return;
        }

        $stillWaiting = Backorder::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', BackorderStatus::Fulfilled)
            ->exists();

        if ($stillWaiting) {
            return;
        }

        $intendedStatus = $fulfilledBackorder->intendedOrderStatus;

        if (! $intendedStatus instanceof OrderStatus) {
            return;
        }

        $order->update(['order_statuses_id' => $intendedStatus->id]);

        // The OrderStatusChanged listener is ShouldQueueAfterCommit and is
        // not guaranteed to have run by the time the next backorder in this
        // product's FIFO loop is evaluated - force the recompute now so
        // free_on_stock_quantity reflects this release immediately.
        $this->orderStatusTransitionService->refreshReservedStockForProductIds([$productId]);
    }
}
```

## File: `app/Services/OrderCreationService.php` (edit)

Replace the hard-rejection path with backorder creation. Exact diff against
the full file already read:

1. Remove the `InsufficientStockException` import; add imports for
   `Modules\Orders\Models\OrderStatus` and
   `Modules\Backorders\Services\BackorderCreationService`.

2. Replace `loadAndValidateStock()`'s body and return type:

```php
/**
 * @param  array<int, array{product_id: int, quantity: int}>  $items
 * @return array{products: array<int, Product>, shortfalls: array<int, array{product_id: int, requested: int, available: int}>}
 */
private function loadAndValidateStock(Warehouse $warehouse, array $items): array
{
    $products = [];
    $shortfalls = [];

    foreach ($items as $item) {
        if (! array_key_exists($item['product_id'], $products)) {
            $products[$item['product_id']] = Product::query()
                ->where('warehouse_id', $warehouse->id)
                ->with('vatRate', 'stockProduct')
                ->findOrFail($item['product_id']);
        }

        /** @var Product $product */
        $product = $products[$item['product_id']];

        if ($product->stock_unlimited) {
            continue;
        }

        $stockProduct = $product->stockProduct;
        $available = $stockProduct instanceof StockProduct ? $stockProduct->free_on_stock_quantity : 0;

        if ($item['quantity'] > $available) {
            $shortfalls[] = [
                'product_id' => $product->id,
                'requested' => $item['quantity'],
                'available' => $available,
            ];
        }
    }

    return [
        'products' => $products,
        'shortfalls' => $shortfalls,
    ];
}
```

3. In `create()`, update the call site and the post-transaction status write:

```php
public function create(Warehouse $warehouse, array $data): Order
{
    if (! empty($data['custom_order_id'])) {
        $existingOrder = Order::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('custom_order_id', $data['custom_order_id'])
            ->first();

        if ($existingOrder) {
            return $existingOrder;
        }
    }

    $stockCheck = $this->loadAndValidateStock($warehouse, $data['items']);
    $products = $stockCheck['products'];
    $shortfalls = $stockCheck['shortfalls'];

    $client = $this->findOrCreateClient($warehouse, $data['client']);

    $order = DB::transaction(function () use ($warehouse, $data, $products, $client): Order {
        // ... unchanged - exact existing body (Order::create + OrderProduct::create loop) ...
    });

    $intendedOrderStatusId = (int) $data['order_status_id'];
    $orderStatusId = $intendedOrderStatusId;

    if ($shortfalls !== []) {
        $backorderedStatus = OrderStatus::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('name', 'Backordered')
            ->first();

        if (! $backorderedStatus) {
            throw new \RuntimeException(
                "Warehouse #{$warehouse->id} is missing its 'Backordered' order status - run the backorders module migrations."
            );
        }

        $orderStatusId = $backorderedStatus->id;
    }

    // Deliberately a separate write from the transaction above: see the
    // existing comment in this method for why (OrderObserver/ShouldQueueAfterCommit).
    $order->update(['order_statuses_id' => $orderStatusId]);

    if ($shortfalls !== []) {
        app(BackorderCreationService::class)->createForOrder($order, $shortfalls, $intendedOrderStatusId);
    }

    return $order;
}
```

Keep the existing comment above the final `update()` call verbatim (it
explains the `ShouldQueueAfterCommit` constraint) — do not remove it.

## File: `app/Exceptions/Api/InsufficientStockException.php` (delete)

No longer thrown anywhere once the above lands. Delete the file.

## File: `app/Services/PurchaseOrderProcessingService.php` (edit)

Inject `BackorderFulfillmentService` and call it right after the existing
`refreshReservedStockForProductIds()` call in `markProcessed()` — nowhere
else (not in `markPurchased()` or `cancel()` — see Scope Decisions above).

```php
use Modules\Backorders\Services\BackorderFulfillmentService;

class PurchaseOrderProcessingService
{
    public function __construct(
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
        private readonly BackorderFulfillmentService $backorderFulfillmentService,
    ) {}

    // ...evaluateScanCompletion(), markPurchased(), markReceived() unchanged...

    public function markProcessed(PurchaseOrder $purchaseOrder, ?int $causerId = null): void
    {
        // ...unchanged through the existing DB::transaction() block...

        $this->orderStatusTransitionService->refreshReservedStockForProductIds(
            $productCounts->keys()->all()
        );

        $this->backorderFulfillmentService->fulfillForProducts(
            $productCounts->keys()->all()
        );
    }

    // ...cancel(), refreshProductStock() unchanged...
}
```

## File: `app/Filament/Resources/ActivityLogs/ActivityLogResource.php` (edit)

Add `Backorder` to the tenant-scoping morph list. Since `Backorder` has its
own direct `warehouse_id` (via `BelongsToWarehouse`), it belongs in the
**first** group (the one using `whereBelongsTo($tenant, 'warehouse')`
directly) — same group as `Order`, `Client`, `Product`, not the special-cased
`StockProduct` branch.

```php
use Modules\Backorders\Models\Backorder;
```

```php
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
    // ...rest of the orWhereHasMorph() chain unchanged...
```

## File: `app/Providers/Filament/AdminPanelProvider.php` (edit)

Add the 9th `->discoverResources()` call, alphabetically **before** the
`brands` one:

```php
->discoverResources(in: base_path('app-modules/backorders/src/Filament/Resources'), for: 'Modules\Backorders\Filament\Resources')
->discoverResources(in: base_path('app-modules/brands/src/Filament/Resources'), for: 'Modules\Brands\Filament\Resources')
// ...existing 7 calls unchanged, in existing order...
```

(Match whatever exact formatting/indentation the existing 8 calls use — copy
the line immediately above the `brands` line and change only the module
name/namespace.)

## Resources

**Resource:** `Modules\Backorders\Filament\Resources\Backorders\BackorderResource`
Command: none (hand-written, see Commands section for why).
Location: `app-modules/backorders/src/Filament/Resources/Backorders/BackorderResource.php`
Docs: https://filamentphp.com/docs/4.x/resources/overview

- `protected static ?string $model = Backorder::class;`
- `protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;`
- No `form()` method — this resource has no Create/Edit pages.
- `getPages(): array` returns only:
  `'index' => ListBackorders::route('/')`
- `public static function canCreate(): bool { return false; }`
- `public static function canEdit(Model $record): bool { return false; }`
- `public static function canDelete(Model $record): bool { return false; }` —
  backorders are system-managed records; staff act on them only via the
  "Cancel" action, never delete.
- No `getEloquentQuery()` override needed — `BelongsToWarehouse`'s global
  scope handles tenant scoping automatically, same as `OrderResource`.

**Page:** `Modules\Backorders\Filament\Resources\Backorders\Pages\ListBackorders`
extends `Filament\Resources\Pages\ListRecords`, `protected static string $resource = BackorderResource::class;`.

**Table:** `Modules\Backorders\Filament\Resources\Backorders\Tables\BackordersTable`
(static `configure(Table $table): Table`, called from `BackorderResource::table()`,
mirroring how `OrderResource::table()` delegates to `OrdersTable::configure()`).

Columns (all `Filament\Tables\Columns\TextColumn`, docs:
https://filamentphp.com/docs/4.x/tables/columns/text):

- `TextColumn::make('order.generated_custom_order_id')` — `->label(__('Order'))->searchable()->sortable()`
- `TextColumn::make('product.name')` — `->label(__('Product'))->searchable()`
- `TextColumn::make('quantity_ordered')` — `->label(__('Ordered'))->sortable()`
- `TextColumn::make('quantity_fulfilled')` — `->label(__('Fulfilled'))->sortable()`
- `TextColumn::make('quantity_remaining')` — `->label(__('Remaining'))` (reads
  the model accessor defined above — Filament resolves this via plain
  attribute access, no `getStateUsing()` needed)
- `TextColumn::make('status')` — `->label(__('Status'))->badge()` (color/label
  from the `BackorderStatus` enum automatically, per Verified API above)
- `TextColumn::make('created_at')` — `->label(__('Backordered since'))->dateTime()->sortable()`
- `TextColumn::make('resolved_at')` — `->label(__('Resolved'))->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true)`

Filter: `Filament\Tables\Filters\SelectFilter` (docs:
https://filamentphp.com/docs/4.x/tables/filters/select)

- `SelectFilter::make('status')->label(__('Status'))->options([`
  `'open' => __('Open'), 'partially_fulfilled' => __('Partially fulfilled'), 'fulfilled' => __('Fulfilled'), 'cancelled' => __('Cancelled'),`
  `])`

Action: `Filament\Actions\Action` (docs: https://filamentphp.com/docs/4.x/actions/overview)

- **Component:** `Filament\Actions\Action::make('cancel')`
- **Location:** record action (table row), via `->recordActions([...])`
- **Visibility:** `->visible(fn (Backorder $record): bool => ! in_array($record->status, [BackorderStatus::Fulfilled, BackorderStatus::Cancelled], true))`
- **Authorization:** none beyond standard panel access — all authenticated
  panel users may cancel a backorder (matches: no Policy classes exist
  anywhere in this app; this is not a destructive action on the order itself).
- **Behavior (steps):**
  1. `->requiresConfirmation()`
  2. `->color('danger')`
  3. `->action(function (Backorder $record): void { $record->update(['status' => BackorderStatus::Cancelled, 'resolved_at' => now()]); })`
  4. Does **not** touch `$record->order` in any way (per confirmed rule #4).

## Authorization

All authenticated panel users may view the Backorder list and use the Cancel
action — no restrictions, matching every other module resource in this app
(no Policy classes exist anywhere; `OrderResource`/`ProductResource`/etc. all
rely on default Filament authorization with ad-hoc overrides only for
specific actions like delete). `canCreate()`/`canEdit()`/`canDelete()` are
hard-coded `false` on `BackorderResource` as specified above — this isn't a
permission restriction, it's removing capabilities that don't apply to a
system-managed record.

## Widgets

N/A — not requested, no dashboard/stats widget for this feature in this plan.

## Tests

All new tests at top-level `tests/Feature/`, Pest syntax, per house
convention (module's own `tests/` stays empty).

### 1. `tests/Feature/BackorderCreationTest.php`

Exercise `OrderCreationService::create()` directly (construct via `app(OrderCreationService::class)`),
with a `Warehouse` that has had `TenantSeeder::seedOrderStatuses()` run (or
manually seed the `New`/`Confirmed`/`Backordered` statuses for the test
warehouse — check how existing `OrderCreationService`-level tests, if any,
set this up, and follow that pattern for consistency).

- `it('creates a backorder and holds the order when stock is insufficient')`
  — product with `free_on_stock_quantity = 1`, order requests `5`. Assert:
  the order **is created** (not rejected), its `order_statuses_id` resolves
  to the warehouse's `Backordered` status, a `Backorder` row exists with
  `quantity_ordered = 5`, `quantity_fulfilled = 0`, `status = Open`,
  `intended_order_statuses_id` equals the originally-requested status id.
- `it('creates one backorder per insufficient line when an order has multiple products')`
- `it('does not create a backorder when stock is sufficient')` — regression:
  order gets the originally requested status directly, no `Backorder` rows.
- `it('never backorders a stock_unlimited product')` — regression of the
  existing `stock_unlimited` skip.
- `it('throws when the warehouse has no Backordered order status configured')`
  — a warehouse manually stripped of that status still fails loudly (assert
  the `\RuntimeException`), not silently.

### 2. `tests/Feature/BackorderFulfillmentTest.php`

Build the scenario directly against `BackorderFulfillmentService` and
`PurchaseOrderProcessingService::markProcessed()` (use real factories: a
`Warehouse`, `Product` + `StockProduct`, two `Order`s with their `Backorder`
rows created via the flow above or directly via the factory, a
`PurchaseOrder` + scanned `PurchaseOrderProduct` rows).

- `it('fully fulfills and releases a single backorder when enough stock arrives')`
  — assert `Backorder->status = Fulfilled`, `resolved_at` set,
  `quantity_fulfilled = quantity_ordered`, and the order's
  `order_statuses_id` now equals its `intended_order_statuses_id`. If that
  intended status has `generate_picklist = true`, also assert a `Picklist`
  row now exists for the order (mirrors the existing assertion style in
  `CreateOrderEndpointTest.php:241-261`, confirming the queued listener runs
  synchronously enough in the test environment).
- `it('allocates stock FIFO across two competing backorders for the same product')`
  — two backorders for the same product, older one created first, needing 10
  and 8 respectively; a restock of only 12 units. Assert the older backorder
  is fully `Fulfilled` (10) and released, the newer one is `PartiallyFulfilled`
  with `quantity_fulfilled = 2`, and its order's status is **unchanged**
  (still `Backordered`).
- `it('tops up a partially fulfilled backorder on a later restock')` —
  continuing the above scenario, a second `markProcessed()` call with 6 more
  units fully covers the remaining backorder; assert it becomes `Fulfilled`
  and its order releases too.
- `it('does not touch backorders for unrelated products')`.

### 3. `tests/Feature/BackorderResourceTest.php`

Use `Livewire::test()` against `ListBackorders` (follow the Livewire-testing
conventions already used by this app's other Filament resource tests — check
an existing one, e.g. `ActivityLogResourceTest.php`, for the exact
tenant/auth setup boilerplate to copy).

- `it('lists backorders for the current tenant warehouse only')` — a
  `Backorder` belonging to a different warehouse must not appear.
- `it('filters by status')`.
- `it('cancels a backorder without changing its order')` — call the `cancel`
  table action, assert `status = Cancelled`, `resolved_at` set, and the
  parent `Order`'s `order_statuses_id` is byte-for-byte unchanged.
- `it('hides the cancel action once a backorder is fulfilled or cancelled')`.

### 4. Update: `tests/Feature/Api/CreateOrderEndpointTest.php`

Rewrite the test at lines 163-187
(`'rejects an order when requested quantity exceeds available stock'`) —
its premise no longer holds. Replace with:

- `it('backorders an order when requested quantity exceeds available stock')`
  — same setup (`onStockQuantity: 1`, request `5`), but now assert
  `$response->assertCreated()`, the order row **does** exist, the
  `order_products` row **does** exist (quantity 5), and a `Backorder` row
  exists for that product/order with `quantity_ordered = 5`,
  `quantity_fulfilled = 0`.

Leave every other test in this file untouched — in particular, verify
`'allows a stock_unlimited product to exceed available stock'` (line 189) and
`'triggers the existing picklist pipeline when the chosen status requires it'`
(line 241) still pass unmodified, since neither is affected by this change.

### 5. Regression check (no code changes expected, just confirm green)

Run `tests/Feature/PurchaseOrderStockDeferralTest.php` and
`tests/Feature/PurchaseOrderStatusTransitionsTest.php` and confirm both still
pass exactly as before — this plan does not touch
`calculateReservedQuantity()`/`recalculateFreeStock()`/`incomingBatchesForProduct()`,
so there is no reason for either to change behavior, but they are the
closest pre-existing mechanism to this feature and must be proven untouched.

## Build & Verification

1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test --compact tests/Feature/BackorderCreationTest.php`
3. `php artisan test --compact tests/Feature/BackorderFulfillmentTest.php`
4. `php artisan test --compact tests/Feature/BackorderResourceTest.php`
5. `php artisan test --compact tests/Feature/Api/CreateOrderEndpointTest.php`
6. `php artisan test --compact tests/Feature/PurchaseOrderStockDeferralTest.php tests/Feature/PurchaseOrderStatusTransitionsTest.php`
7. `php artisan route:list --path=api/v1/orders` — sanity check the endpoint
   still resolves after the service change (no routing changes expected).

## Report Back

The implementing agent must report:

- [ ] Whether the `modular` skill's generator was used as-is or the module
      was hand-scaffolded, and any deviation from `app-modules/notes`'s
      structure.
- [ ] Confirmation that **no** `down()` method exists in either new
      migration.
- [ ] The exact warehouse(s) used in each new test and whether
      `TenantSeeder::seedOrderStatuses()` was reused or statuses were seeded
      manually — flag if this diverged from how other `OrderCreationService`
      tests (if any exist) set up their warehouse/status fixtures.
- [ ] Results of all six `Build & Verification` test commands, pass/fail,
      including the two regression suites.
- [ ] Whether `InsufficientStockException` had any usages found beyond
      `OrderCreationService` and `CreateOrderEndpointTest.php` (there should
      be none, but confirm via a final grep before deleting the file).
- [ ] Confirmation that `Picklist.back_order` was left untouched.
- [ ] Any Filament v4 API call in this plan that didn't work as specified
      (e.g. if `TextColumn::make('status')->badge()` did not automatically
      pick up the enum's color/label and a manual `->color()`/`->formatStateUsing()`
      had to be added) — report the deviation and what was used instead.
