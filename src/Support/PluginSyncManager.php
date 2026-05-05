<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PluginSyncManager
{
    public static function sync(): void
    {
        $correlationId = (string) Str::uuid();
        Context::add('filament_starter_minimal.correlation_id', $correlationId);

        Log::info('starter_minimal.sync.start', ['correlation_id' => $correlationId]);

        try {
            Cache::lock('starter_minimal_sync', 60)->block(10, function (): void {
                self::doSync();
            });

            Log::info('starter_minimal.sync.end', ['correlation_id' => $correlationId, 'ok' => true]);
        } catch (\Throwable $e) {
            Log::warning('starter_minimal.sync.end', [
                'correlation_id' => $correlationId,
                'ok' => false,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            Context::forget('filament_starter_minimal.correlation_id');
        }
    }

    private static function doSync(): void
    {
        $panels = Filament::getPanels();
        $registry = app(PluginRegistryContract::class)->all();
        $managedPanels = config('filament-starter-minimal.managed_panels', []);
        $registryKeys = array_keys($registry);

        DB::transaction(function () use ($panels, $registry, $managedPanels, $registryKeys): void {
            foreach ($panels as $panelId => $panel) {
                if (! in_array($panelId, $managedPanels, true)) {
                    continue;
                }

                foreach ($registry as $pluginKey => $definition) {
                    PanelPluginOverride::query()->updateOrCreate(
                        [
                            'panel_id' => $panelId,
                            'plugin_key' => $pluginKey,
                            'tenant_id' => null,
                        ],
                        [
                            'enabled' => $definition->defaultEnabled,
                            'options' => $definition->defaultOptions,
                            'options_version' => 1,
                        ]
                    );
                }
            }

            $stale = PanelPluginOverride::query()
                ->whereNull('tenant_id')
                ->where(function ($query) use ($registryKeys, $managedPanels): void {
                    $query->whereNotIn('plugin_key', $registryKeys)
                        ->orWhereNotIn('panel_id', $managedPanels);
                })
                ->get();

            foreach ($stale as $override) {
                $override->delete();
            }
        });

        foreach ($panels as $panelId => $panel) {
            PluginStateResolver::invalidate($panelId);
        }
    }
}
