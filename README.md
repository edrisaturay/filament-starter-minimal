# Filament Starter Minimal

A standalone Laravel package for building Filament v5 admin panels. It provides a configurable plugin registry, Shield permissions, user management with STS impersonation, and platform administration resources. Optional Laravel tools can be installed and configured from the same installer.

Install this package directly into your Laravel application. It does not require `edrisaturay/filament-starter`, a starter template, or this repository's development application.

## Requirements

- PHP `^8.3`.
- Laravel with Illuminate components matching `^11.28`, `^12.0`, or `^13.0`, subject to the compatible versions of Filament and bundled dependencies resolved by Composer.
- Filament `^5.0` and Filament Shield `^4.2.0`, included as Composer dependencies.
- A configured application database and an authenticatable user model.

## Installation

### 1. Install the package

Run in your Laravel application:

```bash
composer require edrisaturay/filament-starter-minimal
```

The service provider is discovered automatically. If the application does not yet have a Filament panel, create one:

```bash
php artisan filament:install --panels
```

### 2. Register the panel plugin

Add the plugin to your application's Filament `PanelProvider`:

```php
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // Keep your panel's existing ID, path, authentication, and middleware.
        ->plugins([
            FilamentStarterMinimalPlugin::make(),
        ]);
}
```

Register it on each panel that uses this package. Shield is registered through the package, so do not also add `FilamentShieldPlugin::make()` or run `shield:install`.

### 3. Configure your panels

The default managed panel ID is `admin`. To use other IDs or change defaults, publish the configuration:

```bash
php artisan vendor:publish --tag=filament-starter-minimal-config
```

In `config/filament-starter-minimal.php`, list every panel using the plugin:

```php
'managed_panels' => ['admin', 'staff'],
```

Registering the panel plugin installs enabled registry plugins. `managed_panels` tells snapshot, sync, doctor, and cross-panel integrations which panels to manage. Keep the two aligned.

### 4. Run setup

Configure `.env` and the database connection first, then run:

```bash
php artisan minimal-starter:install
```

The installer:

1. Adds Spatie Permission's `HasRoles` trait to the configured user model when needed, then restarts to load the change.
2. Publishes `ShieldSeeder`, `UserSeeder`, the application Users resource, and its supporting configuration without overwriting existing files.
3. Prepares plugin migrations and runs `migrate --force`.
4. Creates panel snapshots and plugin override rows, with interactive plugin selections for each managed panel.
5. Runs `ShieldSeeder` to generate permissions and policies for registered managed panels, then runs `UserSeeder` to prepare the super admin.
6. Offers optional Laravel tools and installs/configures the selected tools.

The default seeded account is:

| Setting | Default |
| --- | --- |
| Name | Edrisa Turay |
| Email | `edrisa@edrisa.com` |
| Password for a new account | `password` |
| Role | `super_admin` |

Change `seeders.admin` in the published configuration before installation to use different account details. Existing account passwords are preserved. Change the default password before exposing the application publicly.

For unattended setup, plugin choices use the configured defaults and optional Laravel tools must be selected explicitly:

```bash
php artisan minimal-starter:install --publish-config --no-interaction
php artisan minimal-starter:install --laravel-tools=horizon --laravel-tools=telescope --laravel-tools=pulse --no-interaction
```

## Publishing without installing

Publish the seeders and TomatoPHP-based application Users resource without running migrations or seeders:

```bash
php artisan vendor:publish --tag=filament-starter-minimal
```

Or publish them separately:

```bash
php artisan vendor:publish --tag=filament-starter-minimal-seeders
php artisan vendor:publish --tag=filament-starter-minimal-users
```

Files are prepared in `database/seeders/` and `app/Filament/Resources/Users/`. Publishing only prepares files. `minimal-starter:install` performs setup and runs the seeders in order. Existing published files are retained unless you explicitly force a vendor publish.

Package migrations are loaded automatically. Application copies are optional:

```bash
php artisan vendor:publish --tag=filament-starter-minimal-migrations
```

## Updating an existing application

```bash
composer update edrisaturay/filament-starter-minimal -W
php artisan minimal-starter:update
php artisan minimal-starter:doctor
```

`minimal-starter:update` runs migrations and refreshes panel snapshots and registry rows. It does not rerun the installation seeders or install optional tools. Use `minimal-starter:install` for installation setup, or `minimal-starter:tools` to add tools to an existing application.

## Artisan commands

| Command | Purpose |
| --- | --- |
| `minimal-starter:install` | Prepare plugin assets, migrate, sync panels, seed Shield and the admin user, and offer optional Laravel tools. |
| `minimal-starter:tools` | Select, install, and configure optional Laravel tools. Accepts tool keys as arguments. |
| `minimal-starter:update` | Run migrations and refresh panel snapshots and registry rows. |
| `minimal-starter:doctor` | Check database access, migrations, registered panels, snapshots, and plugin classes. |
| `minimal-starter:safe-mode on` | Force plugins marked dangerous to disable into the enabled state and clear plugin caches. |
| `minimal-starter:safe-mode off` | Turn off the cached safe-mode setting and clear plugin caches. |

Installation options include `--publish-config`, `--publish-all`, `--laravel-tools=*`, and `--skip-laravel-tools`. Commands accept Laravel's standard `--no-interaction` and verbosity flags.

Doctor exits with `1` for critical findings and `0` for success or warnings only. Safe mode can also be configured with `STARTER_MINIMAL_SAFE_MODE` or `safe_mode`; it is inactive in the testing environment.

## Optional Laravel tools

`minimal-starter:install` offers a separate optional Laravel Tools selection. Nothing is selected automatically in unattended runs. These packages are installed in the consuming app only when selected.

```bash
php artisan minimal-starter:tools horizon telescope pulse
php artisan minimal-starter:install --laravel-tools=horizon --laravel-tools=pennant --no-interaction
php artisan minimal-starter:install --skip-laravel-tools
```

Available keys: `horizon`, `telescope`, `pulse`, `reverb`, `scout`, `pennant`, `sanctum`, `socialite`, `cashier`, `octane`, `sail`, `dusk`, `pint`, and `pail`. Sail, Dusk, Pint, and Pail are installed as development dependencies. Composer resolves versions compatible with the consuming app and must be on PATH. Package discovery and configuration run in fresh PHP processes after installation.

Setup publishes configuration and migrations, preserves existing files and credentials, applies migrations, and records successful selections in `config/starter-minimal-tools.php`. Failures stop setup with a nonzero exit code; rerun the same selection after correcting the reported problem. Existing dashboard policies are replaced by the starter's super admin access policy for Horizon, Telescope, and Pulse, including in local environments. The `guard` setting can select a non-default session guard.

| Tool | Automatic setup | Application or infrastructure work |
| --- | --- | --- |
| Horizon | Configuration, Redis queue driver, Predis when the PHP Redis extension is absent, dashboard authorization/link, snapshot schedule | Running Redis, supervised Horizon worker, Laravel scheduler |
| Telescope | Configuration, migrations, sensitive request filtering, dashboard authorization/link, daily pruning | Laravel scheduler |
| Pulse | Configuration, dashboard view, migrations, authorization/link | Supervise `pulse:check` for server metrics |
| Reverb | Official broadcasting and Reverb setup, local credentials/configuration, Echo dependencies when package.json exists | Supervise Reverb, public host/TLS and build frontend assets |
| Scout | Configuration, database driver if no driver is configured | Add Searchable to the models you want indexed |
| Pennant | Configuration and feature storage migration | Define application feature flags |
| Sanctum | Configuration, token migration, HasApiTokens on the configured user model | Define API routes and token issuance |
| Socialite | GitHub/Google configuration merged into services, environment placeholders | OAuth credentials and application login/callback flow |
| Cashier | Stripe configuration/migrations, Billable on the configured user model, environment placeholders | Stripe credentials/webhook registration and product/checkout flow |
| Octane | Official FrankenPHP installation for a new setup | Process supervision and application compatibility with long-running workers |
| Sail | Official Docker scaffolding with Redis; existing Compose files preserved | Docker installation and container startup |
| Dusk | Official browser test scaffolding and ChromeDriver installation | Chrome and a running test application |
| Pint | Laravel preset when no configuration exists | Run `vendor/bin/pint` |
| Pail | Installed CLI log viewer | Run `php artisan pail` |

Horizon changes `QUEUE_CONNECTION` to `redis`. External credentials are never invented, and the installer does not start production daemons or provision external services. The final setup output lists these remaining requirements explicitly. Cashier's standard migrations target the standard `users` table; custom billing schemas need application-specific migrations.

## What this package provides

| Area | Description |
|------|-------------|
| **`FilamentStarterMinimalPlugin`** | Filament `Plugin` (`getId(): 'filament-starter-minimal'`). Registers Platform **resources** and runs **`PlatformPanelFactory::build()`** for registry plugins. |
| **`PluginRegistry`** | Declares plugins (key, label, installer closure, defaults, `dangerous_to_disable`, Composer package name, plugin class). |
| **`PluginStateResolver`** | Merges **config defaults** with **DB overrides**, caches result per panel (`starter_minimal_plugins_{panelId}`). |
| **`PluginSyncManager`** | Ensures every registry key has a row per managed panel; removes stale rows; writes audit entries; clears cache. |
| **`PanelSnapshotManager`** | Stores panel metadata JSON; triggers `PluginSyncManager::sync()`. |
| **Filament resources** | **Plugin Management**, **Panel Snapshots**, **Audit Log** (super-admin gated). |

---

## Configuration reference

File: `config/filament-starter-minimal.php` (merged as `filament-starter-minimal.*`).

| Key | Purpose |
|-----|---------|
| `safe_mode` | When `true` (or env `STARTER_MINIMAL_SAFE_MODE=true`), dangerous registry plugins are forced on. Usually toggled via `minimal-starter:safe-mode` (cache). |
| `superadmin.role` | Role name checked by Platform resources (`hasRole()`). Default: `super_admin`. |
| `superadmin.bypass_gates` | Users with the super-admin role pass every Gate/policy check (env `STARTER_MINIMAL_SUPERADMIN_BYPASS_GATES`). Default: `true`. |
| `shield.enforce_policies` | Call `FilamentShield::enforcePolicies()` so Shield-generated policies for vendor models are registered (env `STARTER_MINIMAL_SHIELD_ENFORCE_POLICIES`). Default: `true`. |
| `managed_panels` | Panel IDs used by sync, snapshots, doctor, and cross-panel integrations. |
| `plugin_defaults` | Nested: `panel_id` → `plugin_key` → `enabled`, `options`. Used before DB overrides. |

Example for two panels:

```php
'managed_panels' => ['admin', 'staff'],

'plugin_defaults' => [
    'admin' => [
        'filament-shield' => [
            'enabled' => true,
            'options' => [],
        ],
    ],
    'staff' => [
        'filament-shield' => [
            'enabled' => true,
            'options' => [],
        ],
    ],
],
```

---

## Database schema

All package tables use the `starter_minimal_` prefix.

| Table | Purpose |
|-------|---------|
| `starter_minimal_panel_plugin_overrides` | Per-panel, per-plugin `enabled`, `is_dangerous`, `options`. Also `options_version` (currently always `1`, reserved for future schema migrations) and `updated_by_user_id` (reserved; not yet auto-set by sync, populate manually if needed). Unique on `(panel_id, plugin_key)`. |
| `starter_minimal_panel_snapshots` | Latest known Filament panel metadata (`path`, domains, middleware, and the consumer panel's `hasTenancy()` flag, informational only; this package itself is not multi-tenant aware). |
| `starter_minimal_audit_logs` | Append-only log for plugin sync and override changes. Immutable, `update`/`delete` throw at the model layer. `actor_user_id` is auto-injected from `auth()->id()` and is **not** mass-assignable. |

**Dangerous plugins:** If `is_dangerous` is true, the `PanelPluginOverride` model forces `enabled` to stay true.

---

## Filament resources (navigation: **Platform**)

| Resource | Access | Notes |
|----------|--------|--------|
| **Plugin Management** | Super admin only | CRUD overrides; **Sync from Registry**; **Clear Plugin Cache**. |
| **Panel Snapshots** | Super admin only | Read-only meta; **Refresh snapshots** header action. |
| **Audit Log** | Super admin only | Read-only list, newest first. |

`canViewAny()` / edit / create / delete use `auth()->user()->hasRole(config('filament-starter-minimal.superadmin.role'))`.

---

## Bundled plugin catalog

The default catalog is registered for you in `Registry\DefaultPluginCatalog`. Every entry is **enabled by default** and every installer is `class_exists`-guarded, so an optional entry whose dependency is absent is skipped. Bundled dependencies are installed by Composer; additional catalog entries may require a separate installation. Manage enabled plugins per panel from **Plugin Management** in the admin UI, or pre-configure in `config/filament-starter-minimal.php`.

| Key | Package (`composer require ...`) | Notes |
|-----|----------------------------------|-------|
| `filament-shield` | `bezhansalleh/filament-shield` | RBAC. `dangerous_to_disable`, safe-mode forces it on. |
| `filament-panel-switch` | `bezhansalleh/filament-panel-switch` | Configures globally via `PanelSwitch::configureUsing()`. |
| `filament-exceptions` | `bezhansalleh/filament-exceptions` | Adds an Exceptions resource. |
| `filament-language-switch` | `bezhansalleh/filament-language-switch` | Configures globally. Pass `options.locales` to set available locales. |
| `filament-breezy` | `jeffgreco13/filament-breezy` | Profile + 2FA flows. 2FA turns on only when the user model uses `TwoFactorAuthenticatable`. |
| `filament-auth-designer` | `caresome/filament-auth-designer` | Branded auth pages + theme toggle. Options: `pages`, `theme_toggle`, `media`, `media_position`, `media_size`, `blur`. |
| `filament-logger` | `mradder/filament-logger` | Activity log UI. |
| `filament-connection-badge` | `rawand201/filament-connection-badge` | DB connection indicator. |
| `filament-media-manager` | `slimani/filament-media-manager` | Folder/file media manager. |
| `filament-menu-builder` | `datlechin/filament-menu-builder` | Drag-and-drop navigation builder. |
| `filament-api-service` | `rupadana/filament-api-service` | Generates REST endpoints from Resources. |
| `filament-resource-lock` | `kenepa/resource-lock` | Prevents concurrent edits on a resource. |
| `filament-spatie-health` | `shuvroroy/filament-spatie-laravel-health` | UI for `spatie/laravel-health`. |
| `filament-spatie-backup` | `shuvroroy/filament-spatie-laravel-backup` | UI for `spatie/laravel-backup`. |
| `filament-global-search-modal` | `charrafimed/global-search-modal` | Richer global search UI. |
| `filament-knowledge-base` | `guava/filament-knowledge-base` | One Knowledge Base option: documentation plugin on the configured KB panel, companion on other panels. |
| `filament-spotlight` | `pxlrbt/filament-spotlight` | ⌘K spotlight launcher. |
| `filament-quick-create` | `awcodes/filament-quick-create` | Topbar quick-create dropdown. Options: `excludes`, `includes`, `sort_by`. |
| `filament-log-viewer` | `achyutn/filament-log-viewer` | Laravel log reader page. Options: `authorize`, `register_navigation`, `navigation_*`, `polling_time`. |
| `filament-uni-file-manager` | `unifilemanager/filament-file-manager` | Filesystem browser page. |
| `filament-column-filters` | `zvizvi/filament-column-filters` | Excel-style filter popups in table column headers. No options, enabling it on a panel makes `->columnFilter()` available on that panel's table columns. |
| `filament-users` | `tomatophp/filament-users` | User management with Shield roles and STS impersonation. Options: `avatar`, `user_resource`, `teams_resource` (all backed by **static** properties on the vendor plugin, so the last panel to install wins). Its user resource supersedes this package's shipped `UserResource`, see below. |

Run `php artisan minimal-starter:doctor` after `composer require` to confirm the class autoloads, if a vendor renames their plugin class between versions, override the entry via `withPlugin()` (next section).

### Knowledge Base (Guava)

This package **requires** `guava/filament-knowledge-base` ^3.x (Filament v5). You need **two** Filament panels:

1. A dedicated **knowledge-base** panel with `KnowledgeBasePlugin` (registry key `filament-knowledge-base`).
2. Your main panel(s) with the companion, selected automatically by the same `filament-knowledge-base` option.

Both use one registry entry and one installer checkbox. Companion preview options remain available on that entry. Existing custom configuration using `filament-knowledge-base-companion` should use `filament-knowledge-base` instead.

`managed_panels` ships as `['admin']`. Once your KB panel exists, add its id (default `knowledge-base`) to `managed_panels`: the companion on `admin` switches on only for a managed KB panel with the KB plugin enabled, and renders nothing while that panel is not registered.

After install:

```bash
php artisan filament:assets
npm install -D @tailwindcss/typography   # if not already present
```

In **each** custom Filament theme used by the KB panel and companion panel(s), add:

```css
@plugin "@tailwindcss/typography";
@source '../../../../vendor/guava/filament-knowledge-base/src/**/*';
@source '../../../../vendor/guava/filament-knowledge-base/resources/views/**/*';
```

Store markdown under `docs/{panel-id}/{locale}/` and scaffold pages with `php artisan docs:make`.

### Column Filters (zvizvi)

Enabled by default on every managed panel. Registering it is what makes the
`columnFilter()` macro available and wires the Livewire listeners that decorate
table headers, disable the `filament-column-filters` key for a panel and the
macro stops existing there.

```php
use Filament\Tables\Columns\TextColumn;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

TextColumn::make('name')->columnFilter(ColumnFilter::search()),
TextColumn::make('created_at')->date()->columnFilter(ColumnFilter::date()),
TextColumn::make('status')->columnFilter(ColumnFilter::select()->options([...])->multiple()),
TextColumn::make('amount')->columnFilter(ColumnFilter::range()->step(0.01)),
```

Each configured column auto-registers a backing table filter (`cf_{column}`), so
it shows the usual filter indicators without cluttering the filters dropdown. A
regular filter named like the column (or any `SelectFilter` on the same
attribute) is synced with automatically; otherwise point at it with
`->syncWith('filter_name', ['from' => 'created_from', ...])`.

Pages that build their table outside a Livewire request (a custom endpoint
calling `getTable()` headlessly) need the vendor trait on the page class itself:

```php
use Zvizvi\FilamentColumnFilters\Concerns\HasColumnFilters;
```

Translations (en/he) publish with `php artisan vendor:publish --tag=filament-column-filters-translations`.

### Breezy two-factor authentication

Breezy enables two-factor authentication only when the configured user model uses `Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable`. Add that trait if you want 2FA; `HasRoles` alone does not enable it.

## Companion packages (not panel plugins)

These packages don't implement Filament's `Plugin` contract, they ship **form components**, **traits**, or **action classes** you compose into your own Resources. They're not in the registry. Two of them are bundled as hard dependencies (always installed), the rest are opt-in `composer require`:

**Bundled (auto-installed)**, class is autoloadable everywhere this starter runs:

- `filament/spatie-laravel-media-library-plugin`, media library form fields (use inside your Resources via `SpatieMediaLibraryFileUpload::make(...)`)
- `stechstudio/filament-impersonate`: used by the TomatoPHP Users resource, published Users resource, and starter fallback resource. The starter action restricts impersonation to the configured super admin role and redirects within the current panel. STS supplies the leave banner and restores the original admin session.

  ```php
  use EdrisaTuray\FilamentStarterMinimal\Filament\Actions\Impersonate;

  $table->recordActions([Impersonate::make(), /* ... */]);
  ```

  TomatoPHP's built-in impersonation action and banner hook are disabled by the starter to avoid session conflicts. Publish STS settings with `php artisan vendor:publish --tag=filament-impersonate-config`.

## Spatie Media Library ↔ Slimani Media Manager bridge

The starter ships a bridge that mirrors any Spatie Media Library upload into the Slimani Media Manager so the file shows up in the Media Manager UI under a folder hierarchy.

Folder layout:
```
/{parent_property}/{collection_name}/file.ext
```

For example, a `SpatieMediaLibraryFileUpload::make('passport')` on a `User` whose configured `folder_property` is `name` lands in `/alice/passport/passport.jpg`.

### Configuration

Bridging is **opt-in per model**. Configure it in `config/filament-starter-minimal.php`:
```php
'media_manager_bridge' => [
    'enabled' => env('STARTER_MINIMAL_MEDIA_BRIDGE', true),

    'models' => [
        \App\Models\User::class => ['folder_property' => 'name'],
        // \App\Models\Product::class => ['folder_property' => 'slug'],
    ],

    'default_folder_properties' => ['name', 'title', 'slug', 'username'],
    'fallback_folder' => 'Uploads',
],
```

Models NOT listed under `models` are **not** bridged, the original Spatie upload happens, but no Slimani File row is created.

### How it works

The bridge listens for `Media::created` on the Spatie media model. For each new media row whose owner is a configured model, it:

1. Resolves the parent folder name from the configured `folder_property` (e.g. `'name'`), falling back to `default_folder_properties` in order, then to `fallback_folder`.
2. Uses `media.collection_name` (`passport`, `id`, etc.) as the sub-folder name.
3. Creates the folder hierarchy if missing (find-or-create).
4. Creates a `Slimani\MediaManager\Models\File` row in that sub-folder.
5. **Copies** the media into the new File's own Spatie media collection.
6. Creates a `media_attachments` row linking the File polymorphically to the originating model.

### Trade-off: storage is duplicated

The bridge **copies** the physical file (1 file → 2 disk copies). This is intentional: re-attaching the original media to the Slimani File would break `$user->getMedia('passport')`, which is exactly what Filament's `SpatieMediaLibraryFileUpload` calls when rendering the form field.

Copy-and-link keeps both worlds happy: forms keep displaying their uploads, and the Media Manager UI gets a clean folder-organised view.

If the duplication matters for your file sizes, set `STARTER_MINIMAL_MEDIA_BRIDGE=false` and write a thinner bridge tailored to your app.

## Users resource and impersonation

Installation publishes `App\Filament\Resources\Users\UserResource` and its pages as application wrappers around TomatoPHP's Users resource. The integration selects the published resource when available and avoids registering the vendor resource alongside it. Customize the published files in your application.

User impersonation uses `stechstudio/filament-impersonate`. TomatoPHP's built-in impersonation is disabled; STS provides the leave-impersonation banner and restores the original admin session. The starter action permits the configured super admin role.

The package ships a default `UserResource` (`EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource`) with the Impersonate action pre-wired.

**It stands down by default.** `tomatophp/filament-users` is bundled and enabled, and its integration uses the published application resource when available, otherwise the vendor resource. Registering the fallback resource too would duplicate users in navigation. So on any panel where the `filament-users` plugin is enabled (with `options.user_resource` left on), the shipped resource is skipped automatically; no config flag needed. Disable `filament-users` for a panel, via `plugin_defaults` or the Plugin Management UI, to get the shipped resource back.

- **Model** resolves dynamically from `config('filament-starter-minimal.users.model')`, falling back to `config('auth.providers.users.model')` (so `App\Models\User` by default).
- **Authorization** is left to Laravel policies (the installation Shield seeder generates them for managed panels).
- **Disable the fallback resource** when the consuming app registers its own UserResource. Also disable the `filament-users` resource through its `user_resource` option if replacing the TomatoPHP resource:
  ```env
  STARTER_MINIMAL_USER_RESOURCE=false
  ```
  Or in `config/filament-starter-minimal.php`:
  ```php
  'users' => [
      'enabled' => false,
      'model' => null,
  ],
  ```

**Optional (`composer require` as needed)**:

- `filament/spatie-laravel-settings-plugin`, Spatie-Settings-backed pages
- `filament/spatie-laravel-tags-plugin`, tag form components
- `riodwanto/filament-ace-editor`, Ace code editor form field
- `ralphjsmit/laravel-filament-components`, assorted helper components
- `ysfkaya/filament-phone-input`, international phone-number form field
- `owen-it/laravel-auditing`, model trait, not Filament-specific

## Extending the registry

The plugin registry is bound to the container as `PluginRegistryContract`. Register additional plugins (or override package-shipped ones with the same key) via the fluent `withPlugin()` / `withPlugins()` API on the panel plugin:

```php
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use Filament\Panel;
use Vendor\MyPlugin\MyPlugin;

$panel->plugins([
    FilamentStarterMinimalPlugin::make()
        ->withPlugin(new PluginDefinition(
            key: 'my-plugin',
            label: 'My Plugin',
            installer: fn (Panel $p, array $options): Panel => $p->plugin(MyPlugin::make()),
            defaultEnabled: false,
            dangerousToDisable: false,
            defaultOptions: [],
            class: MyPlugin::class,
            package: 'vendor/my-plugin',
        )),
]);
```

You can also resolve the contract directly anywhere in the app (e.g. in another service provider's `boot()`):

```php
use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;

app(PluginRegistryContract::class)->register(new PluginDefinition(...));
```

After registering new definitions, run **Sync from Registry** in the admin UI or call `PluginSyncManager::sync()` so override rows are created.

### Protected dangerous flag

If the package's default catalog ships a definition with `dangerousToDisable: true` (e.g. `filament-shield`), `PluginRegistryContract::register()` will **throw** when a same-key registration tries to silently demote it to `false`. To intentionally override this, pass the explicit opt-in:

```php
app(PluginRegistryContract::class)->register($definition, allowDangerousOverride: true);
```

This blocks the chained-takeover path where a third-party plugin could redefine `filament-shield` with `dangerousToDisable=false` and then disable RBAC.

### `isDangerous()` semantics

`PluginRegistry::isDangerous(string $key, ?string $panelId = null)` returns `true` only if the **registry definition** for `$key` has `dangerousToDisable=true`. The `$panelId` parameter is accepted for API compatibility but ignored, the registry is the single source of truth. The `is_dangerous` DB column is a denormalized cache maintained by the model's `saving` hook; reading it is unnecessary and risks drift.

---

## Caching and invalidation

- Resolved state is cached **forever** per panel under key `starter_minimal_plugins_{panelId}_v{registry-signature}`. The signature changes whenever a `PluginDefinition` is added, removed, or has its `defaultEnabled`/`dangerousToDisable` modified, so registering a plugin via `withPlugin()` automatically misses the old cache.
- Saving or deleting a `PanelPluginOverride` calls **`PluginStateResolver::invalidate()`** for that panel.
- The **Clear Plugin Cache** action in Plugin Management clears the resolver cache for **all** registered Filament panels.
- When **safe mode** is active, resolved state forces `dangerous_to_disable` plugins on before the result is cached; use **`minimal-starter:safe-mode on|off`** so caches are invalidated automatically.

## How sync works

`PluginSyncManager::sync()` is the write path that reconciles your code-level registry against the per-panel override rows in the database. Triggered from the **Sync from Registry** button, the install/update commands, or programmatically.

For every `(managed_panel × registry_plugin)` pair it:
1. Acquires a 60-second `Cache::lock('starter_minimal_sync')` (other concurrent calls block up to 10 seconds, prevents racing inserts during a deploy).
2. Inside a single `DB::transaction()`, calls `PanelPluginOverride::updateOrCreate()` for each pair so model events (audit log + cache invalidation) fire.
3. Deletes any override row whose `plugin_key` is no longer in the registry **or** whose `panel_id` is no longer in `managed_panels`. Each deletion writes an audit row.
4. After commit, invalidates `PluginStateResolver` caches for every registered panel.

Calling `sync()` twice in a row is a no-op on the second call, `updateOrCreate` only writes when fields differ from their defaults.

---

## Shipped defaults

Out of the box, with **zero published config**:

- `managed_panels` is `['admin']`, the panel list the sync, snapshot and doctor commands walk. Every panel that registers `FilamentStarterMinimalPlugin` still gets its plugins; list each one here so those tools see it.
- `superadmin.role` is `super_admin`, the role name checked by every Platform resource. The installer adds `HasRoles` when needed and the user seeder assigns this role. Platform resources require an authenticated user with that role.
- `safe_mode` is `false`.
- This package is **single-tenant**. The override / snapshot / audit tables key by `panel_id` only. The consumer's individual Filament panels may use Filament tenancy themselves, that's orthogonal.
- All registry entries default to enabled. Missing optional dependencies are skipped. Disable plugins per panel using Plugin Management or `plugin_defaults`; Shield is marked dangerous to disable.

## Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| Platform resources invisible to all users | The User model is missing the role trait, or no user has the configured `super_admin` role. Doctor cannot detect this, check `$user->hasRole('super_admin')` directly. |
| Plugin Management UI is empty | No snapshots yet. Run `php artisan minimal-starter:install` (or `:update`) to populate `starter_minimal_panel_snapshots` and the override table. |
| Toggling a plugin in the UI does nothing | Check that the panel registers the plugin, its ID is listed in `managed_panels`, and the dependency is installed. Protected plugins cannot be disabled. Clear the plugin cache after checking overrides. |
| `Cache::rememberForever` returns stale state in production | The cache driver must be persistent (`redis`, `database`, `file`). The resolver caches forever; with `array` driver the cache is recreated cold every request. |
| Doctor reports a plugin's class missing after `composer require` | The vendor renamed its plugin class. Override the entry via `FilamentStarterMinimalPlugin::make()->withPlugin(new PluginDefinition(...))` with the correct FQCN, then run **Sync from Registry**. |
| Shield resources appearing twice | You registered both `FilamentStarterMinimalPlugin::make()` and `FilamentShieldPlugin::make()` on the same panel. Drop the bare Shield registration; this starter installs Shield via the registry. |

## Package layout

```
config/
  filament-starter-minimal.php
  starter-minimal-tools.php
database/
  migrations/
    *_create_starter_minimal_tables.php
src/
  FilamentStarterMinimalServiceProvider.php
  Contracts/
    PluginRegistryContract.php
  Registry/
    DefaultPluginCatalog.php
    PluginDefinition.php
  Filament/
    FilamentStarterMinimalPlugin.php
    PlatformPanelFactory.php
    Resources/
      AuditLogResource.php
      PanelPluginOverrideResource.php
      PanelSnapshotResource.php
      ...
  Models/
    AuditLog.php
    PanelPluginOverride.php
    PanelSnapshot.php
  Support/
    MinimalDoctor.php
    MinimalSafeMode.php
    PanelSnapshotManager.php
    PluginRegistry.php
    PluginStateResolver.php
    PluginSyncManager.php
```

---

## Package development and testing

For local development, add a Composer path repository in the consuming application's `composer.json` pointing to your checkout of this package, then require `edrisaturay/filament-starter-minimal:dev-main`. No other starter package is needed.

The package has its own Pest and Orchestra Testbench suite. Run from the package checkout:

```bash
composer install
vendor/bin/pest
```

## License

MIT. See the package `composer.json` for author metadata.

---

## Support

Source and issues: [edrisaturay/filament-starter-minimal](https://github.com/edrisaturay/filament-starter-minimal). For Filament or Shield behavior, refer to the official **Filament v5** and **Filament Shield** documentation.
