# Filament 5 / Livewire 4 Upgrade — Technical Plan (Phase 2)

## Sequencing

**This phase runs after `blueprints/laravel-13-upgrade.md` (Phase 1) is implemented, reviewed, and committed in this same worktree.** Both phases touch root `composer.json`; running them concurrently risks corrupting the lock file resolution. Do not start this phase until Phase 1's changes are committed.

## Context

The original Laravel 13 plan deliberately stayed on Filament 4 (4.16.4 already supports Laravel 13 + Livewire 3, avoiding a forced Livewire 4 jump). The user has since asked for the full Filament 5 major upgrade as well. This file plans that as its own phase.

Initial risk assessment (287 Filament resource files in this app) looked large, but direct research narrowed it substantially — see "Verified low-risk findings" below. The official Filament 5 upgrade guide (fetched directly from `github.com/filamentphp/filament` docs on the `5.x` branch, since Boost's `search-docs` only indexes docs matching the *currently installed* major version and was returning the stale 3→4 guide) documents **no manual breaking changes of its own** beyond the new requirements and Livewire 4. The real work is the Livewire 3→4 migration.

## New requirements (from the official Filament 5 upgrade guide)

- PHP 8.2+ — already satisfied (8.4.27 installed)
- Laravel v11.28+ — already satisfied after Phase 1 (targeting ^13.0)
- Livewire v4.0+ — **this is the actual upgrade**
- Tailwind CSS v4.1+ for custom themes — **already satisfied**, see below

## Verified low-risk findings (checked directly against this codebase, not assumed)

- **Tailwind is already v4.0.0** (`package.json`: `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`). No `tailwind.config.js` exists. The one custom Filament theme (`resources/css/filament/admin/theme.css`) is already in the v4 `@import`/`@source` format (no `@config` directive) — the Tailwind-migration section of the Filament 3→4 guide is already satisfied; nothing to do here.
- **Livewire Volt is not used.** `composer why livewire/volt` resolves nothing (not actually required anywhere live), and a repo-wide search for Volt's `new class extends Component` front-matter syntax found zero matches. No Volt migration needed.
- **Only one file in the entire app directly extends `Livewire\Component`**: `app-modules/products/src/Filament/Actions/ImportWithSpreadsheetAction.php`. Everything else goes through Filament's abstractions. Check this file against the Livewire 4 "method signature changes" section (mount signature, stream() if used) specifically.
- **No `wire:scroll`, no `wire:transition` with removed modifiers (`.opacity`/`.scale`/`.duration`/`.origin`), no `Route::get()` registering a Livewire component class directly** anywhere in `resources/`, `app-modules/`, or `routes/`. These are zero-hit, not "probably fine."
- **Exactly one `<livewire:...>` tag** in the whole app (`resources/views/admin/profile.blade.php:5`, `<livewire:filament-profile />`), and it is already self-closing — the "component tags must be closed" v4 requirement is already satisfied.
- **`config/livewire.php` has three explicit, non-default values that the v4 key renames affect** (verified by reading the file directly):
  - `'layout' => 'layouts.app'` → must become `'component_layout' => ...`. Confirm with `search-docs` (once Livewire 4 is actually installed, Boost's doc index will switch to v4 docs) whether a plain dotted view path like `'layouts.app'` is still accepted under the new key, or whether it must be expressed as a `layouts::app` namespace reference requiring a `component_namespaces` entry. Do not guess — verify against the installed v4 docs before deciding.
  - `'lazy_placeholder' => null` → rename key to `'component_placeholder'` (value can likely stay `null`, but confirm `null` is still a valid default rather than a required string).
  - `'smart_wire_keys' => false` → key name is unchanged in v4 (only the *default* changed to `true`); since this app already sets it explicitly, no action needed beyond leaving it as-is.

## Execution steps

1. **Run Filament's official automated upgrade script** (Rector-based, handles most mechanical breaking changes across all 287 resource files automatically — do not hand-edit what this script can do):
   ```bash
   composer require filament/upgrade:"^5.0" -W --dev
   vendor/bin/filament-v5
   # then run whatever commands the script itself outputs — they are specific to this app
   composer require filament/filament:"^5.0" -W --no-update
   ```
2. **Bump Livewire** in the same composer.json pass: `"livewire/livewire": "^4.0"` (this will already be pulled in as a consequence of filament/support 5.x requiring `^4.4.8`, but make the root constraint explicit rather than relying purely on transitive resolution).
3. Run `composer update` and resolve to a clean state (re-check `alizharb/filament-activity-log` — already declares `filament ^4.0|^5.0` and `spatie/laravel-activitylog ^4.0|^5.0`, so no bump needed there, but confirm after update it actually resolved against filament 5).
4. Apply the `config/livewire.php` key renames from "Verified low-risk findings" above, after confirming the correct v4 value format via `search-docs`.
5. Read the one direct Livewire component (`ImportWithSpreadsheetAction.php`) against the Livewire 4 upgrade guide's "method signature changes" section and fix if affected.
6. `php artisan filament:upgrade-directory-structure-to-v4 --dry-run` — this is a *Filament-4-era* command for moving to v4's resource/cluster directory layout; since this app is already on Filament 4 and presumably already uses that structure, this step is likely a no-op. Run the dry-run and confirm it reports no changes before skipping it; do not assume.
7. `php artisan optimize:clear`.
8. `composer remove filament/upgrade --dev` once the migration is confirmed complete.
9. Run the full Pest suite, `vendor/bin/pint --dirty --format agent`, `php artisan lang:extract`, `compose analyse`, `compose lint` (same mandatory checks as Phase 1 — if Phase 1 already established whether `compose analyse`/`compose lint` are real commands in this environment, reuse that finding rather than re-discovering it).
10. Manually sanity-check a handful of representative Filament pages in the browser (list page, form/edit page, a relation manager, a custom action) per this repo's "test the golden path in a browser" convention for UI changes — Boost's `browser-logs` tool can surface any JS errors from the Livewire 4 frontend assets.

## Report back

Exact before/after version numbers for `filament/filament`, `livewire/livewire`, and anything else bumped as a resolution side-effect; what the `filament-v5` Rector script actually changed (it writes its own change list — include it); the final decision made for each `config/livewire.php` key rename; and confirmation of the golden-path manual check.

## Out of scope

- Any new feature work beyond what the upgrade requires.
- Pushing or opening a PR (the user pushes and opens PRs themselves).
