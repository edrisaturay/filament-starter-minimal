<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PluginStateResolver
{
    /**
     * Resolve each registry plugin's state for a panel. Options are layered:
     * registry defaults, then `plugin_defaults` config, then the DB override
     * row — each layer only replaces the keys it sets, so an option added to a
     * definition later still reaches panels whose override rows predate it.
     *
     * State is only cached when the override table could be read; otherwise
     * (e.g. the panel boots before migrations ran) the defaults-only state
     * would be cached forever.
     *
     * @return array<string, array{enabled: bool, options: array<string, mixed>}>
     */
    public static function resolve(string $panelId): array
    {
        $cacheKey = self::cacheKey($panelId);

        try {
            $cached = Cache::get($cacheKey);
        } catch (\Throwable $exception) {
            report($exception);

            return self::compute($panelId)['state'];
        }

        if (is_array($cached)) {
            return $cached;
        }

        ['state' => $state, 'complete' => $complete] = self::compute($panelId);

        if ($complete) {
            try {
                Cache::forever($cacheKey, $state);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $state;
    }

    public static function invalidate(string $panelId): void
    {
        Cache::forget(self::cacheKey($panelId));
    }

    protected static function cacheKey(string $panelId): string
    {
        $registry = app(PluginRegistryContract::class);

        return "starter_minimal_plugins_{$panelId}_v{$registry->signature()}";
    }

    /**
     * @return array{state: array<string, array{enabled: bool, options: array<string, mixed>}>, complete: bool}
     */
    protected static function compute(string $panelId): array
    {
        $definitions = app(PluginRegistryContract::class)->all();
        $configDefaults = config("filament-starter-minimal.plugin_defaults.{$panelId}", []);
        $resolved = [];

        foreach ($definitions as $key => $definition) {
            $resolved[$key] = [
                'enabled' => (bool) ($configDefaults[$key]['enabled'] ?? $definition->defaultEnabled),
                'options' => array_replace(
                    $definition->defaultOptions,
                    (array) ($configDefaults[$key]['options'] ?? []),
                ),
            ];
        }

        $complete = true;

        try {
            if (! Schema::hasTable((new PanelPluginOverride)->getTable())) {
                $complete = false;
            } else {
                $overrides = PanelPluginOverride::query()
                    ->where('panel_id', $panelId)
                    ->get();

                foreach ($overrides as $override) {
                    if (! isset($resolved[$override->plugin_key])) {
                        continue;
                    }

                    if ($override->enabled !== null) {
                        $resolved[$override->plugin_key]['enabled'] = (bool) $override->enabled;
                    }

                    if ($override->options !== null) {
                        $options = is_array($override->options)
                            ? $override->options
                            : (json_decode($override->options, true) ?? []);

                        $resolved[$override->plugin_key]['options'] = array_replace(
                            $resolved[$override->plugin_key]['options'],
                            $options,
                        );
                    }
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
            $complete = false;
        }

        if (MinimalSafeMode::isActive()) {
            foreach ($definitions as $key => $definition) {
                if ($definition->dangerousToDisable) {
                    $resolved[$key]['enabled'] = true;
                }
            }
        }

        return ['state' => $resolved, 'complete' => $complete];
    }
}
