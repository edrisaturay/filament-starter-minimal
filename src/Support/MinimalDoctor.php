<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelSnapshot;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MinimalDoctor
{
    /**
     * @return array<int, array{check: string, status: string, message: string}>
     */
    public function check(): array
    {
        $results = [];

        // DB connection — if this fails, every subsequent probe also will, so abort early.
        try {
            DB::connection()->getPdo();
            $results[] = $this->row('Database Connection', 'ok', 'Database is reachable.');
        } catch (\Throwable $exception) {
            $results[] = $this->row(
                'Database Connection',
                'critical',
                'Database connection failed: '.$exception->getMessage(),
            );

            return $results;
        }

        $results[] = $this->guarded('Minimal starter tables', function (): array {
            $present = Schema::hasTable('starter_minimal_panel_plugin_overrides');

            return [
                $present ? 'ok' : 'critical',
                $present
                    ? 'Starter minimal migrations are applied.'
                    : 'Run php artisan migrate (or minimal-starter:install).',
            ];
        });

        $results[] = $this->guarded('Filament', fn (): array => [
            class_exists(Panel::class) ? 'ok' : 'critical',
            class_exists(Panel::class) ? 'Filament Panel class is available.' : 'Filament is not installed.',
        ]);

        if (Schema::hasTable('starter_minimal_panel_snapshots')) {
            $results[] = $this->guarded('Panel Snapshots', function (): array {
                $count = PanelSnapshot::query()->count();

                return [
                    $count > 0 ? 'ok' : 'warning',
                    "Found {$count} panel snapshot(s). Run minimal-starter:install or minimal-starter:update if empty.",
                ];
            });
        }

        foreach (config('filament-starter-minimal.managed_panels', []) as $panelId) {
            $results[] = $this->guarded("Filament panel: {$panelId}", function () use ($panelId): array {
                $panel = Filament::getPanel($panelId, isStrict: false);

                return [
                    $panel !== null ? 'ok' : 'warning',
                    $panel !== null
                        ? "Panel \"{$panelId}\" is registered."
                        : "Panel \"{$panelId}\" is in managed_panels but not registered with Filament.",
                ];
            });
        }

        foreach (app(PluginRegistryContract::class)->all() as $key => $definition) {
            $class = $definition->class;
            if ($class === null) {
                continue;
            }

            $results[] = $this->guarded("Plugin dependency: {$key}", function () use ($class, $definition): array {
                if (class_exists($class)) {
                    return ['ok', "Class {$class} is autoloadable."];
                }

                // Every definition is default-enabled, so a missing class only
                // means the optional package isn't installed — informational,
                // unless the plugin is one the starter treats as load-bearing.
                $status = $definition->dangerousToDisable ? 'critical' : 'info';

                return [
                    $status,
                    "Class {$class} not found (package {$definition->package} may be missing).",
                ];
            });
        }

        return $results;
    }

    /**
     * @return array{check: string, status: string, message: string}
     */
    private function row(string $check, string $status, string $message): array
    {
        return ['check' => $check, 'status' => $status, 'message' => $message];
    }

    /**
     * Run a probe inside its own try/catch so one failure can't abort the whole report.
     *
     * @param  callable(): array{0: string, 1: string}  $probe
     * @return array{check: string, status: string, message: string}
     */
    private function guarded(string $check, callable $probe): array
    {
        try {
            [$status, $message] = $probe();

            return $this->row($check, $status, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->row($check, 'critical', $e->getMessage());
        }
    }
}
