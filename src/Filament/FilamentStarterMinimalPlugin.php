<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\AuditLogResource;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelPluginOverrideResource;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelSnapshotResource;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource;
use EdrisaTuray\FilamentStarterMinimal\Registry\DefaultPluginCatalog;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Panel plugin: minimal registry + platform resources (plugin management, snapshots, audit).
 *
 * Registering this on a panel installs every enabled plugin in the registry —
 * which, by default, is every plugin in DefaultPluginCatalog whose composer
 * package is installed. There is no auto-registration: a panel that omits this
 * from ->plugins([...]) gets none of the stack, which is how you end up with
 * Filament's "Plugin [x] is not registered for panel [y]" when a sibling panel
 * does register it (Filament resolves plugins off the *default* panel while
 * building routes).
 *
 * Usage:
 *   ->plugins([
 *       FilamentStarterMinimalPlugin::make()
 *           ->withPlugin(new PluginDefinition(
 *               key: 'my-plugin',
 *               label: 'My Plugin',
 *               installer: fn (Panel $p, array $opts): Panel => $p->plugin(MyPlugin::make()),
 *               class: MyPlugin::class,
 *               package: 'vendor/my-plugin',
 *           )),
 *   ])
 */
class FilamentStarterMinimalPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-starter-minimal';
    }

    public function register(Panel $panel): void
    {
        $panelId = $panel->getId();

        $resources = [
            PanelPluginOverrideResource::class,
            PanelSnapshotResource::class,
            AuditLogResource::class,
        ];

        if ($this->shouldRegisterUserResource($panelId)) {
            $resources[] = UserResource::class;
        }

        $panel->resources($resources);

        PlatformPanelFactory::build($panel, $panelId, force: true);
    }

    /**
     * The shipped UserResource stands down when tomatophp/filament-users is
     * installing its own — both cover the same model, so registering both
     * collides on slug and navigation.
     */
    protected function shouldRegisterUserResource(string $panelId): bool
    {
        if (! config('filament-starter-minimal.users.enabled', true)) {
            return false;
        }

        return ! DefaultPluginCatalog::claimsUserResource(
            PluginStateResolver::resolve($panelId),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Register an additional plugin definition into the shared registry. Last
     * write wins, so consumers may override package-shipped definitions by
     * passing the same key. Definitions registered here are persisted by the
     * sync manager into the overrides table on the next sync run.
     */
    public function withPlugin(PluginDefinition $definition): static
    {
        app(PluginRegistryContract::class)->register($definition);

        return $this;
    }

    /**
     * Bulk-register many plugin definitions in one call.
     *
     * @param  array<int, PluginDefinition>  $definitions
     */
    public function withPlugins(array $definitions): static
    {
        foreach ($definitions as $definition) {
            $this->withPlugin($definition);
        }

        return $this;
    }
}
