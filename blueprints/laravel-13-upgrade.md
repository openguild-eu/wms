# Laravel 13 Upgrade — Technical Plan

## Context

Current state (verified against live `composer show`/`composer prohibits` output, not assumed):

| Package | composer.json | Installed | Laravel 13 status |
|---|---|---|---|
| php | ^8.2 | 8.4.27 (Herd) | Laravel 13 floor is PHP 8.3; CI already pins 8.4 |
| laravel/framework | ^12.0 | 12.72.0 | Target ^13.0 (latest v13.42.0) |
| filament/filament | ^4.0 | 4.16.2 | **4.16.4 already supports `illuminate/contracts ^13.0` and `livewire/livewire ^3.8.7` — stay on Filament 4, just patch-bump. Filament 5 would force a Livewire 3→4 major jump; out of scope.** |
| alizharb/filament-activity-log | ^1.3 | 1.5.0 | Already declares `filament ^4.0\|^5.0`, `spatie/laravel-activitylog ^4.0\|^5.0` — no change needed |
| internachi/modular | ^3.0 | 3.0.2 | Already declares `illuminate/support ^11\|^12\|^13` — no change needed |
| laravel/ai | ^0.2.5 | 0.2.8 | Already declares `illuminate/* ^12.0\|^13.0` — no change needed |
| laravel/boost | ^2.7 | 2.10.4 | Already declares `illuminate/* ...^13.0` — no change needed |
| larastan/larastan | ^3.0 | 3.15.0 | Already declares `...^13` — no change needed |
| spatie/laravel-activitylog | ^5.0 | 5.1.4→5.1.5 | Already declares `illuminate/* ^12.0\|^13.0` |
| spatie/laravel-health | ^1.40 | 1.41.0 | Already declares `illuminate/* ^11\|^12\|^13` |
| spatie/laravel-pdf | ^2.2 | 2.14.0 | Already declares `illuminate/contracts ^11\|^12\|^13` |
| nunomaduro/collision | ^8.6 | — | v8.9.6 conflicts only with `<11.48.0` / `>=14.0.0` — ^8.6 range already fine |
| laravel/tinker | ^2.10.1 | 2.11.1 | **v2.x caps at illuminate ^12.0 — must bump constraint to ^3.0** (v3.1.0 supports ^13.0) |
| pestphp/pest | ^3.8 | 3.8.7 | **Must bump to ^5.0** (v5.3.7, requires PHP ^8.4 — already satisfied everywhere) |
| pestphp/pest-plugin-laravel | ^3.2 | 3.2.0 | **Must bump to ^5.0** (v5.0.1 requires `laravel/framework ^13.23.0` and `pestphp/pest ^5.0.1` exactly — this app is moving fully to 13, so the 13-only floor is fine) |
| rebing/graphql-laravel | ^9.10 | 9.17.0 | **Must bump to ^10.0** — 9.x has no Laravel 13 support; 10.2.0 declares `laravel/framework ^12.0\|^13.0` |
| ageekdev/laravel-barcode | ^1.3 | 1.3.0 | **Must bump to ^2.0** — 1.x caps at illuminate ^12.0; 2.0.0 declares `illuminate/contracts ^12.0\|^13.0` and adds a new `ext-bcmath` requirement plus a new `spatie/color` dependency |
| app-modules/* (10 local modules via internachi/modular) | each pins `laravel/framework ^12.0` in its own `composer.json` | n/a | **Every module's own composer.json must be bumped to `^13.0`** — confirmed via `composer prohibits laravel/framework 13.0.0`, which lists all 10 (backorders, brands, clients, notes, orders, picklists, platform, products, settings, users) |

Root composer.json itself (`laravel/laravel` is the app's own package name) also pins `laravel/framework ^12.0` — same edit as any other direct dependency.

## Decision: stay on Filament 4, do not jump to Filament 5

Filament 5 requires `livewire/livewire ^4.4.8`. Filament 4.16.4 already supports Laravel 13 with Livewire 3.8.7. Bundling a Livewire 3→4 major upgrade into this task would multiply risk and scope for no required benefit. If a future Filament 5 migration is wanted, do it as its own planned effort.

## Known risk areas for the execution agent to budget time for

1. **Pest 3 → 5 is a double major jump** (3→4→5), moving PHPUnit 11→12→13 underneath. Likely low app-code impact per the official "zero/near-zero breaking changes" framing, but: snapshot naming changed in Pest 4 (check for any `toMatchSnapshot` usage), some rarely-used Pest 2-era plugins were removed in Pest 4, and PHPUnit 12/13 changelogs may affect custom test base classes or assertions. Run the full suite after the bump and fix fallout; do not skip straight to `--filter`-only runs.
2. **rebing/graphql-laravel 9 → 10** is a major version with no changelog reviewed in this plan — read the package's actual upgrade notes/CHANGELOG before touching schema/type code, and run whatever GraphQL-specific tests/queries exist in this app after the bump.
3. **ageekdev/laravel-barcode 1 → 2** adds a `spatie/color` dependency and requires the `bcmath` PHP extension (confirm it's enabled in `php -m`). Check actual usage sites of this package (`grep -r` for the package's facade/class) for any API surface changes before assuming it's a drop-in bump.
4. **Laravel 13 app-level changes** per the official upgrade guide (laravel.com/docs/13.x/upgrade): review the "High Impact" and "Medium Impact" sections, specifically cache key prefix changes and session cookie name defaults (could invalidate sessions/cache on deploy — note this for the user, don't silently "fix" it without flagging). A third-party post also claimed a restriction on overriding Eloquent model `boot()`; this was not confirmed against the official guide — check it directly rather than trusting the secondary source.
5. **`.ai/custom-rules` mandatory checks** (from this repo's CLAUDE.md) must all pass before reporting done: `php artisan lang:extract`, `compose analyse`, `compose lint`, plus the affected Pest suite.

## Execution steps

1. Edit root `composer.json`:
   - `"php": "^8.2"` → `"^8.3"`
   - `"laravel/framework": "^12.0"` → `"^13.0"`
   - `"laravel/tinker": "^2.10.1"` → `"^3.0"`
   - `"rebing/graphql-laravel": "^9.10"` → `"^10.0"`
   - `"ageekdev/laravel-barcode": "^1.3"` → `"^2.0"`
   - `"pestphp/pest": "^3.8"` → `"^5.0"`
   - `"pestphp/pest-plugin-laravel": "^3.2"` → `"^5.0"`
2. Edit each `app-modules/*/composer.json` (all 10 modules): `"laravel/framework": "^12.0"` → `"^13.0"`.
3. Run `composer update` (expect to need `--with-all-dependencies` for the major bumps; resolve conflicts iteratively — `composer why-not` is the fastest diagnostic if composer refuses a solve).
4. Fix fallout per the risk areas above. Re-run the targeted test files after each fix, not just at the end.
5. Run the full affected Pest suite, then the complete suite.
6. Run `vendor/bin/pint --dirty --format agent`, `php artisan lang:extract`, `compose analyse`, `compose lint`.
7. Report back: what was changed (package versions, code changes, config changes), what broke and how it was fixed, and anything that could not be verified (e.g., if `compose analyse`/`compose lint` aren't real aliases in this shell, say so explicitly rather than guessing a substitute).

## Out of scope

- Filament 5 / Livewire 4 upgrade.
- Any new feature work.
- Pushing or opening a PR (per this repo's working convention, the user pushes and opens PRs themselves).
