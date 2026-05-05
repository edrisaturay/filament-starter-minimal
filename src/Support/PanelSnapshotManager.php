<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Models\PanelSnapshot;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PanelSnapshotManager
{
    public static function snapshot(): void
    {
        $correlationId = (string) Str::uuid();
        Context::add('filament_starter_minimal.correlation_id', $correlationId);

        Log::info('starter_minimal.snapshot.start', ['correlation_id' => $correlationId]);

        try {
            Cache::lock('starter_minimal_snapshot', 60)->block(10, function (): void {
                self::doSnapshot();
            });

            // Sync inherits the same correlation_id since the Context entry is still set.
            PluginSyncManager::sync();

            Log::info('starter_minimal.snapshot.end', ['correlation_id' => $correlationId, 'ok' => true]);
        } catch (\Throwable $e) {
            Log::warning('starter_minimal.snapshot.end', [
                'correlation_id' => $correlationId,
                'ok' => false,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            Context::forget('filament_starter_minimal.correlation_id');
        }
    }

    private static function doSnapshot(): void
    {
        $panels = Filament::getPanels();

        // Guard against wiping every snapshot when called before panels are
        // registered (e.g. early console boot). A no-op is safer than a
        // destructive prune in that case.
        if (count($panels) === 0) {
            return;
        }

        $currentIds = [];

        DB::transaction(function () use ($panels, &$currentIds): void {
            foreach ($panels as $panel) {
                $id = $panel->getId();
                $currentIds[] = $id;

                PanelSnapshot::query()->updateOrCreate(
                    ['panel_id' => $id],
                    [
                        'meta' => [
                            'path' => $panel->getPath(),
                            'domains' => $panel->getDomains(),
                            'middleware' => array_map(
                                fn ($m): string => is_string($m) ? $m : $m::class,
                                $panel->getMiddleware()
                            ),
                            'tenancy' => $panel->hasTenancy(),
                        ],
                        'last_seen_at' => now(),
                    ]
                );
            }

            PanelSnapshot::query()
                ->whereNotIn('panel_id', $currentIds)
                ->delete();
        });
    }

    /**
     * @return array<int, PanelSnapshot>
     */
    public static function getSnapshots(): array
    {
        return PanelSnapshot::query()->orderBy('panel_id')->get()->all();
    }
}
