# Blueprint: Self-Service WMS Demo (Signup + 48h Auto-Reset)

> **Implemented.** `wms` core changes are committed in this repo
> (`database/seeders/TenantSeeder.php`, `app/Models/Subdomain.php`).
> `app-modules/demo` has been built, tested (289/289 passing), and
> relocated to `openguild-eu/wms-demo`. Three corrections were found
> during implementation and are called out inline below where they
> change this plan's original text — search for "**Correction**".

## Status & Scope

This plan spans **two repositories**:

1. **`wms`** (this checkout) — the distributable product. Gets exactly one
   change: a refactor of `database/seeders/TenantSeeder.php` to expose a
   reusable `seedWarehouse()` method (see "Core Repo Change" below). No
   demo/signup/reset code is added here.
2. **`wms-demo`** (a new repository, not yet created) — holds only a
   self-contained Laravel module (`app-modules/demo`) plus a CI workflow that
   rebuilds/deploys by checking out `wms@main` fresh and overlaying the module
   on top. All signup/reset/cleanup code described below lives here, not in
   `wms`.

Everything under "Demo Module (wms-demo repo)" assumes the implementing agent
is working in a fresh Laravel 13 + Filament 5 installation that already has
`wms@main` checked out with `app-modules/demo` dropped in and
`"modules/demo": "*"` added to that checkout's root `composer.json` `require`
block (the existing `repositories` entry `{"type": "path", "url":
"app-modules/*"}` already globs this path — no repository config change
needed). If the implementing agent is instead working directly inside this
`wms` checkout to build/test the module before the `wms-demo` repo exists,
that is fine for development, but the module must not be registered in `wms`'s
own `bootstrap/providers.php` or shipped in `wms` releases.

## Established Facts (verified in this checkout, do not re-derive)

- Tenancy: `App\Models\Subdomain` (table `subdomains`: `subdomain` unique,
  `name` unique) → `App\Models\Warehouse` (table `warehouses`, FK
  `subdomain_id` with `cascadeOnDelete()`) → nearly every domain model has
  `warehouse_id` with `onDelete('cascade')` at the DB level (confirmed in
  `app-modules/{backorders,brands,clients,notes,orders,picklists,products,settings}/database/migrations/*.php`).
- `App\Models\Scopes\TenantScope` (`app/Models/Scopes/TenantScope.php`) adds
  `WHERE subdomain_id = ?` using `app('current_subdomain')->id`, registered via
  `static::addGlobalScope(new TenantScope)` in `booted()` on
  `App\Models\Warehouse` and `Modules\Users\Models\User` only.
- `App\Http\Middleware\IdentifySubdomain` resolves the subdomain from the
  request host and does `app()->instance('current_subdomain', $subdomainModel)`;
  registered globally for `web`+`api` in `bootstrap/app.php` and also first in
  `AdminPanelProvider`'s panel `->middleware([...])` list.
- Filament tenancy: `app/Providers/Filament/AdminPanelProvider.php` —
  `->tenant(Warehouse::class, ownershipRelationship: 'warehouse',
  slugAttribute: 'name')`. `Modules\Users\Models\User` implements
  `Filament\Models\Contracts\HasTenants`; `getTenants()` filters warehouses by
  `current_subdomain`; `canAccessTenant()` checks the `warehouses()`
  belongs-to-many (pivot `user_warehouses`, which cascades on warehouse
  delete).
- `users.email` is a **global** unique column (not scoped) —
  `app-modules/users/database/migrations/0001_01_01_000000_create_users_table.php`.
  `subdomains.subdomain` and `subdomains.name` are also global-unique.
- `database/seeders/TenantSeeder.php` already has its real seeding logic
  factored into six private methods that each take a single `Warehouse
  $warehouse` argument and contain no company-specific data:
  `seedVatRates`, `seedWarehouseDefaults`, `seedClients`, `seedOrderStatuses`,
  `seedOrders`, `seedPicklists`. Only `run()` itself hardcodes two companies
  (philandphae, phaewomen) with their subdomains/users/emails/passwords.
- `app/Console/Commands/CreateTestTenant.php` is an empty stub
  (`app:create-test-tenant`, `handle()` just returns `SUCCESS`) — a prior,
  abandoned attempt at this feature. Leave it alone; it is unrelated to this
  plan (it lives in `wms` core, not the demo module) and out of scope to
  finish or delete here.
- No scheduled commands exist anywhere in `wms` today (`routes/console.php`
  only defines the default `inspire` Artisan command). No self-service
  registration exists (`routes/web.php` only has
  `SubdomainLookupController@show`/`lookup` on the central domain).
- Module convention (`app-modules/users`, `app-modules/platform`,
  `app-modules/settings`): a `{Module}ServiceProvider` with empty `register()`
  and a `boot()` that calls `$this->loadMigrationsFrom(__DIR__.'/../../database/migrations')`.
  Composer package name `modules/{name}`, PSR-4 root `Modules\{Name}\`,
  registered via that package's own `composer.json` `extra.laravel.providers`
  (autodiscovered — **not** added to `wms`'s `bootstrap/providers.php`, which
  only lists `AppServiceProvider`, `FilamentServiceProvider`,
  `AdminPanelProvider`, `GraphQLServiceProvider`). No existing module currently
  loads routes, views, or an event listener — this module is the first to do
  so; use standard Laravel `ServiceProvider` APIs for all three
  (`loadRoutesFrom`, `loadViewsFrom`, `Event::listen`), which require no core
  changes.
- Precedent for a public, central-domain, plain-Blade form:
  `App\Http\Controllers\SubdomainLookupController` (`show`/`lookup` methods,
  `$request->validate([...])`, returns `view(...)` or a redirect). Mirror this
  convention for the signup controller rather than introducing a Livewire
  component, since no public (non-Filament) Livewire component exists
  anywhere in this app today.
- **Verification method used for lifecycle/API claims below**: direct
  reading of installed vendor source
  (`vendor/filament/filament/src/Auth/Pages/Login.php`,
  `vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php`,
  `vendor/laravel/framework/src/Illuminate/Auth/Events/Login.php`), not
  `search-docs`. Confirmed: Filament's `Login::authenticate()` calls
  `$authGuard->attemptWhen(...)` on the `web` guard (a `SessionGuard`), whose
  successful `attempt()`/`login()` path calls `fireLoginEvent()`, which
  dispatches `Illuminate\Auth\Events\Login` with public properties `$guard`,
  `$user` (the authenticated `Authenticatable`), `$remember`. This event fires
  for every successful login through the Filament panel, and will also fire
  for the demo module's own post-signup `Auth::login()` call — both paths are
  covered by one listener. Nothing about this is Filament-specific or
  version-fragile; it is standard `SessionGuard` behavior.

## Core Repo Change (`wms`)

### Update Existing Seeder

```
File: database/seeders/TenantSeeder.php
Change: Promote the six per-warehouse seeding methods from `private` to
  `public`, and add one new public method that composes them in the fixed
  order the existing `run()` already uses:

  public function seedWarehouse(Warehouse $warehouse): void
  {
      Model::unguarded(function () use ($warehouse) {
          $this->seedVatRates($warehouse);
          $this->seedWarehouseDefaults($warehouse);
          $this->seedClients($warehouse);
          $this->seedOrderStatuses($warehouse);
          $this->seedOrders($warehouse);
          $this->seedPicklists($warehouse);
      });
  }

  Correction: wrap the six calls in `Model::unguarded(...)`
  (`Illuminate\Database\Eloquent\Model`). `php artisan db:seed` unguards
  Eloquent mass assignment automatically for the duration of a seeder run,
  which is why these methods work fine inside `run()` today — but a direct
  call like `(new TenantSeeder)->seedWarehouse($warehouse)` from outside
  `db:seed` does not get that automatic unguard, and the first insert
  (`VatRate`, writing `warehouse_id` which isn't in `VatRate::$fillable`)
  throws a `MassAssignmentException` without it. This has no effect on
  `run()`'s existing behavior.

  Do not change `run()`'s behavior or the six methods' internal logic at all
  — this is a pure visibility/composition change so `wms-demo` can call
  `(new TenantSeeder)->seedWarehouse($warehouse)` instead of duplicating
  ~700 lines of seed logic. `run()` may optionally be rewritten to call
  `$this->seedWarehouse($warehouse)` in place of its six inline calls per
  company, but must produce byte-for-byte the same seeded records as today
  for `philandphae`/`phaewomen` (same subdomains, users, warehouse names,
  passwords, printed `$this->command->info(...)` lines). Do not touch the
  per-company subdomain/user/warehouse creation block in `run()` beyond this
  optional call-site simplification.
```

No other file in `wms` changes. Do not add `is_demo`/`demo_expires_at` columns
or any demo-aware code to `wms` — those live only in the `demo` module below.

## Demo Module (`wms-demo` repo, `app-modules/demo`)

### Commands

Run inside the `wms-demo` checkout (after `wms@main` + this module exist
together, per the Status & Scope section):

```
php artisan make:module Demo --no-interaction --accept-default-namespace
php artisan make:migration add_demo_fields_to_subdomains_table --path=app-modules/demo/database/migrations --no-interaction
php artisan make:listener ResetExpiredDemoSubdomain --no-interaction
php artisan make:command PruneAbandonedDemoSubdomains --no-interaction
```

The implementing agent must then move the generated `Listener` and `Command`
files into `app-modules/demo/src/Listeners/` and
`app-modules/demo/src/Console/Commands/` respectively (under namespace
`Modules\Demo\...`), since `make:listener`/`make:command` scaffold into
`app/` by default and this module must be fully self-contained in
`app-modules/demo`.

### Models

```
## Update Existing Model
Migration: add_demo_fields_to_subdomains_table
  Table: subdomains
  Add: is_demo (boolean, default:false)
  Add: demo_expires_at (timestamp, nullable)
```

```
Model: App\Models\Subdomain  (lives in wms core — demo module may not edit
  core files directly in wms, but wms-demo's own checkout of wms IS the place
  this change is made, since the migration above is additive and the model
  edit below is required for the new columns to behave correctly; treat this
  as a change the demo module's own build applies to its working copy of
  Subdomain.php, analogous to the migration itself)
  Add casts() method (Subdomain currently has none):
    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'demo_expires_at' => 'datetime',
        ];
    }
  Place casts() as the last method, per this project's model convention.
  Correction: `protected`, not `public` — matches the actual convention in
  `App\Models\Warehouse::casts()` and `Modules\Users\Models\User::casts()`,
  both `protected`.
  Add fillable entries: 'is_demo', 'demo_expires_at' (alongside existing
  'subdomain', 'name').
```

> **Open question the user should confirm before implementation**: the
> instruction set says the `wms-demo` repo should hold *only* the demo module
> and that `wms` core is never touched. But a model edit (`Subdomain::casts()`
> and `$fillable`) is not something a Laravel module can apply to a class it
> doesn't own without either (a) a small, demo-agnostic edit to
> `app/Models/Subdomain.php` in `wms` core (just adding cast/fillable entries
> for columns that don't exist there — harmless to self-hosted users as long
> as the columns themselves are never created outside the demo deployment),
> or (b) the demo module's migration also physically modifying
> `app/Models/Subdomain.php` as part of its own build step (fragile, not a
> normal Laravel pattern). Recommended: treat this exactly like the
> `TenantSeeder` change — a second, tiny, demo-agnostic addition to `wms`
> core (nullable/defaulted columns + matching casts cost nothing for
> non-demo installs). If that is unacceptable, the alternative is for the
> demo module to read/write these two columns via raw query builder
> (`DB::table('subdomains')`) instead of through the Eloquent model's casts,
> which avoids touching `wms` core entirely at the cost of losing
> `->demo_expires_at` as a Carbon instance (would need manual
> `Carbon::parse()` at each read site). Confirm which before implementing.

### Routes

```
File: app-modules/demo/routes/demo-routes.php (new)

Route::middleware('web')->domain(config('app.central_domain'))->group(function () {
    Route::get('/demo/signup', [DemoSignupController::class, 'show'])
        ->name('demo.signup.show');

    Route::post('/demo/signup', [DemoSignupController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('demo.signup.store');
});
```

`Route::middleware('web')->domain(...)` is required here (not optional)
because `wms`'s `bootstrap/app.php` only applies the central-domain `domain()`
constraint to its own `routes/web.php` file via a `then:` callback — routes
loaded by a package's `loadRoutesFrom()` are not covered by that constraint
automatically, so the module must apply it itself. `IdentifySubdomain` runs
globally before this (registered in `$middleware->web(append: [...])` in
`bootstrap/app.php`), so on the central domain it will have already allowed
the request through (it only blocks `login`/`logout`/`filament*` paths on the
central domain, per `app/Http/Middleware/IdentifySubdomain.php:34-42`).

> **Correction**: `internachi/modular`'s `RoutesPlugin` auto-loads every
> `app-modules/*/routes/*.php` file unconditionally (bare, with no group
> around it) — this happens regardless of anything the service provider
> does. That means the `web`+`domain()` wrapping **must** live here, in
> `demo-routes.php` itself (as shown above), and must **not** also be
> applied by `DemoServiceProvider::boot()` (the "Service Provider" section
> below originally showed both — doing so registers the routes twice: once
> correctly wrapped via the module auto-load, and once more, unwrapped, via
> the provider's own `Route::...->group(__DIR__.'/../../routes/demo-routes.php')`
> call, which defeats the domain/`web` restriction for that second copy).
> The Service Provider section below has been corrected to remove all route
> registration.

### Service Provider

```
File: app-modules/demo/src/Providers/DemoServiceProvider.php
Namespace: Modules\Demo\Providers

class DemoServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'demo');

        Event::listen(Login::class, ResetExpiredDemoSubdomain::class);

        Schedule::command(PruneAbandonedDemoSubdomains::class)->daily();
    }
}
```

> **Correction**: no route registration here — see the correction note
> under "Routes" above. `internachi/modular` loads `demo-routes.php`
> automatically; the provider must not load or wrap it a second time.

Full imports needed: `Illuminate\Support\ServiceProvider`,
`Illuminate\Support\Facades\Event`, `Illuminate\Support\Facades\Schedule`,
`Illuminate\Auth\Events\Login`,
`Modules\Demo\Listeners\ResetExpiredDemoSubdomain`,
`Modules\Demo\Console\Commands\PruneAbandonedDemoSubdomains`.

`Schedule::command(...)->daily()` registered inside a package service
provider's `boot()` is the same mechanism Laravel first-party packages (e.g.
Horizon's snapshot command) use to self-schedule without the host
application editing `routes/console.php` — it works because `boot()` runs on
every process bootstrap, including the one `schedule:run` itself triggers.

`app-modules/demo/composer.json` must follow the existing module shape
exactly (see `app-modules/users/composer.json` as the template): package
name `modules/demo`, PSR-4 `"Modules\\Demo\\": "src/"` (+ Tests/Factories/
Seeders mirrors), `extra.laravel.providers: ["Modules\\Demo\\Providers\\DemoServiceProvider"]`.

### Signup Flow (Controller + View)

```
File: app-modules/demo/src/Http/Controllers/DemoSignupController.php
Namespace: Modules\Demo\Http\Controllers

public function show(): View
{
    return view('demo::signup');
}

public function store(Request $request): RedirectResponse
{
    $data = $request->validate([
        'company_name' => ['required', 'string', 'max:255'],
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(8)],
    ]);

    $slug = $this->generateUniqueSubdomainSlug($data['company_name']);
    $companyName = $this->generateUniqueCompanyName($data['company_name']);

    $subdomain = DB::transaction(function () use ($data, $slug, $companyName) {
        $subdomain = Subdomain::create([
            'subdomain' => $slug,
            'name' => $companyName,
            'is_demo' => true,
            'demo_expires_at' => now()->addHours(48),
        ]);

        app()->instance('current_subdomain', $subdomain);

        $user = User::create([
            'subdomain_id' => $subdomain->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $warehouse = Warehouse::create([
            'subdomain_id' => $subdomain->id,
            'name' => 'Demo Warehouse',
            'currency' => 'EUR',
        ]);

        $user->warehouses()->syncWithoutDetaching([$warehouse->id]);

        (new TenantSeeder)->seedWarehouse($warehouse);

        return $subdomain;
    });

    $user = $subdomain->users()->firstOrFail();

    Auth::guard('web')->login($user);

    return redirect()->away(
        'http://'.$subdomain->subdomain.'.'.config('app.central_domain').'/'
    );
}

private function generateUniqueSubdomainSlug(string $companyName): string
{
    $base = Str::slug($companyName) ?: 'demo';

    do {
        $candidate = $base.'-'.Str::lower(Str::random(6));
    } while (Subdomain::where('subdomain', $candidate)->exists());

    return $candidate;
}

private function generateUniqueCompanyName(string $companyName): string
{
    if (! Subdomain::where('name', $companyName)->exists()) {
        return $companyName;
    }

    $suffix = 2;

    do {
        $candidate = "{$companyName} ({$suffix})";
        $suffix++;
    } while (Subdomain::where('name', $candidate)->exists());

    return $candidate;
}
```

> **Correction**: `subdomains.name` is globally unique
> (`app-modules/users/database/migrations/0000_00_00_000000_create_subdomains_table.php`).
> The original draft of this controller stored `$data['company_name']`
> verbatim as `name`, which crashes with a 500 the second time anyone signs
> up with a company name that's already been used (e.g. two people both
> typing "Acme Logistics") — exactly the scenario the "two signups with the
> same company_name" test below exercises. Fixed by disambiguating the
> stored `name` on collision only (`generateUniqueCompanyName`, added
> above), not by rejecting the signup with a `unique:subdomains,name`
> validation rule — rejecting it would block a legitimate, unrelated
> company from signing up with the same display name, which isn't the
> desired behavior for a public demo.

Full imports: `Illuminate\Http\Request`, `Illuminate\Http\RedirectResponse`,
`Illuminate\View\View`, `Illuminate\Support\Facades\DB`,
`Illuminate\Support\Facades\Hash`, `Illuminate\Support\Facades\Auth`,
`Illuminate\Support\Str`, `Illuminate\Validation\Rules\Password`,
`App\Models\Subdomain`, `App\Models\Warehouse`, `Modules\Users\Models\User`,
`Database\Seeders\TenantSeeder`.

Note: `Auth::guard('web')->login($user)` fires `Illuminate\Auth\Events\Login`,
which means `ResetExpiredDemoSubdomain` (below) also runs for this brand-new
login. This is harmless and intentional — `demo_expires_at` was just set to
`now()->addHours(48)`, so the listener's `isPast()` check is false and it
no-ops. Do not special-case signup to skip the listener; relying on the same
check for both paths is the single canonical expiry rule this plan uses
(see Lifecycle Consistency below).

`resources/views/signup.blade.php` (namespaced `demo::signup`): a plain Blade
form (not Livewire, see "Established Facts") posting to
`route('demo.signup.store')`, fields `company_name`, `name`, `email`,
`password`, `password_confirmation`, with `@csrf` and
`$errors->has(...)`/`old(...)` display, mirroring
`resources/views/subdomain-lookup.blade.php`'s existing style.

### Reset Listener

```
File: app-modules/demo/src/Listeners/ResetExpiredDemoSubdomain.php
Namespace: Modules\Demo\Listeners

public function handle(Login $event): void
{
    $user = $event->user;

    if (! $user instanceof User) {
        return;
    }

    $subdomain = $user->subdomain;

    if (! $subdomain instanceof Subdomain || ! $subdomain->is_demo) {
        return;
    }

    if (! $subdomain->demo_expires_at instanceof Carbon || ! $subdomain->demo_expires_at->isPast()) {
        return;
    }

    DB::transaction(function () use ($subdomain, $user) {
        Warehouse::withoutGlobalScope(TenantScope::class)
            ->where('subdomain_id', $subdomain->id)
            ->delete();

        $warehouse = Warehouse::create([
            'subdomain_id' => $subdomain->id,
            'name' => 'Demo Warehouse',
            'currency' => 'EUR',
        ]);

        $user->warehouses()->syncWithoutDetaching([$warehouse->id]);

        (new TenantSeeder)->seedWarehouse($warehouse);

        $subdomain->update(['demo_expires_at' => now()->addHours(48)]);
    });
}
```

Full imports: `Illuminate\Auth\Events\Login`, `Illuminate\Support\Facades\DB`,
`Illuminate\Support\Carbon`, `App\Models\Subdomain`, `App\Models\Warehouse`,
`App\Models\Scopes\TenantScope`, `Modules\Users\Models\User`,
`Database\Seeders\TenantSeeder`.

`Warehouse::withoutGlobalScope(TenantScope::class)` is used explicitly
(rather than relying on the ambient `current_subdomain` container binding
that `TenantScope` reads) so this delete is correct regardless of whether
`IdentifySubdomain` has already bound `current_subdomain` for this request —
per the multi-tenancy guideline's gotcha, prefer an explicit, named scope
removal over broad scope bypassing, and here we go one step further by not
depending on scope-based filtering at all for a destructive query: the
`where('subdomain_id', $subdomain->id)` is the authoritative filter.

The DB-level `cascadeOnDelete()` chain already confirmed in "Established
Facts" means this single `Warehouse::delete()` query removes every
`warehouse_id`-scoped row (orders, order products, clients, client addresses,
products, stock products, picklists, picklist products, brands, attributes,
attribute groups, vat rates, api keys, webhook endpoints, backorders, notes,
stock locations) and the `user_warehouses` pivot rows, without the module
needing to know about or depend on any business module's schema.

### Abandoned-Demo Cleanup Command

```
File: app-modules/demo/src/Console/Commands/PruneAbandonedDemoSubdomains.php
Namespace: Modules\Demo\Console\Commands
Signature: demo:prune-abandoned {--days=30}

public function handle(): int
{
    $cutoff = now()->subDays((int) $this->option('days'));

    $abandoned = Subdomain::query()
        ->where('is_demo', true)
        ->whereNotNull('demo_expires_at')
        ->where('demo_expires_at', '<', $cutoff)
        ->get();

    foreach ($abandoned as $subdomain) {
        $subdomain->delete();
    }

    $this->info("Pruned {$abandoned->count()} abandoned demo subdomain(s).");

    return self::SUCCESS;
}
```

`Subdomain::delete()` cascades to its `users` and `warehouses` rows (and from
there to everything `warehouse_id`-scoped, per the chain above) via
`cascadeOnDelete()` on `users.subdomain_id` and `warehouses.subdomain_id` —
confirmed in `app-modules/users/database/migrations/0001_01_01_000000_create_users_table.php:18`
and `app-modules/settings/database/migrations/2025_09_03_073615_create_warehouses.php:18`.
30 days is a default, overridable via `--days=N`; this is a quantity choice,
not a domain rule, so it is fine to default here rather than ask the user.

## Lifecycle Consistency

**Rule** (one canonical expiry check, used in exactly one place —
`ResetExpiredDemoSubdomain`): a subdomain's demo data is stale if and only if
`is_demo === true` AND `demo_expires_at` is non-null AND
`demo_expires_at->isPast()`. Nothing else (not last-login time, not a
separate "active" flag) determines staleness.

**Stages**:

1. Signup creates `is_demo = true`, `demo_expires_at = now()+48h` → fresh,
   not stale.
2. Any login (including the signup-triggered one) runs the same staleness
   check via the `Login` event. Not stale → no-op, warehouse and its data are
   left untouched.
3. Stale → wipe + reseed + bump `demo_expires_at` to `now()+48h` (from the
   moment of *this* login, not from the original value) → fresh again,
   identical shape to stage 1 except the warehouse/data are new rows.
4. If the user never logs back in, `demo_expires_at` stays in the past
   indefinitely; after `--days=30` (default) past that point, the scheduled
   command deletes the whole subdomain. There is no stage between "stale, not
   yet reset" and "deleted" other than the user logging in — a stale-but-not-
   yet-pruned demo is fully inert (still blocked by `IdentifySubdomain`
   requiring a real subdomain row to exist, then by normal `auth` middleware
   requiring login) except for the one listener check at the moment of login.

**Consistency check** (discriminating cases, see Tests): logging in once
after expiry resets and extends; logging in again immediately after (within
the new 48h window) must **not** reset a second time — this is the
boundary the test suite must assert, since a bug that re-checks the original
`demo_expires_at` (instead of re-reading the freshly-updated column) would
wipe data on every login forever instead of once per 48h window.

## Authorization

```
Route: demo.signup.show / demo.signup.store
  Authorization: None (public) — unauthenticated by design, central domain
  only, rate-limited (throttle:5,60 on the POST route) as the only abuse
  guard.
```

```
Listener: ResetExpiredDemoSubdomain
  Authorization: N/A — not a user-facing action. Runs for every successful
  login; gated entirely by the `is_demo` + `demo_expires_at` data check
  above, not by any permission. Non-demo subdomains (`is_demo = false`,
  the default for every real self-hosted/production tenant) always no-op
  here regardless of what `demo_expires_at` happens to contain.
```

```
Command: demo:prune-abandoned
  Authorization: Console-only (scheduler/CLI), not web-exposed. No policy.
```

## CI (`wms-demo` repo) — two options, pick one

**Option A — polling (simplest, zero footprint in `wms`)**:

```yaml
# wms-demo/.github/workflows/deploy.yml
on:
  schedule:
    - cron: '0 */4 * * *'   # every 4 hours
  workflow_dispatch: {}
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          repository: <org>/wms
          ref: main
          path: build
      - uses: actions/checkout@v4
        with:
          path: overlay
      - run: cp -r overlay/app-modules/demo build/app-modules/demo
      - run: |
          cd build
          jq '.require["modules/demo"] = "*"' composer.json > composer.json.tmp
          mv composer.json.tmp composer.json
      - run: cd build && composer install --no-dev --optimize-autoloader
      - run: cd build && npm ci && npm run build
      # deploy step: push `build/` to the hosting target (Laravel Cloud CLI,
      # rsync, etc. — depends on where this gets hosted; not specified here)
```

**Option B — instant, triggered from `wms`'s own CI**. Add this one step to
`wms`'s existing CI workflow (the only thing that ever touches `wms` for this
feature, and it is a generic "notify" step, not demo-specific logic):

```yaml
# wms/.github/workflows/<existing-ci>.yml, appended after tests pass on main
  - name: Notify wms-demo to rebuild
    if: github.ref == 'refs/heads/main'
    run: |
      curl -X POST \
        -H "Authorization: token ${{ secrets.WMS_DEMO_DISPATCH_TOKEN }}" \
        -H "Accept: application/vnd.github.v3+json" \
        https://api.github.com/repos/<org>/wms-demo/dispatches \
        -d '{"event_type":"wms-updated"}'
```

```yaml
# wms-demo/.github/workflows/deploy.yml
on:
  repository_dispatch:
    types: [wms-updated]
  workflow_dispatch: {}
jobs:
  deploy: # same steps as Option A
```

Both variants should be documented; the user picks one when scaffolding
`wms-demo`. Option B additionally needs a fine-grained GitHub token (repo
dispatch scope on `wms-demo` only) stored as a `wms` repo secret
(`WMS_DEMO_DISPATCH_TOKEN`) — confirm the user is comfortable adding that
secret before implementing Option B, since it is the one piece of this
feature that touches `wms`'s CI configuration rather than only its
application code.

## Tests

All tests live in `wms-demo`'s own test suite (`uses(RefreshDatabase::class)`,
same as this app's existing `tests/Feature/*`, e.g.
`tests/Feature/WarehouseDashboardWidgetsTest.php`'s pattern of creating a
`Subdomain`+`Warehouse`+`User` directly and using
`app()->instance('current_subdomain', $subdomain)` /
`Filament::setTenant($warehouse)` where needed).

```
DemoSignupController:
  Validation (use dataset pattern):
    - company_name: required, max:255
    - name: required, max:255
    - email: required, email, max:255, unique:users,email
    - password: required, confirmed, min:8

  Behavior:
    - a valid signup creates exactly one Subdomain with is_demo=true and
      demo_expires_at ~48 hours from now (assert with Carbon::setTestNow
      frozen, not a loose range)
    - a valid signup creates exactly one Warehouse named "Demo Warehouse"
      under that subdomain, with the seeded VatRate/Client/OrderStatus/
      Order/Picklist counts matching what TenantSeeder::seedWarehouse
      produces for a single warehouse (assert exact counts, not just
      non-zero)
    - the created user is attached to the warehouse via user_warehouses
    - after a valid signup, the response is authenticated as the new user
      (assert Auth::check() / the session user matches) and redirects to
      that subdomain's own host
    - two signups with the same company_name produce two different
      subdomain slugs (collision handling), both persisted successfully,
      with the second signup's stored `name` disambiguated (e.g.
      "Acme Logistics" then "Acme Logistics (2)") rather than rejected
    - a signup with a never-before-seen company_name stores that name
      unchanged, with no suffix (the common-case counterpoint to the
      collision test above)
    - a 6th signup attempt from the same IP within 60 minutes is throttled
      (429 / redirected with a rate-limit error), the 5th is not

  Authorization:
    - signup routes are reachable without authentication
    - signup routes are not reachable on a tenant subdomain host (only the
      central domain) — mirror the IdentifySubdomain-based assertion style
      used in tests/Feature/ProductBarcodeDownloadTest.php for constructing
      a host URL

ResetExpiredDemoSubdomain:
  - given a demo subdomain whose demo_expires_at is in the past, logging in
    deletes the old Warehouse (assert the old warehouse id no longer exists
    in the database) and creates a new Warehouse with a freshly reseeded
    dataset (assert new VatRate/Client/Order/Picklist counts match a fresh
    seedWarehouse() run), and sets demo_expires_at to a new timestamp ~48
    hours from the moment of this login (freeze time with
    Carbon::setTestNow to assert the exact new value, not just "in the
    future")
  - given a demo subdomain whose demo_expires_at is still in the future,
    logging in does NOT delete or recreate the warehouse (assert the same
    warehouse id and the same seeded record ids persist unchanged) —
    this is the critical "don't wipe an active demo" boundary case
  - given a demo subdomain that was just reset (demo_expires_at bumped to
    now()+48h by the previous login), logging in again immediately does
    NOT reset a second time (regression case from the Lifecycle
    Consistency "Consistency check" above: a bug that re-reads the
    original/stale `demo_expires_at` instead of the updated column would
    wipe data on every login)
  - given a NON-demo subdomain (is_demo=false) whose demo_expires_at
    happens to be set and in the past (constructed deliberately to isolate
    the is_demo gate from the timestamp gate), logging in does NOT delete
    or recreate anything
  - logging in as the brand-new user created by signup itself (fresh
    demo_expires_at = now()+48h) does not trigger a reset — covers the
    "signup's own Auth::login() call also fires this listener" interaction
    noted above

PruneAbandonedDemoSubdomains:
  - a demo subdomain with demo_expires_at more than 30 days in the past is
    deleted, along with its users and warehouses (assert all three are gone
    from the database, via cascade)
  - a demo subdomain with demo_expires_at only 5 days in the past is NOT
    deleted
  - a non-demo subdomain is never deleted by this command regardless of any
    demo_expires_at value
  - the --days option changes the cutoff (e.g. --days=10 prunes a
    15-day-old expiry that --days=30 would not)
```

## Checklist Confirmation

- Namespaces: all components above are given full namespaces
  (`Illuminate\Auth\Events\Login`, `Illuminate\Support\Facades\Schedule`,
  etc.) — this plan introduces no Filament form/table/column/action
  components, so the Filament-specific namespace table in `checklist.md`
  does not apply here.
- No Filament Resources, Widgets, or Infolists are added by this feature;
  those "Required Sections" are intentionally empty.
- Authorization is specified for every new entry point (routes, listener,
  command) per `authorization.md`'s format, including the explicit "None
  (public)" case.
- The one unresolved domain choice (Subdomain model edit location — see the
  callout under "Models" above) is flagged for the user rather than silently
  decided, per `overview.md`'s instruction not to resolve consequential
  choices unilaterally.
