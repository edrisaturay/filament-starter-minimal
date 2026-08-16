<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Panel;

class PlatformPanelFactory
{
    /**
     * Install every enabled registry plugin into the panel.
     *
     * @param  bool  $force  Skip the `managed_panels` check. Registering
     *                       FilamentStarterMinimalPlugin on a panel is itself an
     *                       explicit request to manage it, so the plugin passes
     *                       true — otherwise a panel missing from
     *                       `managed_panels` would silently get no plugins at
     *                       all, which surfaces later as Filament's
     *                       "Plugin [x] is not registered for panel [y]".
     */
    public static function build(Panel $panel, string $panelId, bool $force = false): Panel
    {
        $managedPanels = config('filament-starter-minimal.managed_panels', []);

        if (! $force && ! in_array($panelId, $managedPanels, true)) {
            return $panel;
        }

        $states = PluginStateResolver::resolve($panelId);
        $registry = app(PluginRegistryContract::class);

        foreach ($states as $key => $state) {
            if (! $state['enabled']) {
                continue;
            }

            $definition = $registry->get($key);
            if ($definition === null) {
                continue;
            }

            ($definition->installer)($panel, $state['options']);
        }

        return $panel;
    }
}
