<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Panel;

class PlatformPanelFactory
{
    public static function build(Panel $panel, string $panelId): Panel
    {
        $managedPanels = config('filament-starter-minimal.managed_panels', []);

        if (! in_array($panelId, $managedPanels, true)) {
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
