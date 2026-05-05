<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use Illuminate\Support\Facades\Cache;

class PluginStateResolver
{
    /**
     * @return array<string, array{enabled: bool, options: array<string, mixed>}>
     */
    public static function resolve(string $panelId): array
    {
        $registry = app(PluginRegistryContract::class);
        $cacheKey = "starter_minimal_plugins_{$panelId}_v{$registry->signature()}";

        $computeState = function () use ($panelId, $registry): array {
            $definitions = $registry->all();
            $resolved = [];

            $configDefaults = config("filament-starter-minimal.plugin_defaults.{$panelId}", []);

            foreach ($definitions as $key => $definition) {
                $enabled = $configDefaults[$key]['enabled'] ?? $definition->defaultEnabled;
                $options = $configDefaults[$key]['options'] ?? $definition->defaultOptions;

                $resolved[$key] = [
                    'enabled' => $enabled,
                    'options' => $options,
                ];
            }

            try {
                $overrides = PanelPluginOverride::query()
                    ->where('panel_id', $panelId)
                    ->get();

                foreach ($overrides as $override) {
                    if (isset($resolved[$override->plugin_key])) {
                        if ($override->enabled !== null) {
                            $resolved[$override->plugin_key]['enabled'] = (bool) $override->enabled;
                        }
                        if ($override->options !== null) {
                            $resolved[$override->plugin_key]['options'] = is_array($override->options)
                                ? $override->options
                                : (json_decode($override->options, true) ?? []);
                        }
                    }
                }
            } catch (\Exception $e) {
                report($e);
            }

            if (MinimalSafeMode::isActive()) {
                foreach ($definitions as $key => $definition) {
                    if ($definition->dangerousToDisable) {
                        $resolved[$key]['enabled'] = true;
                    }
                }
            }

            return $resolved;
        };

        try {
            return Cache::rememberForever($cacheKey, $computeState);
        } catch (\Throwable $exception) {
            report($exception);

            return $computeState();
        }
    }

    public static function invalidate(string $panelId): void
    {
        $registry = app(PluginRegistryContract::class);
        Cache::forget("starter_minimal_plugins_{$panelId}_v{$registry->signature()}");
    }
}
