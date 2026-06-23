# Filament Starter Minimal

A **Laravel package** that ships a small, **Filament v5**-compatible “starter” layer: a **panel plugin** (`FilamentStarterMinimalPlugin`), a **plugin registry** (Shield-first), **database-backed overrides**, **cached plugin state**, and **Filament resources** for platform administration (plugin management, panel snapshots, audit log).

It is intentionally **minimal** compared to [`edrisaturay/filament-starter`](https://github.com/edrisaturay/filament-starter): fewer bundled plugins, no multi-plugin publish wizard, and a **smaller** doctor surface—but it still ships **Artisan commands** in the same spirit as the full starter (`install`, `update`, `doctor`, `safe-mode`).

---

## Artisan commands

These mirror the **full starter** commands with a `minimal-starter:` prefix and lighter behavior.

| Full starter (`edrisaturay/filament-starter`) | This package |
|-----------------------------------------------|----------------|
| `php artisan starter:install` | `php artisan minimal-starter:install` |
| `php artisan starter:update` | `php artisan minimal-starter:update` |
| `php artisan starter:doctor` | `php artisan minimal-starter:doctor` |
| `php artisan starter:safe-mode on\|off` | `php artisan minimal-starter:safe-mode on\|off` |

### `minimal-starter:install`

- Optionally publishes config (`--publish-config`, or interactive confirm when not `--no-interaction`).
- Runs `php artisan migrate --force`.
- Calls `PanelSnapshotManager::snapshot()` (snapshots + plugin override sync).

```bash
php artisan minimal-starter:install
php artisan minimal-starter:install --publish-config --no-interaction
```

### `minimal-starter:update`

Runs `migrate --force`, then `PanelSnapshotManager::snapshot()`. Use after deploy or dependency updates.

```bash
php artisan minimal-starter:update
```

### `minimal-starter:doctor`

Runs health checks: database connectivity, minimal migrations present, Filament / Shield classes, snapshot row count, each `managed_panels` id registered with Filament, and that each registry plugin class exists.

Exit codes:
- `0` — every check passed or only `warning`-level findings
- `1` — at least one **`critical`** finding (DB unreachable, Filament missing, registered plugin's class not autoloadable, etc.)

`warning`-level findings (e.g. a managed panel id that isn't registered with Filament yet, zero snapshots) are reported but **do not fail the command** — operators can wire CI on `doctor || exit 1` without false negatives.

```bash
php artisan minimal-starter:doctor
```

### `minimal-starter:safe-mode`

When **on**, plugins marked `dangerous_to_disable` in the registry are forced **enabled** in resolved state (same idea as the full starter). Implemented via cache key `starter_minimal_safe_mode_active`, optional env `STARTER_MINIMAL_SAFE_MODE`, or config `filament-starter-minimal.safe_mode`. Toggling clears plugin state cache for all Filament panels.

```bash
php artisan minimal-starter:safe-mode on
php artisan minimal-starter:safe-mode off
```

**Note:** In `testing` env, safe mode reads as inactive (matches full starter behavior for tests).

---

## Requirements

- PHP **^8.3**
- Laravel **12** (`illuminate/support` ^12)
- **Filament** ^5 (`filament/filament`)
- **Filament Shield** ^4 (`bezhansalleh/filament-shield`)

Your app should use a **Spatie-style role** on the authenticated user (e.g. `hasRole('super_admin')`) for the built-in Platform resources.

---

## Installation

### Composer

```bash
composer require edrisaturay/filament-starter-minimal
```

Or add a **path repository** while developing:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/edrisaturay/filament-starter-minimal",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "edrisaturay/filament-starter-minimal": "dev-main"
    }
}
```

The package auto-registers `FilamentStarterMinimalServiceProvider` via Composer’s `extra.laravel.providers`.

### Configuration (optional)

Publish the config file to customize panel IDs, defaults, or the super-admin role:

```bash
php artisan vendor:publish --tag=filament-starter-minimal-config
```

### Migrations

Migrations are **loaded from the package** automatically (`loadMigrationsFrom`). Run:

```bash
php artisan migrate
```

If you prefer **copies** of migration files in your app (e.g. for customization), publish them:

```bash
php artisan vendor:publish --tag=filament-starter-minimal-migrations
php artisan migrate
```

---

## Quick start

### 1. Register the panel plugin

In your `PanelProvider` (or wherever you configure the panel), add **one** plugin entry. Do **not** also register `FilamentShieldPlugin::make()` separately unless you intend to load Shield twice.

```php
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentStarterMinimalPlugin::make(),
        ]);
}
```

### 2. Align config with your panel ID

Edit `config/filament-starter-minimal.php` (or the merged defaults):

- Set **`managed_panels`** to every Filament **panel id** that should use the registry and receive registry-driven plugins (e.g. `['admin', 'staff']`).
- Under **`plugin_defaults`**, add a block for **each** of those panel IDs and each **plugin key** returned by `PluginRegistry::getPlugins()` (see [Extending the registry](#extending-the-registry)).

If a panel’s id is **not** in `managed_panels`, `PlatformPanelFactory` will **not** attach registry plugins to that panel (but the Filament resources are still registered on whichever panels load `FilamentStarterMinimalPlugin`—restrict access with policies / `canViewAny()` as needed).

### 3. Seed snapshots and plugin override rows

The **Plugin Management** UI expects **panel snapshots** (and sync populates **overrides**). After your panels are registered, call:

```php
use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;

PanelSnapshotManager::snapshot();
```

Typical places: `AppServiceProvider::boot()` (after Filament is ready), a dedicated Artisan command, or your deployment hook **once** per environment.

### 4. Filament Shield

Follow Shield’s own docs: run `shield:install`, configure `config/filament-shield.php`, generate permissions, and assign the **super admin** role configured in `filament-starter-minimal.superadmin.role` (default `super_admin`) to users who should access **Platform** resources.

---

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
| `managed_panels` | Panel IDs that receive **registry** installers via `PlatformPanelFactory`. |
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

All tables use the **`starter_minimal_`** prefix so this package can coexist with **`edrisaturay/filament-starter`** (which uses `starter_*` without `_minimal_`).

| Table | Purpose |
|-------|---------|
| `starter_minimal_panel_plugin_overrides` | Per-panel, per-plugin `enabled`, `is_dangerous`, `options`. Also `options_version` (currently always `1`, reserved for future schema migrations) and `updated_by_user_id` (reserved; not yet auto-set by sync — populate manually if needed). Unique on `(panel_id, plugin_key)`. |
| `starter_minimal_panel_snapshots` | Latest known Filament panel metadata (`path`, domains, middleware, and the consumer panel's `hasTenancy()` flag — informational only; this package itself is not multi-tenant aware). |
| `starter_minimal_audit_logs` | Append-only log for plugin sync and override changes. Immutable — `update`/`delete` throw at the model layer. `actor_user_id` is auto-injected from `auth()->id()` and is **not** mass-assignable. |

**Dangerous plugins:** If `is_dangerous` is true, the `PanelPluginOverride` model forces `enabled` to stay true (same behavior as the full starter).

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

The default catalog is registered for you in `Registry\DefaultPluginCatalog`. Every entry is **disabled by default** (except `filament-shield`) and every installer is `class_exists`-guarded — so the package can list a plugin without forcing you to `composer require` it. Toggle plugins on per-panel from **Plugin Management** in the admin UI, or pre-configure in `config/filament-starter-minimal.php`.

| Key | Package (`composer require ...`) | Notes |
|-----|----------------------------------|-------|
| `filament-shield` | `bezhansalleh/filament-shield` | RBAC. `dangerous_to_disable` — safe-mode forces it on. |
| `filament-panel-switch` | `bezhansalleh/filament-panel-switch` | Configures globally via `PanelSwitch::configureUsing()`. |
| `filament-exceptions` | `bezhansalleh/filament-exceptions` | Adds an Exceptions resource. |
| `filament-language-switch` | `bezhansalleh/filament-language-switch` | Configures globally. Pass `options.locales` to set available locales. |
| `filament-breezy` | `jeffgreco13/filament-breezy` | Profile + 2FA flows. |
| `filament-logger` | `z3d0x/filament-logger` | Activity log UI. |
| `filament-connection-badge` | `rawand201/filament-connection-badge` | DB connection indicator. |
| `filament-media-manager` | `tomatophp/filament-media-manager` | Folder/file media manager. |
| `filament-menu-builder` | `datlechin/filament-menu-builder` | Drag-and-drop navigation builder. |
| `filament-api-service` | `rupadana/filament-api-service` | Generates REST endpoints from Resources. |
| `filament-resource-lock` | `kenepa/resource-lock` | Prevents concurrent edits on a resource. |
| `filament-spatie-health` | `shuvroroy/filament-spatie-laravel-health` | UI for `spatie/laravel-health`. |
| `filament-spatie-backup` | `shuvroroy/filament-spatie-laravel-backup` | UI for `spatie/laravel-backup`. |
| `filament-global-search-modal` | `charrafimed/global-search-modal` | Richer global search UI. |
| `filament-knowledge-base` | `guava/filament-knowledge-base` | Markdown docs panel (`KnowledgeBasePlugin`). Enable on your `knowledge-base` panel id only. |
| `filament-knowledge-base-companion` | `guava/filament-knowledge-base` | Links admin (and other) panels to the KB (`KnowledgeBaseCompanionPlugin`). |
| `filament-spotlight` | `pxlrbt/filament-spotlight` | ⌘K spotlight launcher. |
| `filament-quick-create` | `awcodes/filament-quick-create` | Topbar quick-create dropdown. Options: `excludes`, `includes`, `sort_by`. |

Run `php artisan minimal-starter:doctor` after `composer require` to confirm the class autoloads — if a vendor renames their plugin class between versions, override the entry via `withPlugin()` (next section).

### Knowledge Base (Guava)

This package **requires** `guava/filament-knowledge-base` ^3.x (Filament v5). You need **two** Filament panels:

1. A dedicated **knowledge-base** panel with `KnowledgeBasePlugin` (registry key `filament-knowledge-base`).
2. Your main panel(s) with `KnowledgeBaseCompanionPlugin` (registry key `filament-knowledge-base-companion`).

Defaults in the published config enable the KB plugin on `knowledge-base` and leave the companion **off** on `admin` until you toggle it in Plugin Management or `plugin_defaults`.

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

## Companion packages (not panel plugins)

These packages don't implement Filament's `Plugin` contract — they ship **form components**, **traits**, or **action classes** you compose into your own Resources. They're not in the registry. Two of them are bundled as hard dependencies (always installed), the rest are opt-in `composer require`:

**Bundled (auto-installed)** — class is autoloadable everywhere this starter runs:

- `filament/spatie-laravel-media-library-plugin` — media library form fields (use inside your Resources via `SpatieMediaLibraryFileUpload::make(...)`)
- `stechstudio/filament-impersonate` — table/page action class. Auto-wired into the shipped `UserResource` (see [Shipped UserResource](#shipped-userresource) below). If you ship your own `UserResource`, add manually:
  ```php
  use STS\FilamentImpersonate\Actions\Impersonate;

  // In your UserResource::table()
  $table->recordActions([Impersonate::make(), /* ... */]);

  // In your EditUser::getHeaderActions()
  return [Impersonate::make()->record($this->getRecord())];
  ```
  If your panel uses `->spa()`, chain `->withoutSpa()` when redirecting outside Filament.

## Shipped UserResource

The package ships a default `UserResource` (`EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource`) with the Impersonate action pre-wired — so a fresh install gets a working users CRUD with row-level impersonation out of the box.

- **Model** resolves dynamically from `config('filament-starter-minimal.users.model')`, falling back to `config('auth.providers.users.model')` (so `App\Models\User` by default).
- **Authorization** is left to Laravel policies (Filament Shield generates them automatically when you run `php artisan shield:install`).
- **Disable** when the consuming app already registers its own UserResource (e.g. `tomatophp/filament-users`):
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

- `filament/spatie-laravel-settings-plugin` — Spatie-Settings-backed pages
- `filament/spatie-laravel-tags-plugin` — tag form components
- `riodwanto/filament-ace-editor` — Ace code editor form field
- `ralphjsmit/laravel-filament-components` — assorted helper components
- `ysfkaya/filament-phone-input` — international phone-number form field
- `owen-it/laravel-auditing` — model trait, not Filament-specific

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

`PluginRegistry::isDangerous(string $key, ?string $panelId = null)` returns `true` only if the **registry definition** for `$key` has `dangerousToDisable=true`. The `$panelId` parameter is accepted for API compatibility but ignored — the registry is the single source of truth. The `is_dangerous` DB column is a denormalized cache maintained by the model's `saving` hook; reading it is unnecessary and risks drift.

---

## Caching and invalidation

- Resolved state is cached **forever** per panel under key `starter_minimal_plugins_{panelId}_v{registry-signature}`. The signature changes whenever a `PluginDefinition` is added, removed, or has its `defaultEnabled`/`dangerousToDisable` modified — so registering a plugin via `withPlugin()` automatically misses the old cache.
- Saving or deleting a `PanelPluginOverride` calls **`PluginStateResolver::invalidate()`** for that panel.
- The **Clear Plugin Cache** action in Plugin Management clears the resolver cache for **all** registered Filament panels.
- When **safe mode** is active, resolved state forces `dangerous_to_disable` plugins on before the result is cached; use **`minimal-starter:safe-mode on|off`** so caches are invalidated automatically.

## How sync works

`PluginSyncManager::sync()` is the write path that reconciles your code-level registry against the per-panel override rows in the database. Triggered from the **Sync from Registry** button, the install/update commands, or programmatically.

For every `(managed_panel × registry_plugin)` pair it:
1. Acquires a 60-second `Cache::lock('starter_minimal_sync')` (other concurrent calls block up to 10 seconds — prevents racing inserts during a deploy).
2. Inside a single `DB::transaction()`, calls `PanelPluginOverride::updateOrCreate()` for each pair so model events (audit log + cache invalidation) fire.
3. Deletes any override row whose `plugin_key` is no longer in the registry **or** whose `panel_id` is no longer in `managed_panels`. Each deletion writes an audit row.
4. After commit, invalidates `PluginStateResolver` caches for every registered panel.

Calling `sync()` twice in a row is a no-op on the second call — `updateOrCreate` only writes when fields differ from their defaults.

---

## Shipped defaults

Out of the box, with **zero published config**:

- `managed_panels` is `['admin']` — only a panel with id `admin` receives registry plugins from `PlatformPanelFactory`.
- `superadmin.role` is `super_admin` — the role name checked by every Platform resource. **Your User model must have `spatie/laravel-permission`'s `HasRoles` trait** (or another implementation that exposes a `hasRole(string $role)` method), and a role with that name must exist and be assigned. If neither is true, the resources are simply invisible to all users (closed-default).
- `safe_mode` is `false`.
- This package is **single-tenant**. The override / snapshot / audit tables key by `panel_id` only. The consumer's individual Filament panels may use Filament tenancy themselves — that's orthogonal.
- The registry seeds `filament-shield` (`enabled = true`, `dangerous_to_disable = true`); every other plugin in the catalog is registered as `enabled = false`. Toggle them on per-panel via Plugin Management or via `plugin_defaults` in the published config.

## Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| Platform resources invisible to all users | The User model is missing the role trait, or no user has the configured `super_admin` role. Doctor cannot detect this — check `$user->hasRole('super_admin')` directly. |
| Plugin Management UI is empty | No snapshots yet. Run `php artisan minimal-starter:install` (or `:update`) to populate `starter_minimal_panel_snapshots` and the override table. |
| Toggling a plugin in the UI does nothing | Check that the panel id is in `managed_panels`. `PlatformPanelFactory` short-circuits non-managed panels even though the resources still appear. |
| `Cache::rememberForever` returns stale state in production | The cache driver must be persistent (`redis`, `database`, `file`). The resolver caches forever; with `array` driver the cache is recreated cold every request. |
| Doctor reports a plugin's class missing after `composer require` | The vendor renamed its plugin class. Override the entry via `FilamentStarterMinimalPlugin::make()->withPlugin(new PluginDefinition(...))` with the correct FQCN, then run **Sync from Registry**. |
| Shield resources appearing twice | You registered both `FilamentStarterMinimalPlugin::make()` and `FilamentShieldPlugin::make()` on the same panel. Drop the bare Shield registration; this starter installs Shield via the registry. |

## Commands reference

| Command | Purpose | Exits non-zero on |
|---------|---------|-------------------|
| `minimal-starter:install` | Publish config (optional), `migrate --force`, snapshot + sync | `migrate` non-zero exit OR any `Throwable` from snapshot/sync |
| `minimal-starter:update` | `migrate --force`, snapshot + sync. Re-runnable. | Same |
| `minimal-starter:doctor` | Health checks (see above) | Any `critical` finding |
| `minimal-starter:safe-mode {on\|off}` | Toggle the safe-mode cache flag, invalidate plugin caches | Invalid argument |

All commands accept `--no-interaction` and respect Laravel's standard verbosity flags.

## Coexistence with `edrisaturay/filament-starter`

- **Different DB tables** (`starter_minimal_*` vs `starter_*`).
- **Different Filament plugin id** (`filament-starter-minimal` vs `filament-starter`).
- **Different config file** (`filament-starter-minimal.php` vs `filament-starter.php`).

You should not load **both** starter panel plugins on the same panel if they both register Shield or duplicate resources—pick one package per panel.

---

## Package layout

```
config/
  filament-starter-minimal.php
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

## Testing (in a consuming app)

The reference application includes Pest tests under `tests/Unit/FilamentStarterMinimalTest.php` that assert registry shape, config resolution, DB merge behavior (with a targeted `migrate --path` when needed), and plugin id stability.

---

## License

MIT. See the package `composer.json` for author metadata.

---

## Support

Issues and source: see the monorepo or package repository you publish from. For Filament or Shield behavior, refer to the official **Filament v5** and **Filament Shield** documentation.
