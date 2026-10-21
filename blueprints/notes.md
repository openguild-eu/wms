# Notes Module Blueprint

> Part of the [WMS Blueprint](wms.md). See that file for tenancy/authorization
> rules shared across all modules.

## Purpose

Two related things, both already decided with the user:

1. **A general, subject-less message board** — exactly what existed before
   this blueprint. Anyone can post a free-text message, visible to everyone
   in the warehouse, grouped by the day it was posted. Nothing required.
2. **Per-record notes** — a note can *optionally* be attached to one Order,
   Product, Picklist, Purchase Order, or Client, the same way a sticky note
   gets left on a specific file. Added from that record's own "Notes" tab.

Both kinds of note are the same `Note` model, and **every place notes are
shown — the central `/notes` page and all five per-record "Notes" tabs — is
the same Facebook-message-style feed: one message box at the top, then every
relevant note below it, newest first, grouped by date. None of them are a
`Filament\Tables\Table`.** This was tried once with a table (columns, a
subject-type filter) and explicitly rejected by the user — see Design.

- **`/notes`** shows every note that exists (general and per-record), with a
  small link to the subject inline when a note has one.
- **A record's own "Notes" tab** (e.g. `/orders/1/notes`) shows only that
  record's own notes — no subject link needed, since every note shown there
  already has the same, implied subject.

**Append-only for every note, general or per-record: no edit, no delete,
ever.** This carries over unchanged and is not reconsidered here.

This supersedes the previous `blueprints/notes.md` (commit `3724fc2e`), which
deliberately dropped per-record notes down to the general board only. It does
**not** re-litigate that decision as a mistake — it adds per-record notes back
on top of the general board that commit built, plus a Client note type that
never existed before.

Covers the `Modules\Notes` module and five other modules' resources
(`Modules\Orders`, `Modules\Products`, `Modules\Picklists`, `Modules\Clients`)
that each get a "Notes" sub-navigation page.

## Design

- **Every notes page is a feed, not a table — confirmed twice with the
  user.** The first pass built `/notes` as `NoteResource`'s own
  `ListRecords`-based index page (a table with a subject-type filter); that
  was explicitly rejected: "i dont want table overview with comments, i want
  it like the facebook message way, one page with alle messages with a
  message field." The second pass kept that feed for `/notes` but left the
  five per-record tabs as `ManageRelatedRecords` tables (reusing
  `NoteResource`'s `table()`); that was rejected too, pointing at
  `/orders/1/notes` specifically: "on module level ... it is still not one
  page way like in /notes." Every notes page is now the same feed shape.
  Because of this, `NoteResource` ends up almost empty — it exists only to
  register `/notes` and to resolve a note's subject link (`getSubjectUrl()`),
  not to share a `form()`/`table()`/`infolist()` the way a conventional
  Filament Resource would.
- **No morph map.** `grep -rln "morphMap\|MorphMap" app/ app-modules/` finds
  nothing anywhere in this app; Spatie Activitylog's own `subject_type` already
  stores full class names (confirmed in `ActivityLogResource`, which matches
  `Order::class`/`Product::class`/etc., not short aliases). `Note::subject_type`
  follows that existing convention — full class names, no
  `Relation::enforceMorphMap()` — even though a CLAUDE.md custom rule says
  every Filament provider must define one; per that same file's own rule
  priority, an existing project convention wins over the rule.
- **Subject display label reuses `getActivityLogTitle()`.** Order, PurchaseOrder,
  Product, Picklist, and Client all already implement this method (confirmed
  by grep — only the `implements HasActivityLogTitle` declaration was dropped
  from the first four when the old per-record design was reverted; the method
  bodies were left in place for the activity log itself). No new title logic.
- **`Filament\Forms\Components\MorphToSelect`** (Filament 4.x docs,
  `packages/forms/docs/03-select.md`) is the documented component for picking
  a polymorphic subject from a form — two selects, type then record — via
  `MorphToSelect\Type::make(Order::class)->getOptionLabelFromRecordUsing(fn (Order $record): string => $record->getActivityLogTitle())`.
  Used only on the central `/notes` feed's message form (§6); the per-record
  tabs never show it, since the subject there is already fixed to the current
  record. Each `Type` also needs an explicit `->searchColumns([...])` (or
  `->titleAttribute()`) — `getOptionLabelFromRecordUsing()` only controls the
  *displayed* label; without a search column, selecting/searching a subject
  throws `LogicException: ... must have a [titleAttribute()] set.` (thrown
  from `getSearchColumns()`, a separate code path).
- **`MorphToSelect`/forms in general need the schema bound to a model.**
  Both notes forms (`ListNotes`'s and the per-record tabs', via
  `HasNotesFeed`) are built outside any Resource Create/Edit page, so nothing
  calls `$schema->model(Note::class)` automatically — without it explicitly,
  `MorphToSelect::getModelInstance()` returns `null` and throws calling
  `hasAttribute()` on it (Filament 4.x docs,
  `packages/forms/docs/01-overview.md` confirms the general requirement that
  a `MorphTo`-backed form needs a resolvable model).
- **Per-record notes are queried directly on `Note`, not through the owning
  model's `notes()` relation, from Filament page code.** `Order`/`PurchaseOrder`/
  `Product`/`Picklist`/`Client` do have a `notes(): MorphMany` relation (via
  `HasNotes`, kept for convenience/other call sites and exercised directly in
  tests), but the five "Notes" tabs share one trait (`HasNotesFeed`) whose
  `getRecord(): Model` return type has no common interface narrowing it to
  "a model that has `notes()`". Rather than invent a marker interface (tried,
  and abandoned — Laravel's `MorphMany`/`HasOneOrMany` generics are
  **invariant** on `TDeclaringModel`, so no single interface signature using
  `static`, `$this`, or a concrete class can satisfy PHPStan's child-return-type
  check across five different concrete model classes at once), the trait just
  does `Note::query()->where('subject_type', $record::class)->where('subject_id', $record->getKey())`
  directly — the same approach `NoteResource::getSubjectUrl()` already uses
  for the reverse lookup.
- **Saving to a `MorphTo` relationship requires nullable FK columns** (Filament
  4.x docs, `packages/forms/docs/01-overview.md`: "Filament saves the schema
  first... the foreign ID does not exist yet, so it must be nullable")
  confirms `nullableMorphs('subject')`, not a required morph — consistent with
  decision 1 above (a note is allowed to have no subject at all).
- **No `NotePolicy`.** `find . -iname "*Policy*.php"` finds zero policy classes
  anywhere in this app, and Filament's own docs confirm an operation is
  allowed by default when its policy is missing. Nothing here needs one,
  same as every other resource/model in this app.

## 1. Schema — add the subject columns

**New migration** (the existing
`app-modules/notes/database/migrations/2026_10_20_120000_create_notes_table.php`
is already committed on `main` as of commit `3724fc2e` and must not be
rewritten again — CLAUDE.md: "You MUST NOT use `down()` methods... create a
new migration" for schema changes to anything already committed):

`app-modules/notes/database/migrations/2026_10_21_000000_add_subject_to_notes_table.php`:
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
        Schema::table('notes', function (Blueprint $table): void {
            $table->nullableMorphs('subject');
        });
    }
};
```
`nullableMorphs('subject')` adds `subject_type` (nullable `string`) and
`subject_id` (nullable `unsignedBigInteger`), plus a composite index — both
required to stay nullable so a note can still have no subject at all, and
because Filament's own `MorphTo`-relationship-saving mechanism requires it
(see Design).

## 2. `Note` model — add the `subject` relation

`Modules\Notes\Models\Note` (`app-modules/notes/src/Models/Note.php`), modified:
```php
class Note extends Model
{
    use BelongsToWarehouse;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'warehouse_id',
        'subject_type',
        'subject_id',
        'body',
    ];

    public static function getSearchableSettings(): array
    {
        return [
            'filterableAttributes' => ['warehouse_id', 'user_id', 'subject_type', 'subject_id'],
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
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
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
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
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
`subject_type`/`subject_id` are fillable because both notes forms (§6, §7)
mass-assign them directly via `Note::query()->create([...])` — there is no
Filament relationship-save step involved on either path (see Design). `user_id`
stays deliberately **not** fillable, same as before — only `NoteObserver` sets
it.

## 3. `NoteObserver` — also default `warehouse_id`

**Rule:** every `Note`, whichever of the six creation paths makes it (the
central `/notes` feed, or one of five per-record "Notes" tabs), ends up with
the same `warehouse_id` — the active tenant's — with no caller responsible
for passing it explicitly.

**Stages:**
- *Before this blueprint*: only the old standalone board page created notes,
  and it passed `warehouse_id` explicitly from `Filament::getTenant()`.
- *After*: six creation paths exist, none of which is the one piece of code
  that used to own `warehouse_id`.
- *Enforcement point*: move the `warehouse_id` default into `NoteObserver`,
  the single place every creation path already funnels through for `user_id`.

**Consistency check:** a note created from a per-record tab (which never
mentions `warehouse_id` at all) must end up scoped to the same tenant as one
created from the central feed — both go through `NoteObserver`, so both get
it from `Filament::getTenant()` the same way.

`app-modules/notes/src/Observers/NoteObserver.php`, modified:
```php
class NoteObserver
{
    public function creating(Note $note): void
    {
        $causerId = auth()->id();

        if ($note->user_id === null && is_int($causerId) && $causerId > 0) {
            $note->user_id = $causerId;
        }

        if ($note->warehouse_id === null) {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Warehouse) {
                $note->warehouse_id = $tenant->getKey();
            }
        }
    }
}
```

## 4. `HasNotes` trait — re-created, now including `Client`

`app-modules/notes/src/Models/Concerns/HasNotes.php` (new — this exact file
existed before the revert and is re-created unchanged):
```php
<?php

declare(strict_types=1);

namespace Modules\Notes\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Notes\Models\Note;

trait HasNotes
{
    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject');
    }
}
```
This relation is kept for convenience (e.g. eager-loading `$order->notes`
elsewhere) and is exercised directly in tests, but the five "Notes" tab pages
themselves do **not** call it — see Design for why.

Add `use HasNotes;` plus `implements HasActivityLogTitle` (re-added — the
method already exists on all five, only the interface declaration is new) to:
- `app-modules/orders/src/Models/Order.php`
- `app-modules/orders/src/Models/PurchaseOrder.php`
- `app-modules/products/src/Models/Product.php`
- `app-modules/picklists/src/Models/Picklist.php`
- `app-modules/clients/src/Models/Client.php` (already implements the
  interface and the method today — only `use HasNotes;` is new here)

## 5. `NoteResource` — almost nothing, just enough to host `/notes`

`Modules\Notes\Filament\Resources\Notes\NoteResource`
(`app-modules/notes/src/Filament/Resources/Notes/NoteResource.php`, new):
```php
class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $modelLabel = 'Note';

    public static function getSubjectUrl(Note $note): ?string
    {
        $subject = $note->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        return match ($subject::class) {
            Order::class => OrderResource::getUrl('edit', ['record' => $subject]),
            PurchaseOrder::class => PurchaseOrderResource::getUrl('edit', ['record' => $subject]),
            Product::class => ProductResource::getUrl('edit', ['record' => $subject]),
            Picklist::class => PicklistResource::getUrl('view', ['record' => $subject]),
            Client::class => ClientResource::getUrl('edit', ['record' => $subject]),
            default => null,
        };
    }

    /**
     * The subject's own "Notes" tab (`Manage<X>Notes`) — every one of the
     * five subject resources registers a `'notes'` page (§7), so this
     * mirrors `getSubjectUrl()` exactly but points there instead of at the
     * subject's edit/view page.
     */
    public static function getSubjectNotesUrl(Note $note): ?string
    {
        $subject = $note->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        return match ($subject::class) {
            Order::class => OrderResource::getUrl('notes', ['record' => $subject]),
            PurchaseOrder::class => PurchaseOrderResource::getUrl('notes', ['record' => $subject]),
            Product::class => ProductResource::getUrl('notes', ['record' => $subject]),
            Picklist::class => PicklistResource::getUrl('notes', ['record' => $subject]),
            Client::class => ClientResource::getUrl('notes', ['record' => $subject]),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotes::route('/'),
        ];
    }
}
```
No `form()`, `table()`, or `infolist()` — nothing calls them. `ListNotes`
(§6) and the five "Notes" tabs (§7) each define their own form/view directly;
there is no shared Filament schema to centralize here once none of them use
a table. Both `getSubjectUrl()` and `getSubjectNotesUrl()` are `public`
because the central feed's Blade view (§6) calls them directly, rendering
two links per subject-linked note: one to the subject's own edit/view page,
one to that subject's "Notes" tab (so a reader can jump straight from a
general-feed mention of an Order to every other note left on that same
Order).

`PicklistResource::getUrl('view', ...)` not `'edit'` — confirmed by reading
`PicklistResource::getPages()`: it has no `'edit'` route, only `'index'`,
`'view'`, `'history'`.

## 6. The central `/notes` page

`Modules\Notes\Filament\Resources\Notes\Pages\ListNotes`
(`.../Pages/ListNotes.php`, new) — `NoteResource`'s registered index page (so
`/notes` and the "Notes" nav item work), extending the plain resource `Page`
class, not `ListRecords`:
```php
class ListNotes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = NoteResource::class;

    protected string $view = 'filament.pages.notes';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Note::class)
            ->components([
                MorphToSelect::make('subject')
                    ->label(__('Link to'))
                    ->types([
                        MorphToSelect\Type::make(Order::class)
                            ->getOptionLabelFromRecordUsing(fn (Order $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_order_id', 'custom_order_id', 'invoice_name', 'delivery_name', 'email']),
                        MorphToSelect\Type::make(PurchaseOrder::class)
                            ->getOptionLabelFromRecordUsing(fn (PurchaseOrder $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_purchase_order_id']),
                        MorphToSelect\Type::make(Product::class)
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['name', 'reference_code', 'product_code', 'barcode']),
                        MorphToSelect\Type::make(Picklist::class)
                            ->getOptionLabelFromRecordUsing(fn (Picklist $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_picklist_id']),
                        MorphToSelect\Type::make(Client::class)
                            ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['company', 'email']),
                    ]),
                Textarea::make('body')
                    ->label(__('Message'))
                    ->required()
                    ->rows(2),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $data = $this->form->getState();

        Note::query()->create([
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'body' => $data['body'],
        ]);

        $this->form->fill();

        Notification::make()->title(__('Message posted'))->success()->send();
    }

    /**
     * Named `getBoardMessages()`, not `getMessages()`, to avoid colliding
     * with Livewire's own internal `HandlesValidation::getMessages()`.
     *
     * @return Collection<int, Note>
     */
    public function getBoardMessages(): Collection
    {
        return Note::query()->with(['author', 'subject'])->latest()->get();
    }
}
```
`->model(Note::class)` and each `Type`'s `->searchColumns([...])` are both
required for the reasons in Design — omitting either throws at runtime, not
statically. `subject_type`/`subject_id` are simply `null` when the
`MorphToSelect` is left blank, posting a general note. `user_id`/`warehouse_id`
are never passed explicitly — `NoteObserver` (§3) fills both.

`resources/views/filament/pages/notes.blade.php` (new) renders the
date-grouped feed, each note showing a link to its subject when it has one:
```blade
<x-filament-panels::page>
    @php
        $grouped = $this->getBoardMessages()->groupBy(fn ($note) => $note->created_at->toDateString());
    @endphp

    <form wire:submit="postMessage" class="notes-form">
        {{ $this->form }}

        <x-filament::button type="submit">{{ __('Post') }}</x-filament::button>
    </form>

    <div class="notes-board">
        @forelse ($grouped as $date => $notesForDate)
            <h3>{{ \Illuminate\Support\Carbon::parse($date)->isToday() ? __('Today') : \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y') }}</h3>

            @foreach ($notesForDate as $note)
                {{-- author, time --}}
                @if ($note->subject)
                    <a href="{{ \Modules\Notes\Filament\Resources\Notes\NoteResource::getSubjectUrl($note) }}">
                        {{ $note->subject->getActivityLogTitle() }}
                    </a>
                    &middot;
                    <a href="{{ \Modules\Notes\Filament\Resources\Notes\NoteResource::getSubjectNotesUrl($note) }}">
                        {{ __('Notes') }}
                    </a>
                @endif
                {{-- body --}}
            @endforeach
        @empty
            <p>{{ __('No messages yet.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
```
No pagination, no filter bar — every message renders.

## 7. Per-record "Notes" tabs — the same feed, scoped to one record

Five pages, one per module, each the *same feed* as `/notes` but showing only
one record's own notes and with no subject picker (the subject is already
fixed). Rather than duplicate the form/mount/post/list logic five times, it
lives once in a shared trait:

`app-modules/notes/src/Filament/Pages/Concerns/HasNotesFeed.php` (new):
```php
trait HasNotesFeed
{
    use InteractsWithForms;
    use InteractsWithRecord;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Note::class)
            ->components([
                Textarea::make('body')
                    ->label(__('Message'))
                    ->required()
                    ->rows(2),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $record = $this->getRecord();
        $data = $this->form->getState();

        Note::query()->create([
            'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'body' => $data['body'],
        ]);

        $this->form->fill();

        Notification::make()->title(__('Message posted'))->success()->send();
    }

    /**
     * @return Collection<int, Note>
     */
    public function getBoardMessages(): Collection
    {
        $record = $this->getRecord();

        return Note::query()
            ->where('subject_type', $record::class)
            ->where('subject_id', $record->getKey())
            ->with('author')
            ->latest()
            ->get();
    }
}
```
`Filament\Resources\Pages\Concerns\InteractsWithRecord` (the same concern
`ViewRecord`/`EditRecord` use) supplies `$record`, `resolveRecord()`, and
`getRecord(): Model` — this is what every record-scoped Resource page
(`ManageRelatedRecords` included) is built on, so `mount(int|string $record)`
here follows the exact same shape as `ViewRecord::mount()`. Querying `Note`
directly by `subject_type`/`subject_id` instead of calling `$record->notes()`
is deliberate — see Design for the generics problem that approach runs into.

Each of the five pages is then just four properties:
```php
class ManageOrderNotes extends Page implements HasForms
{
    use HasNotesFeed;

    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.pages.record-notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';
}
```
- `app-modules/orders/src/Filament/Resources/Orders/Pages/ManageOrderNotes.php`
- `app-modules/orders/src/Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrderNotes.php`
  — identical shape, `$resource = PurchaseOrderResource::class`.
- `app-modules/products/src/Filament/Resources/Products/Pages/ManageProductNotes.php`
  — identical shape, `$resource = ProductResource::class`.
- `app-modules/picklists/src/Filament/Resources/Picklists/Pages/ManagePicklistNotes.php`
  — identical shape, `$resource = PicklistResource::class`.
- `app-modules/clients/src/Filament/Resources/Clients/Pages/ManageClientNotes.php`
  (new type of page for this resource) — identical shape,
  `$resource = ClientResource::class`.

`resources/views/filament/pages/record-notes.blade.php` (new) is the same
shape as `notes.blade.php` (§6) minus the subject link (always redundant here
— every note shown already belongs to the current record) and minus the
`MorphToSelect` in the form.

Each page is wired into its resource exactly like `ManageClientActivities` is
wired into `ClientResource` — an entry in `getRecordSubNavigation()` and a
`'notes' => Manage<X>Notes::route('/{record}/notes')` entry in `getPages()` —
for:
- `app-modules/orders/src/Filament/Resources/Orders/OrderResource.php`
- `app-modules/orders/src/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php`
- `app-modules/products/src/Filament/Resources/Products/ProductResource.php`
- `app-modules/picklists/src/Filament/Resources/Picklists/PicklistResource.php`
  (keep its unrelated `->default([])` table fix untouched — not part of Notes)
- `app-modules/clients/src/Filament/Resources/Clients/ClientResource.php`

## 8. Retire the standalone page

**Delete:**
- `app/Filament/Pages/Notes.php` — the old standalone, non-Resource page;
  `/notes` is now `NoteResource`'s `ListNotes` page instead, still a feed,
  not a table.

**`app/Providers/Filament/AdminPanelProvider.php`, modified:**
- Remove `Notes::class` from the `->pages([Dashboard::class, WarehouseMap::class, Notes::class])`
  array (and its `use App\Filament\Pages\Notes;` import).
- Add a `->discoverResources(in: base_path('app-modules/notes/src/Filament/Resources'), for: 'Modules\Notes\Filament\Resources')`
  line, alongside the other six module `discoverResources()` calls already
  there (insert alphabetically, between the `clients` and `orders` lines).

## 9. Testing

`tests/Feature/NotesResourceTest.php` (replaces the old
`tests/Feature/NotesPageTest.php`), same tenant-context-helper shape as
`tests/Feature/WarehouseMapTest.php`:

- **General post from `/notes`**: `Livewire::test(ListNotes::class)->fillForm(['body' => 'Stock arrived'])->call('postMessage')->assertOk()`,
  then assert a `Note` exists with the tenant's `warehouse_id`, the acting
  user's `user_id`, and `subject_type`/`subject_id` both `null`.
- **Linked post from `/notes`**: same, but filling `subject_type`/`subject_id`
  (the `MorphToSelect`'s two underlying columns — not a nested `subject` key)
  with an `Order`'s class/key; assert the created `Note`'s
  `subject_id`/`subject_type` match that order.
- **Rejects an empty message**: `fillForm(['body' => ''])->call('postMessage')->assertHasFormErrors(['body' => 'required'])`.
- **Per-record post**: for all five resources (Order/PurchaseOrder/Product/
  Picklist/Client), `Livewire::test(Manage<X>Notes::class, ['record' => $record->getKey()])->fillForm(['body' => 'Called the client'])->call('postMessage')->assertOk()`,
  then assert the created `Note`'s `subject_type`/`subject_id` match `$record`
  and `warehouse_id` is still set correctly (exercises the `NoteObserver`
  change in §3 — nothing on this page path ever mentions `warehouse_id`).
- **Subject link + label**: assert `NoteResource::getSubjectUrl()` links an
  Order-subject note to `OrderResource::getUrl('edit', ...)` and a
  Picklist-subject note to `PicklistResource::getUrl('view', ...)`, and
  `NoteResource::getSubjectNotesUrl()` links both to their resource's
  `'notes'` route instead — this is the one place a wrong route name fails
  silently (a broken link, not an exception), so it needs an explicit
  assertion, not just "the page renders."
- **`/notes` feed shows subject links**: create a general note and a
  Client-linked note; assert `Livewire::test(ListNotes::class)->assertOk()->assertSee(...)`
  sees both message bodies and the subject's `getActivityLogTitle()`.
- **A record's own feed shows only its own notes**: create a note on an
  Order, a note on a *different* Order, and a general board note; assert
  `Livewire::test(ManageOrderNotes::class, ['record' => $order->getKey()])`
  sees the first and not the other two.
- **Date grouping, newest-first**: two notes on different days render in two
  groups, today's group before an earlier one; two notes on the same day
  render with the later one first. No `loadTable()`/`assertSeeInOrder()`
  caveats apply anywhere in this feature — none of these pages are a
  deferred-loading Filament table, every one is a plain Blade `@foreach`.
- **Tenant scoping**: two warehouses, one `Note` in each (one of them
  subject-less, one linked to a record in that warehouse); assert
  `Note::query()->pluck('body')` under warehouse A's tenant context contains
  only warehouse A's notes.
- **Resource wiring**: `OrderResource::getPages()`/`PicklistResource::getPages()`/
  `ClientResource::getPages()` each have a `'notes'` key.

## File-by-file checklist

**New:**
- `app-modules/notes/database/migrations/2026_10_21_000000_add_subject_to_notes_table.php`
- `app-modules/notes/src/Models/Concerns/HasNotes.php`
- `app-modules/notes/src/Filament/Resources/Notes/NoteResource.php`
- `app-modules/notes/src/Filament/Resources/Notes/Pages/ListNotes.php`
- `app-modules/notes/src/Filament/Pages/Concerns/HasNotesFeed.php`
- `resources/views/filament/pages/notes.blade.php`
- `resources/views/filament/pages/record-notes.blade.php`
- `app-modules/orders/src/Filament/Resources/Orders/Pages/ManageOrderNotes.php`
- `app-modules/orders/src/Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrderNotes.php`
- `app-modules/products/src/Filament/Resources/Products/Pages/ManageProductNotes.php`
- `app-modules/picklists/src/Filament/Resources/Picklists/Pages/ManagePicklistNotes.php`
- `app-modules/clients/src/Filament/Resources/Clients/Pages/ManageClientNotes.php`
- `tests/Feature/NotesResourceTest.php`

**Modified:**
- `app-modules/notes/src/Models/Note.php` — add `subject()`, repoint
  `$fillable`/searchable settings
- `app-modules/notes/src/Observers/NoteObserver.php` — also default
  `warehouse_id` from the tenant (§3)
- `app-modules/orders/src/Models/Order.php`, `PurchaseOrder.php`,
  `app-modules/products/src/Models/Product.php`,
  `app-modules/picklists/src/Models/Picklist.php` — add `use HasNotes;` +
  `implements HasActivityLogTitle`
- `app-modules/clients/src/Models/Client.php` — add `use HasNotes;`
- `app-modules/orders/src/Filament/Resources/Orders/OrderResource.php`,
  `.../PurchaseOrders/PurchaseOrderResource.php`,
  `app-modules/products/src/Filament/Resources/Products/ProductResource.php`,
  `app-modules/picklists/src/Filament/Resources/Picklists/PicklistResource.php`,
  `app-modules/clients/src/Filament/Resources/Clients/ClientResource.php` —
  add sub-nav entry + `getPages()` `'notes'` route
- `app/Providers/Filament/AdminPanelProvider.php` — drop the `Notes::class`
  page, add the `Modules\Notes` resource discovery line
- `blueprints/wms.md` — update the one-line Notes description in the Module
  Blueprints index

**Deleted:**
- `app/Filament/Pages/Notes.php`
- `tests/Feature/NotesPageTest.php`

After implementation, run the mandatory checks (`php artisan lang:extract`;
`vendor/bin/phpstan analyse` and `vendor/bin/pint --dirty --format agent`) and
the new test (`php artisan test --compact tests/Feature/NotesResourceTest.php`).
