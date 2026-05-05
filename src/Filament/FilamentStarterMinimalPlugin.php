<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\AuditLogResource;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelPluginOverrideResource;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelSnapshotResource;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Panel plugin: minimal registry + platform resources (plugin management, snapshots, audit).
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
        $panel
            ->resources([
                PanelPluginOverrideResource::class,
                PanelSnapshotResource::class,
                AuditLogResource::class,
            ]);

        PlatformPanelFactory::build($panel, $panel->getId());
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
