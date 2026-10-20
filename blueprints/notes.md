# Notes Module Blueprint

> Part of the [WMS Blueprint](wms.md). See that file for tenancy/authorization
> rules shared across all modules.

## Purpose

Users need one simple, shared message board per warehouse — a running log of
free-text messages anyone can post and everyone can see, grouped by the date
they were posted, newest first. There is **no "subject"**: a message doesn't
attach to an Order, Product, Picklist, or PurchaseOrder — it's just a note
left for whoever's working that warehouse that day, the same way a physical
whiteboard by the door would work. **Append-only**: once posted, a message can
never be edited or deleted.

This replaces an earlier, different design (per-record notes attached to
Order/Product/Picklist/PurchaseOrder, with a central cross-module list) that
was planned and partially implemented before the user reconsidered the shape
of the feature. That design is fully unwound by this blueprint — see "Revert"
below for exactly what comes back out.

Covers the `Modules\Notes` module (kept, now much smaller) and one new
standalone page at the app level.

## Design

**No existing "grouped by date, instant post" convention exists anywhere in
this app** (confirmed: zero usage of Filament Table's `->groups()` feature
anywhere in `app-modules/*/src/Filament/**/Tables/*.php`; the vendor
`alizharb/filament-activity-log` package ships an `ActivityLogTimelineTableAction`
+ `timeline.blade.php` that render a vertical timeline, but it's unused
anywhere in this app and is a flat reverse-chronological list with no
day-grouping logic to borrow). This is new work, built from two precedents
that do exist:

- **`App\Filament\Pages\WarehouseMap`** (`app/Filament/Pages/WarehouseMap.php`)
  — the exact shape of a standalone `Filament\Pages\Page` (not a Resource)
  that reads the current tenant via `Filament::getTenant()` and is registered
  manually in `AdminPanelProvider`'s `->pages([...])` array rather than
  discovered. Notes' new page follows this shape exactly.
- Filament's own documented **"singular resource" custom-page-with-a-form**
  pattern (`filament/filament` 4.x docs, `03-resources/09-singular.md`) — a
  page implementing `Filament\Forms\Contracts\HasForms`, using
  `Filament\Forms\Concerns\InteractsWithForms`, with a `public ?array $data = [];`
  property, a `form(Schema $schema): Schema` method, `$this->form->fill()` in
  `mount()`, and `{{ $this->form }}` rendered in the page's Blade view. Its
  example wraps the fields in
  `Form::make([...])->livewireSubmitHandler('save')->footer([Actions::make([Action::make('save')->submit('save')])])`
  — reused verbatim (swapping `'save'` for `'postMessage'`) to get Enter-to-submit
  behavior for free instead of hand-rolling a `<form wire:submit>`.

## 1. `Note` model — simplified, direct warehouse ownership

**Migration — rewritten in place**, not superseded by a new one. This table
has only ever run in local dev and was never committed; the project's "never
add `down()`, always a new migration for schema changes" rule exists to
protect already-deployed/shared schema, which doesn't apply to a migration
that has never left this machine as part of this same uncommitted feature.
```php
Schema::create('notes', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('body');
    $table->timestamps();
});
```
No `morphs('subject')`. The `warehouse_id` FK follows the exact house style
already used by `vat_rates`/`attribute_groups`
(`app-modules/settings/database/migrations/..._create_vat_rates.php`:
`$table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');`).

**`Note` model** (`Modules\Notes\Models\Note`):
```php
class Note extends Model
{
    use BelongsToWarehouse;
    use HasFactory;

    protected $fillable = ['warehouse_id', 'body'];

    public static function getSearchableSettings(): array
    {
        return [
            'filterableAttributes' => ['warehouse_id', 'user_id'],
            'sortableAttributes' => ['created_at', 'updated_at'],
            'searchableAttributes' => ['body'],
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\Modules\Users\Models\User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
```
`use BelongsToWarehouse;` (`App\Models\Concerns\BelongsToWarehouse`) adds the
app's standard tenant **global scope** (`App\Models\Scopes\WarehouseScope`,
confirmed by reading it: `$builder->whereRaw('warehouse_id = ?', [$currentTenant->getKey()])`
whenever a Filament tenant is active) — the same mechanism `VatRate`/
`AttributeGroup`/`ProductCategory` already rely on. **Important**: this trait
only scopes queries; it does **not** auto-fill `warehouse_id` on create, so
the page must pass it explicitly when posting a message (see §2).
`getSearchableSettings()`/`toSearchableArray()` stay for CLAUDE.md-consistency
with every other model (still inert — no `laravel/scout` dependency, no
`config/scout.php`, same established non-wiring as `Order`/`VatRate`/etc.),
just repointed from `subject_type`/`subject_id` to `warehouse_id`. `user_id`
is deliberately **not** fillable — set only by the observer below.

**`NoteObserver` — unchanged.** Author-autofill
(`app-modules/notes/src/Observers/NoteObserver.php`: on `creating`, if
`user_id` is null, set it from `auth()->id()`, guarded with
`is_int($causerId) && $causerId > 0`) is orthogonal to the subject/warehouse
redesign and is kept exactly as-is, still registered in
`NotesServiceProvider::boot()` as `Note::observe(NoteObserver::class);`.
`NotesServiceProvider` drops its `loadViewsFrom(__DIR__.'/../../resources/views', 'notes')`
call — the new page's view lives at the app level like `WarehouseMap`'s, so
the module has no views of its own, matching every other module provider that
doesn't call `loadViewsFrom` (`brands`, `clients`, `platform`, `users`).
`NoteFactory` is unchanged (still fakes `body` only).

**The `$isScopedToTenant = false` workaround from the previous design is
obsolete and must not be ported forward.** It existed only because the old
polymorphic `Note` had no `warehouse()` relation, so Filament's automatic
per-resource tenant scope/observer registration
(`vendor/filament/filament/src/Resources/Resource/Concerns/BelongsToTenant.php`)
threw trying to call `$note->warehouse()`. The new design has no `Resource` at
all (so no `$isScopedToTenant` property exists to set) and `Note` has a real,
direct `warehouse()` relation — tenant isolation now comes for free from
`WarehouseScope`, identically to `VatRate`/`AttributeGroup`.

## 2. The single page

`App\Filament\Pages\Notes` (`app/Filament/Pages/Notes.php`) — a plain
standalone page, not a Resource. No sub-pages, no
`getRecordSubNavigation()`, nothing resource-shaped.

**Registration**: manually added to `AdminPanelProvider.php`'s
`->pages([Dashboard::class, WarehouseMap::class, Notes::class])`, replacing the
old `->discoverResources(in: base_path('app-modules/notes/src/Filament/Resources'), ...)`
line (there's no `Filament/Resources` directory left in the module to
discover — see "Revert" below).

**Navigation**: top-level nav item — **no `$navigationGroup`**, deliberately
diverging from `WarehouseMap`'s placement under `'Settings'`. This carries
over the original Notes reasoning: a message board is a routine,
frequently-visited view like Orders or Picklists, not an occasional
admin/audit tool. `protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;`
(confirmed unused by any other resource/page in this app), `$navigationLabel`/
`$title` = `'Notes'`.

**Form + instant post**, following the Filament "singular resource" doc
pattern exactly:
```php
class Notes extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.notes';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Textarea::make('body')
                        ->label(__('Message'))
                        ->required()
                        ->rows(2),
                ])
                    ->livewireSubmitHandler('postMessage')
                    ->footer([
                        Actions::make([
                            Action::make('post')
                                ->label(__('Post'))
                                ->submit('postMessage'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $data = $this->form->getState();

        Note::query()->create([
            'warehouse_id' => $this->getWarehouse()->getKey(),
            'body' => $data['body'],
        ]);

        $this->form->fill();

        Notification::make()->title(__('Message posted'))->success()->send();
    }

    public function getWarehouse(): Warehouse
    {
        /** @var Warehouse $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }

    public function getMessages(): Collection
    {
        return Note::query()->with('author')->latest()->get();
    }
}
```
`getWarehouse()` mirrors `WarehouseMap::getWarehouse()` exactly. `user_id` is
intentionally absent from the `create()` call — `NoteObserver` fills it.
`$this->form->getState()` runs the schema's validation (`required()`) as part
of Filament's normal form lifecycle, so an empty message is rejected inline
with no extra code.

**Display — grouped by date, newest first, no pagination.** `getMessages()`
orders with `->latest()` (newest row first) *before* grouping; the Blade view
(`resources/views/filament/pages/notes.blade.php`) then does:
```blade
<x-filament-panels::page>
    {{ $this->form }}

    @php $grouped = $this->getMessages()->groupBy(fn ($note) => $note->created_at->toDateString()); @endphp

    @foreach ($grouped as $date => $notesForDate)
        <h3>{{ \Illuminate\Support\Carbon::parse($date)->isToday() ? __('Today') : \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y') }}</h3>

        @foreach ($notesForDate as $note)
            {{-- author name, time, body --}}
        @endforeach
    @endforeach
</x-filament-panels::page>
```
Because the source collection is already newest-first, `groupBy()` preserves
that order both across date groups (today's group is encountered — and
therefore keyed — before yesterday's) and within each group (messages inside
a day stay in their original newest-first order) — no secondary sort needed.
Every message is shown — no pagination — since the user asked to "see all
messages on date." This app's global `Table::deferLoading()` default
(`app/Providers/FilamentServiceProvider.php`) is irrelevant here: no
`Filament\Tables\Table` is ever constructed, this is a bare Eloquent
collection rendered in a `@foreach`.

**Append-only, confirmed with the user: no delete, no edit, ever.** No row
actions, no `DeleteAction`, no `EditAction`, nothing resource-shaped.

## 3. Revert — fully unwinding the previous per-record design

Everything below was added by the earlier (now abandoned) per-record/
polymorphic design and must come back out:

**Delete:**
- `app-modules/notes/src/Models/Concerns/HasNotes.php`
- `app-modules/notes/src/Filament/Resources/Notes/` — the entire tree
  (`NoteResource.php`, `Tables/NotesTable.php`, `Columns/*.php`,
  `Schemas/NoteForm.php`, `Pages/ListNotes.php`, `Pages/ViewNote.php`)
- `app-modules/orders/src/Filament/Resources/Orders/Pages/ManageOrderNotes.php`
- `app-modules/orders/src/Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrderNotes.php`
- `app-modules/products/src/Filament/Resources/Products/Pages/ManageProductNotes.php`
- `app-modules/picklists/src/Filament/Resources/Picklists/Pages/ManagePicklistNotes.php`
- `tests/Feature/NotesTabTest.php` — deleted entirely, nothing in it survives
  (it's built around `ManageOrderNotes` et al., `notesAsSubject()`, and
  `subject_type`/`subject_id` assertions); replaced by a new test, not adapted

**Revert to pre-Notes state:**
- `app-modules/orders/src/Models/Order.php`, `PurchaseOrder.php`,
  `app-modules/products/src/Models/Product.php`,
  `app-modules/picklists/src/Models/Picklist.php` — remove
  `implements HasActivityLogTitle`, `use HasNotes;`, and the two now-unused
  `use` imports (`AlizHarb\ActivityLog\Contracts\HasActivityLogTitle`,
  `Modules\Notes\Models\Concerns\HasNotes`) from each
- `app-modules/orders/src/Filament/Resources/Orders/OrderResource.php`,
  `.../PurchaseOrders/PurchaseOrderResource.php`,
  `app-modules/products/src/Filament/Resources/Products/ProductResource.php`,
  `app-modules/picklists/src/Filament/Resources/Picklists/PicklistResource.php`
  — remove the `Manage<X>Notes` import, its entry in
  `getRecordSubNavigation()`, and its `'notes'` route in `getPages()` from
  each. **`PicklistResource.php` keeps its unrelated `FailedProductsTable`/
  `ProductsTable` `->default([])` fix** (a separate bug fix from this same
  session, not part of the Notes feature) — only the Notes-specific lines in
  this file are reverted.

**Kept as-is (still needed):**
- `app-modules/notes/composer.json` and the root `composer.json`'s
  `"modules/notes": "*"` line — the module still exists, now housing `Note`,
  its migration, `NoteObserver`, and `NoteFactory`.
- `app-modules/notes/src/Observers/NoteObserver.php`,
  `app-modules/notes/database/factories/NoteFactory.php`

## 4. Testing

Delete `tests/Feature/NotesTabTest.php` and add **`tests/Feature/NotesPageTest.php`**,
mirroring `tests/Feature/WarehouseMapTest.php`'s context-helper shape (local
`createNotesPageContext()`, not imported — Pest helpers aren't exported
between files in this repo):
- **Posting**: `Livewire::test(Notes::class)->fillForm(['body' => 'Stock arrived'])->call('postMessage')->assertOk()`,
  then assert a `Note` row exists with the current tenant's `warehouse_id` and
  the acting user's `user_id` (confirms author-autofill still works
  unchanged).
- **Newest-first ordering**: create two notes with distinct `created_at`
  (one freshly created, one `Carbon::yesterday()`-backdated via `forceFill()`
  + `saveQuietly()` or a factory `created_at` override), then assert
  `Livewire::test(Notes::class)` renders today's date group before
  yesterday's, and — for two notes on the same day with different
  timestamps — the later one renders first (`assertSeeInOrder([...])` against
  the rendered output, or asserting directly on the page's own
  `getMessages()` order).
- **Tenant scoping**: two warehouses (two subdomains, matching the existing
  `createActivityLogHistoryContext()`/old `NotesTabTest` pattern for a second
  tenant), one `Note` created in each; set the active tenant to warehouse A
  and assert `Note::query()->pluck('body')` contains warehouse A's message and
  not warehouse B's — this exercises `WarehouseScope` directly, since there is
  no more custom `scopeEloquentQueryToTenant()` to call.

## File-by-file checklist

**New:**
- `app/Filament/Pages/Notes.php`
- `resources/views/filament/pages/notes.blade.php`
- `tests/Feature/NotesPageTest.php`

**Modified:**
- `app-modules/notes/database/migrations/2026_10_20_120000_create_notes_table.php` — rewritten in place (drop `morphs('subject')`, add `warehouse_id` FK)
- `app-modules/notes/src/Models/Note.php` — drop `subject()`, add `warehouse()` + `BelongsToWarehouse`, repoint searchable settings, update `$fillable`
- `app-modules/notes/src/Providers/NotesServiceProvider.php` — drop the unused `loadViewsFrom()` call
- `app/Providers/Filament/AdminPanelProvider.php` — swap the notes `discoverResources()` line for a `Notes::class` entry in `->pages([...])`
- `app-modules/orders/src/Models/Order.php`, `PurchaseOrder.php`, `app-modules/products/src/Models/Product.php`, `app-modules/picklists/src/Models/Picklist.php` — revert `HasActivityLogTitle`/`HasNotes` additions
- `app-modules/orders/src/Filament/Resources/Orders/OrderResource.php`, `.../PurchaseOrders/PurchaseOrderResource.php`, `app-modules/products/src/Filament/Resources/Products/ProductResource.php`, `app-modules/picklists/src/Filament/Resources/Picklists/PicklistResource.php` — revert sub-nav/`getPages()` Notes wiring only (Picklist's unrelated infolist fix stays)
- `blueprints/wms.md` — update the one-line Notes description in the Module Blueprints index

**Deleted:**
- `app-modules/notes/src/Models/Concerns/HasNotes.php`
- `app-modules/notes/src/Filament/Resources/Notes/` (entire directory)
- `app-modules/orders/src/Filament/Resources/Orders/Pages/ManageOrderNotes.php`
- `app-modules/orders/src/Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrderNotes.php`
- `app-modules/products/src/Filament/Resources/Products/Pages/ManageProductNotes.php`
- `app-modules/picklists/src/Filament/Resources/Picklists/Pages/ManagePicklistNotes.php`
- `tests/Feature/NotesTabTest.php`

After implementation, run the mandatory checks (`php artisan lang:extract`;
`vendor/bin/phpstan analyse` and `vendor/bin/pint --dirty --format agent` as
the real equivalents of CLAUDE.md's `compose analyse`/`compose lint`, which
don't resolve to any actual command in this repo) and the new/affected tests
(`php artisan test --compact tests/Feature/NotesPageTest.php`).
