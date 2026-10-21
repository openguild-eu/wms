# WMS Filament Blueprint

## Context

This is a Filament Blueprint (per the `planning-filament` skill) for the entire WMS
Filament admin panel. It has two purposes:

1. **Reference specification** of every existing domain (Warehouses/Tenancy, Users,
   Products, Clients, Orders, Picklists) as currently implemented, so the document is
   a complete as-built map of the system, not just the new work.
2. **Full implementable spec** for the one significant gap found during exploration:
   the `PurchaseOrder` feature is scaffolded (migrations, models, Filament resource,
   CSV import) but has no working business logic — nothing wires up barcode
   scanning, nothing increments stock, and nothing implements the domain rule in
   `.github/instructions/warehouse-purchase-orders.instructions.md` that a client
   order's stock shouldn't be reserved while it's covered by an incoming purchase
   order due to arrive before the order's own delivery date.

**Decisions locked in with the user before this blueprint was written**:
- No `Supplier` concept is built in this pass (purchase orders stay supplier-less).
- `purchase_orders_products` gets a new `product_id` FK (resolved at import time),
  so stock mutation is deterministic instead of re-matching by barcode.
- The order/purchase-order stock dependency is **implicit by product + timing** — no
  explicit link table between `order_products` and `purchase_orders`. Accepted
  limitation: allocation is ambiguous under heavy contention for the same incoming
  quantity; the plan resolves this with a deterministic priority rule (earliest
  `orders.delivery_date` served first).
- Scan completion (100% of a PO's rows scanned) is what sets `processed = true` and
  increments stock. `completed` is a separate, manual "Mark Received" confirmation
  with no further stock effect.

**Verification method used for Filament APIs**: `search-docs` was not invoked live
in this planning pass; every Filament API surface below (`#[On]` Livewire
attributes, `Filament\Actions\Action` header actions, `TextEntry`/`TextColumn`
component chains, `FileUpload`, `DatePicker`) is verified against **resolved
installed application source** — the exact same API surface already working in
`ViewPicklist`, `PurchaseOrderResource`, and `ProductResource` in this codebase
(file paths cited throughout). Filament version installed: `filament/filament
v4.13.0` (confirmed via `composer.lock`, not the `^4.0` constraint). Doc URLs
below follow the `https://filamentphp.com/docs/4.x/...` pattern already used in
this project's own `.claude/skills/planning-filament` reference files — these were
not re-fetched live in this pass; treat them as best-effort references, not
verified-live links.

---

## 1. Tenancy & Authorization Foundations (existing — reference only)

These rules apply across every module documented in this Blueprint.

- **Two-layer multi-tenancy**:
  - Subdomain (tenant root): `App\Models\Subdomain` (`id`, `subdomain` unique, `name`
    unique). Resolved per-request by `App\Http\Middleware\IdentifySubdomain`, which
    binds `app()->instance('current_subdomain', $subdomain)`. `App\Models\Scopes\TenantScope`
    applies `where subdomain_id = current_subdomain.id` — used by `Warehouse` and
    `Modules\Users\Models\User`.
  - Warehouse (Filament tenant): configured in `app/Providers/Filament/AdminPanelProvider.php`
    via `->tenant(App\Models\Warehouse::class, ownershipRelationship: 'warehouse', slugAttribute: 'name')`.
    `App\Models\Scopes\WarehouseScope` applies `warehouse_id = Filament::getTenant()->getKey()`
    to every model using the `App\Models\Concerns\BelongsToWarehouse` trait (Product,
    Client, Order, PurchaseOrder, Brand, VatRate, AttributeGroup, ApiKey).
- **No formal Policy classes exist anywhere in this codebase** (confirmed by
  repo-wide search). Authorization is done via: (a) Eloquent global scopes for
  tenant/warehouse isolation, (b) static `canCreate()/canDelete()` overrides on
  Resource classes, (c) domain helper methods on models (e.g.
  `OrderStatus::canEditOrder()/canDeleteOrder()/canModifyProducts()`) consumed
  directly by Filament tables/pages. **New work in this plan follows this same
  convention** — no new Policy classes are introduced.
- `User::canAccessPanel()` always returns `true` — there is no role/permission
  package installed (no `spatie/laravel-permission`), so "Authorization: All
  authenticated users" is the correct spec wherever a module blueprint doesn't say
  otherwise.
- Navigation groups: only one is registered,
  `NavigationGroup::make()->label('Settings')`, in `AdminPanelProvider`. Resources
  either omit `$navigationGroup` (ungrouped: Order, PurchaseOrder, Picklist,
  Product, Brand) or set the exact string `protected static string|UnitEnum|null
  $navigationGroup = 'Settings';` (User, Warehouse, ApiKey, AttributeGroup,
  VatRate). **Do not use `?string` for this property** — that is the likely source
  of the recurring "NavigationGroup error" called out in
  `.github/instructions/warehouse-packages.instructions.md`.

---

## 2. Activity History Convention (implemented)

Every module's top-level Filament Resource has a "History" sub-navigation
tab backed by `spatie/laravel-activitylog`, showing that record's change log
via `alizharb/filament-activity-log`'s `ActivityLogResource`. This pattern
originally existed only for Orders, Products, and Clients — and was broken
everywhere it was used (see below) — and has since been implemented for
every other module too. This section documents the pattern once; each
module file states only its own specifics (which model(s), any field
exclusions) and links back here.

**Bug fixed**: `Modules\Orders\Filament\Resources\Orders\Pages\ManageOrderActivities`,
`Modules\Products\Filament\Resources\Products\Pages\ManageProductActivities`,
and `Modules\Clients\Filament\Resources\Clients\Pages\ManageClientActivities`
all set `protected static string $relationship = 'activities';`. Spatie
`LogsActivity` (v5, installed) only provides `activitiesAsSubject(): MorphMany`
(`vendor/spatie/laravel-activitylog/src/Models/Concerns/LogsActivity.php:87`)
— there is no `activities()` method on these models (that comes from the
separate `HasActivity` trait, which nothing in this app uses). Visiting any
of these three History tabs previously threw
`BadMethodCallException: Call to undefined method ...::activities()`.
`tests/Feature/ActivityLogResourceTest.php` never mounted the page, so this
had gone unnoticed. **Fixed everywhere this pattern is used**:
`$relationship = 'activitiesAsSubject'`, verified by the new
`tests/Feature/ActivityLogHistoryTabTest.php` (mounts every History page and
asserts it renders).

**Exception — `StockProduct`**: its activity is logged under
`subject_type = StockProduct`, a different subject than `Product`, so
`Product`'s own History tab never showed stock quantity changes by default.
`Product`'s History tab is the one resource-wide exception to the
"`ManageRelatedRecords` page per relationship" pattern above: its
`Pages\ManageProductHistory` combines both subject types into a single table
via a manually-built `Activity` query (no relationship spans both), reusing
`Product::stockActivities(): HasManyThrough` for the StockProduct side — see
[`products.md`](products.md) "Combined History tab" and
[`orders.md`](orders.md) §10 for how stock-quantity rows there are attributed
back to the Order/Purchase Order that caused them.

**Model side** — add to any model that should have a History tab:
```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class X extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('x')
            ->logFillable() // or ->logOnly([...]) — see per-model exclusion notes
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // casts() stays last per project rule
}
```

**Filament side** — one new page per resource, same shape as
`app-modules/orders/src/Filament/Resources/Orders/Pages/ManageOrderActivities.php`,
with the relationship bug already fixed:
```php
namespace Modules\<Module>\Filament\Resources\<X>\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Modules\<Module>\Filament\Resources\<X>\<X>Resource;

class Manage<X>Activities extends ManageRelatedRecords
{
    protected static string $resource = <X>Resource::class;
    protected static string $relationship = 'activitiesAsSubject';
    protected static ?string $relatedResource = ActivityLogResource::class;
    protected static ?string $navigationLabel = 'History';
    protected static ?string $breadcrumb = 'History';
    protected static ?string $title = 'History';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return null;
    }
}
```

Resource wiring (mirrors `OrderResource.php:26-37`):
```php
protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

public static function getRecordSubNavigation(Page $page): array
{
    return $page->generateNavigationItems([
        Edit<X>::class, // or View<X>::class where no Edit page exists (Picklist, PurchaseOrder)
        Manage<X>Activities::class,
    ]);
}
```
And register the route in `getPages()`: `'history' => Manage<X>Activities::route('/{record}/history')`.

**Tenant scoping** — `App\Filament\Resources\ActivityLogs\ActivityLogResource::scopeEloquentQueryToTenant()`
(`app/Filament/Resources/ActivityLogs/ActivityLogResource.php:35-56`) filters
the History tab's rows to the current warehouse by checking the logged
subject's own tenant relationship. Every model newly given `LogsActivity`
must be added to this method's subject-type list, with three shapes
depending on how that model relates to a warehouse:
- **Direct `warehouse_id`** (`ApiKey`, `VatRate`, `Brand`, `OrderStatus`,
  `Picklist` — already present): add to the existing
  `whereBelongsTo($tenant, 'warehouse')` group.
- **`Warehouse` itself**: the subject *is* the tenant, not something that
  belongs to it — add a branch:
  `->orWhereHasMorph('subject', [Warehouse::class], fn ($q) => $q->whereKey($tenant->getKey()))`.
- **`User`**: no `warehouse_id` — scoped via `subdomain_id` +
  `user_warehouses` many-to-many — add a branch:
  `->orWhereHasMorph('subject', [User::class], fn ($q) => $q->whereHas('warehouses', fn ($w) => $w->whereKey($tenant->getKey())))`.
- **`AttributeGroup`**: `warehouse_id` is nullable (shared/global groups
  allowed) — include it in the `whereBelongsTo` group **and** add
  `orWhereNull('warehouse_id')` so a global group's history isn't invisible
  in every tenant's History tab.

**Sensitive-field exclusions** — per this project's activity-log privacy
rule (never log passwords/tokens/secrets):
- `User::getActivitylogOptions()` → `->logOnly(['name', 'email'])` (excludes
  `password`).
- `ApiKey::getActivitylogOptions()` → `->logOnly(['name', 'is_active', 'last_used_at', 'expires_at', 'allowed_ips'])`
  (excludes `key_hash` and `warehouse_id`).
- Every other model uses plain `->logFillable()`.

**Scope of this convention**: one History tab per top-level Filament
Resource only. Child/line-item models (`OrderProduct`, `ClientAddresses`,
`PicklistProduct`, `PicklistFailedProduct`, `PurchaseOrderProduct`,
`PurchaseOrderFailedProduct`, `Attribute`) do not get their own tab — this
matches the existing precedent (`Order` has one, `OrderProduct` doesn't).
`StockProduct` already has `LogsActivity` but no page of its own — its
changes are logged under a separate `subject_type` and aren't surfaced in
any UI. This is a pre-existing gap, noted for awareness, not fixed here.

**Regression test** (implemented, covers every module):
`tests/Feature/ActivityLogHistoryTabTest.php` is a dataset-driven Pest test
that, for every resource with a History tab (including the `Product` "Stock
History" tab), creates a fixture record and asserts
`Livewire::test(Manage<X>Activities::class, ['record' => $record->getKey()])->assertOk()`
— the exact call shape that previously 500'd for Order/Product/Client, making
this a true regression test rather than a smoke test. It also asserts that
an excluded field (`ApiKey.key_hash`, `User.password`) never appears in the
resulting `attribute_changes` column after an update, and that
`StockProduct`'s activity is surfaced separately from `Product`'s own.

---

## Module Blueprints

Each existing domain and its new work is documented in its own file:

- [`settings.md`](settings.md) — Warehouses, Subdomains, API Keys, VAT Rates, Attributes; History tab (new work)
- [`users.md`](users.md) — Users; History tab (new work)
- [`products.md`](products.md) — Products, Stock; History tab bug fix
- [`locations.md`](locations.md) — Stock Locations (nested, ranked warehouse bins), picklist walking-order sort, and the warehouse floor map visualization (new work)
- [`brands.md`](brands.md) — Brands; History tab (new work)
- [`clients.md`](clients.md) — Clients; History tab bug fix
- [`orders.md`](orders.md) — Orders, Order Statuses, Purchase Orders (implemented), E-commerce Order API (planned); History tab bug fix + new work
- [`picklists.md`](picklists.md) — Picklists; History tab (new work)
- [`notes.md`](notes.md) — Notes: append-only notes (`Modules\Notes` module), either a general warehouse-wide message or attached to an Order, Purchase Order, Product, Picklist, or Client via a "Notes" tab on each; every notes page (central `/notes` and each record's own tab) is the same Facebook-message-style feed — one message box, one scrolling list grouped by date, no table or filters (new work)
- [`platform.md`](platform.md) — AI persistence, cross-cutting findings
- [`dashboard.md`](dashboard.md) — Tenant dashboard page and widgets: order/picklist backlog, monthly sales charts, low-stock and top-products tables (new work)

The Purchase Order feature and the E-commerce Order Creation API — the two
pieces of new work described in the Context above — are both documented in
[`orders.md`](orders.md), since `PurchaseOrder` lives in `Modules\Orders` and
the order-creation API is entirely order-scoped. The Activity History
convention (§2) applies uniformly across every module file listed below.
