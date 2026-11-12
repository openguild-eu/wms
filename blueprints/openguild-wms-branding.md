# Blueprint: OpenGuild WMS Branding

## Goal

Rebrand the WMS Filament admin panel as **"OpenGuild WMS"**, replicating the
same in-house "OpenGuild" brand already integrated into the sibling project
`/Users/admin/www/pim` (panel brand name `OpenGuild PIM`). This is a
panel-configuration/theming task, not a CRUD feature — no models, resources,
or authorization changes are involved. Sections of the standard plan format
that don't apply (Models, Resources, Authorization, Widgets) are marked N/A.

OpenGuild is **not** a Composer/npm package. It is first-party brand
guideline material (Markdown doc + JSON tokens + SVG/PNG assets) plus manual
Filament `Panel` configuration. There is nothing to install.

## Source of Truth (already verified, do not re-derive)

Brand guideline lives at `/Users/admin/www/pim/resources/guideline/`:

- `BRAND-GUIDELINES.md` — voice/visual spec.
- `tokens.json` — design tokens. Relevant values:
  - `ink` `#0F1B3D` (navy — primary text/heading, dark-band background)
  - `ink-muted` `#4A5578`
  - `blue` `#2547D0` (eyebrow labels/links/info accents)
  - `amber` `#F2B134` (primary signal color — **fill only**, never text on light)
  - `radius-sm` 12px, `radius-md` 16px, `radius-lg` 20px, `radius-xl` 24px
  - Font: **Manrope** (weights 500/600/800), served from Google Fonts.
- `logos/` (8 SVGs + PNG exports). Per its own `README.md`:
  - `openguild-logo-color.svg` — full lockup, navy ink + amber keystone. For light/`surface` grounds.
  - `openguild-logo-white.svg` — full lockup, white pillars/text + amber keystone. For dark grounds.
  - `openguild-app-icon.svg` — white-and-amber mark on navy rounded square, exported as `openguild-app-icon-{32,180,512,1024}.png`. Used for favicon/app icons.
  - `openguild-wms-cube.svg` — the **WMS-specific** product mark (navy/blue faces, amber open lid), intended at 48px *above* the product name (e.g. a marketing/login hero), not as an inline sidebar logo. **Do not** use this as the Filament `brandLogo()` — it's a different layout pattern than the horizontal lockup Filament renders inline. Bring it in as a reference asset only.

PIM's concrete integration (the pattern this blueprint ports, verified against
installed Filament `v4.14.0` docs below):

```php
->brandName('OpenGuild PIM')
->brandLogo(fn () => view('filament.admin.brand-logo', [
    'logo' => asset('images/brand/openguild-logo-color.svg'),
    'textColor' => 'rgba(15, 27, 61, 0.6)',
]))
->darkModeBrandLogo(fn () => view('filament.admin.brand-logo', [
    'logo' => asset('images/brand/openguild-logo-white.svg'),
    'textColor' => 'rgba(255, 255, 255, 0.7)',
]))
->brandLogoHeight('2rem')
->favicon(asset('images/brand/openguild-app-icon-32.png'))
->colors([
    'primary' => '#F2B134',
    'info' => '#2547D0',
])
->font('Manrope', provider: \Filament\FontProviders\GoogleFontProvider::class)
->viteTheme('resources/css/filament/admin/theme.css')
```

With a Blade partial `resources/views/filament/admin/brand-logo.blade.php`
rendering the `<img>` logo plus a text suffix, using **inline styles** (not
Tailwind classes — this view's directory is outside the theme's `@source`
scan):

```blade
<span style="display: inline-flex; align-items: center; height: 100%; gap: 0.5rem;">
    <img src="{{ $logo }}" alt="OpenGuild" style="height: 100%; width: auto; display: block;" />
    <span style="font-size: 0.8125rem; font-weight: 600; line-height: 1; color: {{ $textColor }}; white-space: nowrap;">| pim</span>
</span>
```

## Verified API (Filament v4.14.0, via `search-docs`)

Docs: https://github.com/filamentphp/filament/blob/4.x/docs/08-styling/01-overview.md

- `brandLogo()` / `darkModeBrandLogo()` accept a closure returning a view, confirmed syntax: `->brandLogo(fn () => view('filament.admin.logo'))`.
- `brandLogoHeight()` confirmed.
- `favicon()` confirmed: `->favicon(asset('images/favicon.png'))`.
- `colors()` accepts a **plain hex string** per key — Filament generates the full shade palette for you: `->colors(['primary' => '#6366f1'])`. (Verified under "Generating a color palette" — this confirms PIM's `'primary' => '#F2B134'` usage is correct, not a shortcut that happens to work.)
- `font()` with Google Fonts: `use Filament\FontProviders\GoogleFontProvider; $panel->font('Inter', provider: GoogleFontProvider::class)`. Confirms the exact import path is `Filament\FontProviders\GoogleFontProvider` (note: **not** `Filament\Support\...`).
- `viteTheme()` + manual theme wiring confirmed under "Manual configuration": add the CSS path to `vite.config.js`'s `input` array, and `->viteTheme('resources/css/filament/admin/theme.css')`.
- `php artisan make:filament-theme {panel}` is the official scaffolding command — it generates `resources/css/filament/{panel}/theme.css`, and attempts to auto-wire `vite.config.js` and `->viteTheme()`. Use `--no-interaction` and prefer letting it attempt automatic wiring (see Commands below); if it reports it couldn't auto-configure (non-standard formatting is plausible here since both `vite.config.js` and the provider already have custom content), fall back to the manual edits specified in this blueprint.

## Current WMS State (already verified, do not re-derive)

Single panel provider: `app/Providers/Filament/AdminPanelProvider.php` (panel
id `admin`, path `/`). Full current content:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\WarehouseMap;
use App\Http\Middleware\IdentifySubdomain;
use App\Models\Warehouse;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->brandName('Open Warehouse Management System')
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make(),
            ])

            ->spa()
            ->tenant(Warehouse::class, ownershipRelationship: 'warehouse', slugAttribute: 'name')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->assets([
                Js::make('onscan', base_path('node_modules/onscan.js/onscan.min.js')),
            ])
            ->plugins([
                ActivityLogPlugin::make()
                    ->navigationGroup('Settings'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/brands/src/Filament/Resources'), for: 'Modules\Brands\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/clients/src/Filament/Resources'), for: 'Modules\Clients\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/notes/src/Filament/Resources'), for: 'Modules\Notes\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/orders/src/Filament/Resources'), for: 'Modules\Orders\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/picklists/src/Filament/Resources'), for: 'Modules\Picklists\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/products/src/Filament/Resources'), for: 'Modules\Products\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/settings/src/Filament/Resources'), for: 'Modules\Settings\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/users/src/Filament/Resources'), for: 'Modules\Users\Filament\Resources')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->pages([
                Dashboard::class,
                WarehouseMap::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Settings'),
            ])
            ->middleware([
                IdentifySubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

Every call **other than** `brandName()` and `colors()` must remain
byte-for-byte unchanged (tenancy, SPA, MFA, auth guard, discovered resources,
widgets, pages, navigation groups, middleware, auth middleware).

`vite.config.js` (full current content):

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/warehouse-map.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

No `resources/css/filament/` directory exists yet (no panel has ever been
themed). `resources/css/app.css` is the only existing CSS entry point
(Tailwind v4 CSS-first, no `tailwind.config.js`); it sets `--font-sans:
'Instrument Sans'` globally for the **non-Filament** frontend only — leave it
untouched, exactly as PIM left its own `app.css` (different font) untouched.

No `public/images/` directory exists yet. No `openguild` reference exists
anywhere in the WMS repo (fresh integration). No test asserts the string
`Open Warehouse Management System` (confirmed via repo-wide grep, excluding
an unrelated worktree) — no test changes are required for the brand name.

8 app-modules have Filament resources needing Tailwind `@source` coverage:
`brands`, `clients`, `notes`, `orders`, `picklists`, `products`, `settings`,
`users` — each at `app-modules/<module>/src/Filament`.

## Commands

Run first, in order:

```bash
php artisan make:filament-theme admin --no-interaction --skip-build
```

This should generate `resources/css/filament/admin/theme.css` and attempt to
wire `vite.config.js` + `->viteTheme()` automatically. **Check its output
carefully**: if it reports it could not auto-configure either file (likely,
since both already have non-trivial custom content), it will print manual
instructions — in that case apply the manual edits below to whichever file(s)
it didn't touch, and verify it did not duplicate an entry if it partially
succeeded. Do not run `npm run build` yet (`--skip-build` is intentional —
build only once all asset/content changes below are in place).

## Brand Assets to Bring Into WMS

1. Create `resources/guideline/` in the WMS repo (reference material, not
   application code — mirrors where PIM keeps its own copy) and copy from
   `/Users/admin/www/pim/resources/guideline/`:
   - `BRAND-GUIDELINES.md`
   - `tokens.json`
   - the entire `logos/` directory (all 21 files: 8 SVGs, their PNG exports,
     the 4 app-icon PNG sizes, and `logos/README.md`) — copy as-is, filenames
     unchanged.

2. Create `public/images/brand/` and copy these specific files from
   `/Users/admin/www/pim/resources/guideline/logos/` (same filenames):
   - `openguild-logo-color.svg`
   - `openguild-logo-white.svg`
   - `openguild-app-icon-32.png`
   - `openguild-app-icon-180.png`
   - `openguild-app-icon-512.png`
   - `openguild-app-icon-1024.png`
   - `openguild-wms-cube.svg` (brought in for future use — e.g. a login page
     or marketing surface — **not** wired into the Filament panel config in
     this blueprint; see rationale above)

   Only `openguild-logo-color.svg`, `openguild-logo-white.svg`, and
   `openguild-app-icon-32.png` are actually referenced by code in this
   blueprint; the other icon sizes and the WMS cube mark are brought over for
   completeness/future use since the brand is being adopted wholesale.

## File: `resources/views/filament/admin/brand-logo.blade.php` (new)

```blade
<span style="display: inline-flex; align-items: center; height: 100%; gap: 0.5rem;">
    <img src="{{ $logo }}" alt="OpenGuild" style="height: 100%; width: auto; display: block;" />
    <span style="font-size: 0.8125rem; font-weight: 600; line-height: 1; color: {{ $textColor }}; white-space: nowrap;">| wms</span>
</span>
```

(Same technique as PIM's `resources/views/filament/admin/brand-logo.blade.php`,
with `| pim` changed to `| wms`.)

## File: `resources/css/filament/admin/theme.css`

If `make:filament-theme` generated a different scaffold, replace its contents
with exactly this (adjust the relative `../../../../` prefix only if the
generated file lives somewhere else — it should not):

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/**/*';
@source '../../../../app-modules/brands/src/Filament/**/*';
@source '../../../../app-modules/clients/src/Filament/**/*';
@source '../../../../app-modules/notes/src/Filament/**/*';
@source '../../../../app-modules/orders/src/Filament/**/*';
@source '../../../../app-modules/picklists/src/Filament/**/*';
@source '../../../../app-modules/products/src/Filament/**/*';
@source '../../../../app-modules/settings/src/Filament/**/*';
@source '../../../../app-modules/users/src/Filament/**/*';

@theme {
    --radius-sm: 12px;
    --radius-md: 16px;
    --radius-lg: 20px;
    --radius-xl: 24px;
}
```

Do **not** add color tokens here — `primary`/`info` are set via
`->colors()` in PHP (which generates the full OKLCH shade ramp), matching
PIM's approach exactly.

## File: `vite.config.js`

If `make:filament-theme` did not already add the entry, change the `input`
array to:

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/js/warehouse-map.js',
    'resources/css/filament/admin/theme.css',
],
```

Leave `plugins`, `refresh`, and `tailwindcss()` untouched.

## File: `app/Providers/Filament/AdminPanelProvider.php`

1. Add this import alongside the existing `use Filament\Support\Colors\Color;`
   line:

   ```php
   use Filament\FontProviders\GoogleFontProvider;
   ```

2. Remove `use Filament\Support\Colors\Color;` — it becomes unused once
   `Color::Amber` is replaced below (see step 4). Leave every other import
   untouched.

3. Replace:

   ```php
   ->brandName('Open Warehouse Management System')
   ->profile()
   ```

   with:

   ```php
   ->brandName('OpenGuild WMS')
   ->brandLogo(fn () => view('filament.admin.brand-logo', [
       'logo' => asset('images/brand/openguild-logo-color.svg'),
       'textColor' => 'rgba(15, 27, 61, 0.6)',
   ]))
   ->darkModeBrandLogo(fn () => view('filament.admin.brand-logo', [
       'logo' => asset('images/brand/openguild-logo-white.svg'),
       'textColor' => 'rgba(255, 255, 255, 0.7)',
   ]))
   ->brandLogoHeight('2rem')
   ->favicon(asset('images/brand/openguild-app-icon-32.png'))
   ->font('Manrope', provider: GoogleFontProvider::class)
   ->viteTheme('resources/css/filament/admin/theme.css')
   ->profile()
   ```

4. Replace:

   ```php
   ->colors([
       'primary' => Color::Amber,
   ])
   ```

   with:

   ```php
   ->colors([
       'primary' => '#F2B134',
       'info' => '#2547D0',
   ])
   ```

5. Every other method call in the chain (`->default()`, `->id('admin')`,
   `->path('/')`, `->multiFactorAuthentication(...)`, `->spa()`,
   `->tenant(...)`, `->login()`, `->authGuard('web')`,
   `->databaseNotifications()`, `->assets(...)`, `->plugins(...)`, all
   `->discoverResources(...)` calls, `->discoverWidgets(...)`, `->widgets(...)`,
   `->pages(...)`, `->navigationGroups(...)`, `->middleware(...)`,
   `->authMiddleware(...)`) stays exactly as-is, in the same order.

## Build & Verification

```bash
npm run build
vendor/bin/pint --dirty --format agent
```

Then manually load the admin panel in the browser (via the `run` skill or
`get-absolute-url` for the panel's URL) and verify:

- Sidebar shows "OpenGuild WMS" wordmark logo + "| wms" suffix (color lockup
  in light mode, white lockup in dark mode).
- Browser tab favicon is the OpenGuild app icon.
- Primary buttons/accents render amber (`#F2B134`), `info`-colored elements
  (e.g. any `->color('info')` badges/alerts) render blue (`#2547D0`).
- Panel text renders in Manrope (inspect computed font-family, or visually
  compare against PIM's admin panel).
- No console/network errors loading the Google Fonts stylesheet or the new
  theme CSS bundle.

## Tests

No existing test asserts the panel brand name, colors, or font (verified via
repo-wide grep for `Open Warehouse Management System` — only this file and
an unrelated worktree copy matched). No new test is warranted: this is a
visual/configuration change with no new business logic, conditional
behavior, or authorization path to regress-test. Rely on the manual
browser verification above per this project's CLAUDE.md UI-change rule.

If, during implementation, you find any test elsewhere in the suite that
snapshot-asserts panel branding (e.g. a Dusk/browser test checking page
title or logo alt text), update only that assertion to match the new brand
name/assets and report it — do not add new test coverage beyond what's
listed here.

## Models / Resources / Authorization / Widgets

N/A — no database, resource, policy, or widget changes in this blueprint.

## Report Back

When implementation is complete, report:
- Every file created/modified (with paths).
- Whether `make:filament-theme` auto-wired `vite.config.js`/`viteTheme()` or
  required the manual edits in this blueprint.
- `npm run build` and `pint` output status.
- Manual browser verification result (or what couldn't be verified and why).
