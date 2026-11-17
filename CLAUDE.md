# Claude Code Orchestration Protocol

## Role & Architecture
You are the **Lead Architect (Claude Fable)**. You do NOT write execution code or run routine tests directly. Your job is strictly restricted to high-level planning, routing, architectural design, and final review.

## Multi-Agent Workflow Rules
1. **Explore:** For exploration, log parsing, or repository mappings, spawn a swift sub-agent (e.g., Sonnet or Haiku).
2. **Plan:** Fable synthesizes the insights and writes a precise technical plan (or ADR) including parallel execution paths.
3. **Execute:** You MUST delegate code generation and implementation tasks to isolated sub-agents pinned to **Claude Opus** or **Sonnet**.
4. **Review:** Fable performs the final code review, runs verification tests, and approves the merge. Do not accept sub-agent outputs without automated validation.

## Tooling Reconciliation (Laravel Boost / MCP / Filament Blueprint)
The guidelines below (Laravel Boost, Boost MCP tools, Artisan, Pint, Pest) describe *how* work gets done — they bind whichever agent is doing that work, not just Fable. Reconcile them with the roles above as follows:
- **Preparation sub-agents** may use read-only Boost MCP tools (`database-schema`, `search-docs`, `browser-logs`, `route:list`, Laravel MCP resource/tool inspection) freely during exploration.
- **Design (Fable):** for any Filament work, invoke the `planning-filament` skill and write the resulting plan as Markdown to `blueprints/<feature>.md`. That file is the plan artifact handed to execution — Fable does not implement it.
- **Execution sub-agents** (Opus/Sonnet) are dispatched with: *"Implement blueprints/<feature>.md. Follow the plan and run the relevant tests. Report what was implemented and any checks that could not be completed."* They own all code generation, activate the relevant Boost skill for the domain (modular, filament-development, activitylog-development, medialibrary-development, etc.), and run `vendor/bin/pint --dirty --format agent` plus the affected Pest tests before reporting back.
- **Review (Fable):** invoke the `reviewing-filament-plans` skill with: *"Using the reviewing-filament-plans skill, review the implementation in this checkout against blueprints/<feature>.md, including the current uncommitted changes. Report requirement traceability, confirmed deviations, and anything blocked or not verified. Do not change application code."* Fable may also use read-only Boost tools (`database-query`, `search-docs`) to verify claims, but does not author or change implementation code during review.





<laravel-boost-guidelines>
=== .ai/custom-rules rules ===

---
name: custom AI rules
description: Matthijs Neijenhuijs coding standards for building and maintaining Laravel applications; use for models, controllers, services, and other application code.
license: MIT
metadata:
  author: Matthijs Neijenhuijs
---

## Rule priority

- You MUST follow existing project conventions first.
- If no convention exists, you MUST follow the rules in this file.

## Mandatory checks after completing a task

- You MUST always run these commands:
```bash

# Search for translate strings

php artisan lang:extract

# Run analysis

compose analyse

# Run linter

compose lint

```
- You MUST run relevant tests for changed behavior (at least focused tests around the affected code).

## General rules

- You MUST follow Filament code quality tips: https://filamentphp.com/docs/5.x/resources/code-quality-tips
- You MUST NOT use `down()` methods in migrations. We do not roll back migrations in production. If schema changes are needed, you MUST create a new migration.
- You MUST use `start_on` and `end_on` for date fields.
- You MUST use `start_at` and `end_at` for datetime fields.

## Model rules

- Every model MUST define a `casts()` method for attributes that are not plain strings or integers.

The function should look like this:
```php
public function casts(): array
{
    return [
        'attribute_name' => 'cast_type',
        // Add more attributes and their corresponding cast types as needed
    ];
}
```

- You MUST place `casts()` as the last function in the model class.
- You MUST use `HasFactory` in all models that have a factory.

Example:
```php
/** @use HasFactory<AssetTypeFactory> */
use HasFactory;
```
- Every model MUST define `getSearchableSettings()` for Laravel Scout / Meilisearch.

Example:
```php
public static function getSearchableSettings(): array
{
    return [
        'filterableAttributes' => [],
        'sortableAttributes'   => ['created_at', 'updated_at'],
        'searchableAttributes' => [
            'av_key',
            'name',
        ],
    ];
}
```
- Every model MUST define `toSearchableArray()` for fields indexed by Laravel Scout.

Example:
```php
public function toSearchableArray(): array
{
    return [
        'id'         => $this->id,
        'av_key'     => $this->av_key,
        'name'       => $this->name,
        'created_at' => $this->created_at?->toIso8601String(),
        'updated_at' => $this->updated_at?->toIso8601String(),
    ];
}
```

## Config rules

- You MUST use Meilisearch with the Scout Meilisearch driver.
- Every time a new searchable model is added, you MUST add its index settings in `config/scout.php`.
- Model index settings MUST come from `ModelClass::getSearchableSettings()`.

Example:
```php
// config/scout.php
return [
    'meilisearch' => [
        'index-settings' => [
            'model_name' => ModelClass::getSearchableSettings(),
            // Add more model names and their corresponding searchable settings as needed
        ],
    ],
];
```

## Directory rules

- You MUST use `.gitkeep` files in empty directories so they are tracked by Git.
- You MUST remove `.gitkeep` when the directory is no longer empty.

## Building factory classes

- Factories MUST only contain definitions and fake data calls.
- You MUST NOT use logic in factories: no query logic, `if` statements, loops, or business logic.
- Factories are intentionally simple and "dumb" and MUST stay that way.

## Building seeder classes

- Seeders MAY use logic, but they MUST stay simple and maintainable.
- Seeders MAY use complex queries only when needed, and they MUST remain easy to understand.

## Translation rules

- You MUST translate Filament columns and input fields with `label()`.
- In translation files, you MUST capitalize every separate word.
- You MUST translate navigation labels in Filament clusters and resources with `__()`.

## HTTP resource rules

- Every resource MUST have a corresponding HTTP resource class.
- You MUST define the JSON response structure as an explicit array of attributes in the resource class.

## Filament provider rules

- Every Filament provider MUST define `Relation::enforceMorphMap()` in `boot()`.

Example:
```php
public function boot(): void
{
    Relation::enforceMorphMap([
        'model_name' => ModelClass::class,
        // Add more model names and their corresponding classes as needed
    ]);
}
```

- Every Filament provider MUST define `Feature::discover()` in `boot()`.

Example:
```php
public function boot(): void
{
    Feature::discover([
        FeatureClass::class,
        // Add more feature classes as needed
    ]);
}
```

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel-ai/core rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== alizharb/filament-activity-log/core rules ===

<guideline>
    <title>Filament Activity Log</title>

    <summary>
        Filament Activity Log provides a Filament resource, timeline actions, widgets, exports, and audit-focused helpers on top of spatie/laravel-activitylog.
    </summary>

    <installation>
        Install the package with Composer, register <code>AlizHarb\ActivityLog\ActivityLogPlugin::make()</code> in the Filament panel, and run <code>php artisan filament-activity-log:install</code> when publishing configuration or assets is needed.
    </installation>

    <spatie-activitylog>
        Use Spatie's <code>LogsActivity</code> trait on models that should be audited. For Spatie v5, make sure the official v5 migration has been completed before relying on this package. Spatie v4 stores changes in <code>properties</code>; Spatie v5 stores them in <code>attribute_changes</code>. This package reads both.
    </spatie-activitylog>

    <privacy>
        Do not intentionally log passwords, tokens, API keys, secrets, or private keys. The package redacts configured sensitive fields before display, but redaction is a safety layer and not a reason to store secrets in activity logs.
    </privacy>

    <risk-scoring>
        Use the configured risk system to highlight destructive, security-sensitive, and privacy-sensitive activity. Customize <code>filament-activity-log.risk</code> or provide a resolver class when application-specific scoring is required.
    </risk-scoring>

    <immutable-mode>
        For compliance-heavy panels, enable <code>filament-activity-log.privacy.immutable_mode</code>. This hides delete, bulk delete, prune, restore, and revert actions from the package UI.
    </immutable-mode>

    <authorization>
        Prefer Filament/Laravel authorization for access control. If the application needs a simple package-level rule, configure <code>permissions.custom_authorization</code> with an invokable class instead of a closure so config caching remains safe.
    </authorization>

    <performance>
        Activity tables can grow quickly. Keep filters selective, avoid rendering large JSON payloads in table rows, and add database indexes for common filters such as <code>created_at</code>, <code>event</code>, <code>log_name</code>, <code>causer_type</code>/<code>causer_id</code>, and <code>subject_type</code>/<code>subject_id</code>.
    </performance>

    <filament-usage>
        Use <code>ActivitiesRelationManager</code> to add model-specific history to resources. Use <code>ActivityLogTimelineTableAction</code> when a compact slide-over timeline is better than navigating to the full resource.
    </filament-usage>
</guideline>

=== internachi/modular/core rules ===

## Modular

- This a modular application. Each module is located in its own directory inside of `app-modules`.
- IMPORTANT: Activate `modular` every time you're working with or creating a new module.

=== spatie/laravel-activitylog/core rules ===

# spatie/laravel-activitylog

Activity logging package for Laravel. Logs model events and manual activities to a database table.

## Key Concepts

- **Activity**: An Eloquent model (`Spatie\Activitylog\Models\Activity`) storing log entries with subject, causer, event, attribute_changes, and properties.
- **Subject**: The model being acted upon (polymorphic `subject_type`/`subject_id`).
- **Causer**: The model that caused the action, typically the authenticated user (polymorphic `causer_type`/`causer_id`).
- **LogOptions**: Fluent configuration object returned by `getActivitylogOptions()` on models using the `LogsActivity` trait.
- **ActivityEvent**: Enum with cases `Created`, `Updated`, `Deleted`, `Restored`.
- **`attribute_changes`** column: stores `{"attributes": {...}, "old": {...}}` for tracked model changes.
- **`properties`** column: stores custom user data set via `withProperties()`.

## Traits

### `LogsActivity`

Add to models to automatically log create/update/delete events. Optionally implement `getActivitylogOptions()` to configure which attributes to track (defaults to logging events without attribute changes).

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

### `CausesActivity`

Add to user/causer models. Provides `activitiesAsCauser()` relationship.

### `HasActivity`

Combines `LogsActivity` and `CausesActivity`. Provides `activities()`, `activitiesAsSubject()`, and `activitiesAsCauser()`.

## Manual Logging

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->event(ActivityEvent::Updated)
    ->withProperties(['key' => 'value'])
    ->log('Article was updated');
```

## LogOptions Methods

| Method | Description |
|--------|-------------|
| `logFillable()` | Log all fillable attributes |
| `logAll()` | Log all attributes |
| `logOnly(array)` | Log specific attributes |
| `logExcept(array)` | Exclude attributes |
| `logOnlyDirty()` | Only log changed attributes |
| `dontLogEmptyChanges()` | Skip logging when no tracked attributes changed |
| `dontLogIfAttributesChangedOnly(array)` | Ignore updates that only change these attributes |
| `useLogName(string)` | Set custom log name |
| `setDescriptionForEvent(Closure)` | Custom description per event |
| `useAttributeRawValues(array)` | Store raw (uncast) values |

## Querying Activities

```php
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Enums\ActivityEvent;

Activity::forEvent(ActivityEvent::Created)->get();
Activity::causedBy($user)->get();
Activity::forSubject($article)->get();
Activity::inLog('orders')->get();
```

## Setting the causer

Override the causer for a block of code:

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    // all activities here are caused by $admin
});

// or set globally for the rest of the request
Activity::defaultCauser($admin);
```

## Disabling Logging

```php
activity()->withoutLogging(function () {
    // no activities logged here
});
```

## Accessing Changes and Properties

```php
$activity = Activity::latest()->first();

// Tracked model changes (set automatically by LogsActivity)
$activity->attribute_changes; // Collection: {"attributes": {...}, "old": {...}}

// Custom user data (set via withProperties)
$activity->properties; // Collection
$activity->getProperty('key'); // single value
```

## Custom Activity Model

Set `activity_model` in `config/activitylog.php` to a class that extends `Model` and implements `Spatie\Activitylog\Contracts\Activity`. Use a custom model for custom table names or database connections.

## Customizing Actions

The package uses action classes (`LogActivityAction`, `CleanActivityLogAction`) that can be extended and swapped via config:

```php
// config/activitylog.php
'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \App\Actions\CustomCleanAction::class,
],
```

Custom action classes must extend the originals. Override protected methods (`save()`, `beforeActivityLogged()`, `resolveDescription()`, etc.) to customize behavior.

## Configuration

Key config options in `config/activitylog.php`:
- `enabled`: Master on/off switch (env: `ACTIVITYLOG_ENABLED`)
- `clean_after_days`: Days to keep records for `activitylog:clean` command
- `default_log_name`: Default log name (string)
- `default_auth_driver`: Auth driver for causer resolution
- `include_soft_deleted_subjects`: Include soft-deleted subjects
- `activity_model`: Custom Activity model class
- `default_except_attributes`: Globally excluded attributes
- `actions.log_activity`: Action class for logging activities
- `actions.clean_log`: Action class for cleaning old activities

</laravel-boost-guidelines>
