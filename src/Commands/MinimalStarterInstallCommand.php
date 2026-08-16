<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Jeffgreco13\FilamentBreezy\FilamentBreezyServiceProvider;
use Spatie\Health\HealthServiceProvider;

use function Laravel\Prompts\multiselect;

class MinimalStarterInstallCommand extends Command
{
    protected $signature = 'minimal-starter:install
                            {--publish-config : Publish filament-starter-minimal.php to the config directory}
                            {--publish-all : Publish all plugin configs/migrations without asking}
                            {--force : Re-run install even if it has been completed before}';

    protected $description = 'Publish config, migrate, sync panels, and set up Filament Starter Minimal plugins.';

    public function handle(): int
    {
        $this->info('Filament Starter Minimal — install');

        try {
            $this->publishOwnConfig();

            $this->info('Running base migrations...');
            $exit = $this->call('migrate', ['--force' => true]);
            if ($exit !== 0) {
                $this->error('migrate failed with exit code '.$exit);

                return self::FAILURE;
            }

            $this->info('Snapshotting panels and syncing plugin registry rows...');
            PanelSnapshotManager::snapshot();

            $this->configurePlugins();

            $this->info('Running migrations again for any plugin migrations published above...');
            $this->call('migrate', ['--force' => true]);

            $this->setupShield();

            $this->info('minimal-starter:install completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('minimal-starter:install failed: '.$e->getMessage());
            $this->line('Run `php artisan minimal-starter:doctor` for diagnostics.');

            return self::FAILURE;
        }
    }

    protected function publishOwnConfig(): void
    {
        if ($this->option('publish-config')
            || (! $this->option('no-interaction') && $this->confirm('Publish config/filament-starter-minimal.php?', false))
        ) {
            $this->call('vendor:publish', [
                '--tag' => 'filament-starter-minimal-config',
                '--force' => true,
            ]);
        }
    }

    protected function configurePlugins(): void
    {
        /** @var array<string, PluginDefinition> $available */
        $available = collect(app(PluginRegistryContract::class)->all())
            ->filter(fn (PluginDefinition $d): bool => $d->isAvailable())
            ->all();

        if ($available === []) {
            $this->warn('No registered plugins are installed. Install the suggested composer packages and re-run.');

            return;
        }

        $options = collect($available)
            ->mapWithKeys(fn (PluginDefinition $d, string $key): array => [$key => $d->label])
            ->all();

        $this->publishPluginAssets($available, $options);

        if ($this->option('no-interaction')) {
            $this->applyDefaultPanelOverrides($available);

            return;
        }

        $this->activatePluginsPerPanel($available, $options);
        $this->markDangerousPlugins($available, $options);
    }

    /**
     * @param  array<string, PluginDefinition>  $plugins
     * @param  array<string, string>  $options
     */
    protected function publishPluginAssets(array $plugins, array $options): void
    {
        if ($this->option('publish-all')) {
            $toPublish = array_keys($plugins);
        } elseif ($this->option('no-interaction')) {
            $toPublish = array_keys($plugins);
        } else {
            $toPublish = $this->multiSelect(
                'Which plugins should have configs/migrations published?',
                array_merge(['none' => 'none', 'all' => 'all'], $options),
                ['all']
            );

            if (in_array('none', $toPublish, true)) {
                return;
            }

            if (in_array('all', $toPublish, true)) {
                $toPublish = array_keys($plugins);
            }
        }

        foreach ($toPublish as $key) {
            $definition = $plugins[$key] ?? null;
            if (! $definition instanceof PluginDefinition) {
                continue;
            }

            $installCommand = $this->pluginsWithInstallCommand()[$key] ?? null;
            if ($installCommand !== null) {
                if (! $this->canRunPluginInstallCommand($key)) {
                    continue;
                }

                $this->info("Running {$definition->label} install ({$installCommand})...");
                $this->call($installCommand);

                continue;
            }

            $this->publishPluginConfig($definition);
            $this->publishPluginMigrations($key);
        }
    }

    /**
     * Plugins that ship their own install command. Each entry is keyed by the
     * registry plugin key and points at an Artisan command that publishes
     * configs/migrations and (where applicable) seeds the package.
     *
     * @return array<string, string>
     */
    protected function pluginsWithInstallCommand(): array
    {
        return [
            'filament-shield' => 'shield:install',
            // mradder/filament-logger uses Spatie's PackageServiceProvider, which
            // exposes an install command named `{package-name}:install`.
            'filament-logger' => 'filament-logger:install',
        ];
    }

    protected function publishPluginConfig(PluginDefinition $definition): void
    {
        if ($definition->package === null) {
            return;
        }

        $tag = $this->configPublishTags()[$definition->package] ?? null;

        if ($tag !== null) {
            $this->info("Publishing config for {$definition->label}...");
            $this->call('vendor:publish', ['--tag' => $tag]);

            return;
        }

        if ($definition->class !== null && class_exists($definition->class)) {
            $this->info("Publishing config for {$definition->label}...");
            $this->call('vendor:publish', [
                '--provider' => $definition->class,
                '--tag' => 'config',
            ]);
        }
    }

    protected function publishPluginMigrations(string $key): void
    {
        $publisher = $this->pluginMigrationPublishers()[$key] ?? null;

        if ($publisher === null) {
            return;
        }

        if ($this->hasTableForMigration($publisher['table'])) {
            return;
        }

        if ($this->hasMigrationPublished($publisher['migration_glob'])) {
            return;
        }

        $arguments = ['--tag' => $publisher['tag']];
        if (! empty($publisher['provider'])) {
            $arguments['--provider'] = $publisher['provider'];
        }

        $this->info("Publishing {$publisher['label']} migrations...");
        $this->call('vendor:publish', $arguments);
    }

    /**
     * @param  array<string, PluginDefinition>  $plugins
     * @param  array<string, string>  $options
     */
    protected function activatePluginsPerPanel(array $plugins, array $options): void
    {
        $managedPanels = config('filament-starter-minimal.managed_panels', []);

        foreach ($managedPanels as $panelId) {
            $defaults = $this->panelDefaults($panelId, $plugins);

            $selected = $this->multiSelect(
                "Which plugins should be ENABLED in the '{$panelId}' panel?",
                $options,
                $defaults
            );

            foreach ($plugins as $key => $_definition) {
                PanelPluginOverride::query()->updateOrCreate(
                    ['panel_id' => $panelId, 'plugin_key' => $key],
                    ['enabled' => in_array($key, $selected, true)]
                );
            }
        }
    }

    /**
     * @param  array<string, PluginDefinition>  $plugins
     * @param  array<string, string>  $options
     */
    protected function markDangerousPlugins(array $plugins, array $options): void
    {
        $dangerousDefaults = collect($plugins)
            ->filter(fn (PluginDefinition $d): bool => $d->dangerousToDisable)
            ->keys()
            ->values()
            ->all();

        $selected = $this->multiSelect(
            'Which plugins should be marked as DANGEROUS to disable? (forces enabled)',
            $options,
            $dangerousDefaults
        );

        $managedPanels = config('filament-starter-minimal.managed_panels', []);

        foreach ($selected as $key) {
            foreach ($managedPanels as $panelId) {
                PanelPluginOverride::query()->updateOrCreate(
                    ['panel_id' => $panelId, 'plugin_key' => $key],
                    ['is_dangerous' => true, 'enabled' => true]
                );
            }
        }
    }

    /**
     * Apply config-derived defaults when running non-interactively.
     *
     * @param  array<string, PluginDefinition>  $plugins
     */
    protected function applyDefaultPanelOverrides(array $plugins): void
    {
        $managedPanels = config('filament-starter-minimal.managed_panels', []);

        foreach ($managedPanels as $panelId) {
            foreach ($plugins as $key => $definition) {
                $configEnabled = config("filament-starter-minimal.plugin_defaults.{$panelId}.{$key}.enabled");
                $enabled = $configEnabled ?? $definition->defaultEnabled;

                PanelPluginOverride::query()->updateOrCreate(
                    ['panel_id' => $panelId, 'plugin_key' => $key],
                    ['enabled' => (bool) $enabled],
                );
            }
        }
    }

    /**
     * @param  array<string, PluginDefinition>  $plugins
     * @return array<int, string>
     */
    protected function panelDefaults(string $panelId, array $plugins): array
    {
        $defaults = [];

        foreach ($plugins as $key => $definition) {
            $configEnabled = config("filament-starter-minimal.plugin_defaults.{$panelId}.{$key}.enabled");
            $enabled = $configEnabled ?? $definition->defaultEnabled;
            if ($enabled) {
                $defaults[] = $key;
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    protected function configPublishTags(): array
    {
        return [
            'bezhansalleh/filament-language-switch' => 'filament-language-switch-config',
            'bezhansalleh/filament-panel-switch' => 'filament-panel-switch-config',
            'awcodes/filament-quick-create' => 'filament-quick-create-config',
            'pxlrbt/filament-spotlight' => 'filament-spotlight-config',
            'charrafimed/global-search-modal' => 'global-search-modal-config',
            'shuvroroy/filament-spatie-laravel-health' => 'filament-spatie-laravel-health-config',
            'shuvroroy/filament-spatie-laravel-backup' => 'filament-spatie-laravel-backup-translations',
            'caresome/filament-auth-designer' => 'auth-designer-config',
            'guava/filament-knowledge-base' => 'filament-knowledge-base-config',
            'achyutn/filament-log-viewer' => 'filament-log-viewer-config',
            'tomatophp/filament-users' => 'filament-users-config',
        ];
    }

    /**
     * @return array<string, array{label: string, table: string, tag: string, provider: ?string, migration_glob: string}>
     */
    protected function pluginMigrationPublishers(): array
    {
        $publishers = [
            'filament-breezy' => [
                'label' => 'Filament Breezy',
                'table' => 'breezy_sessions',
                'tag' => 'filament-breezy-migrations',
                'provider' => class_exists(FilamentBreezyServiceProvider::class)
                    ? FilamentBreezyServiceProvider::class
                    : null,
                'migration_glob' => '*_create_breezy_sessions_table.php',
            ],
        ];

        if (class_exists(HealthServiceProvider::class)) {
            $publishers['filament-spatie-health'] = [
                'label' => 'Spatie Health',
                'table' => 'health_check_result_history_items',
                'tag' => 'health-migrations',
                'provider' => HealthServiceProvider::class,
                'migration_glob' => '*_create_health_tables.php',
            ];
        }

        return $publishers;
    }

    protected function setupShield(): void
    {
        if (! class_exists(FilamentShieldPlugin::class)) {
            return;
        }

        if (! $this->userModelSupportsRoles()) {
            $this->warn(
                'Skipping Filament Shield setup because the configured user model does not support roles. '.
                'Add Spatie\\Permission\\Traits\\HasRoles to your user model, then run shield:install / shield:setup manually.'
            );

            return;
        }

        $this->newLine();
        $this->info('--- Filament Shield Setup ---');

        if ($this->option('no-interaction')) {
            return;
        }

        $this->call('shield:setup');

        if ($this->confirm('Create a Shield super admin role now?', true)) {
            $this->call('shield:super-admin');
        }
    }

    protected function hasMigrationPublished(string $pattern): bool
    {
        $path = database_path('migrations');
        if (! is_dir($path)) {
            return false;
        }

        return (bool) glob($path.'/'.$pattern);
    }

    protected function hasTableForMigration(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function canRunPluginInstallCommand(string $pluginKey): bool
    {
        if ($pluginKey !== 'filament-shield') {
            return true;
        }

        if ($this->userModelSupportsRoles()) {
            return true;
        }

        $this->warn(
            'Skipping Filament Shield install because the configured user model does not support roles. '.
            'Add Spatie\\Permission\\Traits\\HasRoles to your user model, then rerun minimal-starter:install or shield:install.'
        );

        return false;
    }

    protected function userModelSupportsRoles(): bool
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || $userModel === '' || ! class_exists($userModel)) {
            return false;
        }

        return method_exists($userModel, 'assignRole')
            && method_exists($userModel, 'hasRole');
    }

    /**
     * @param  array<int|string, string>  $options
     * @param  array<int|string>  $default
     * @return array<int|string>
     */
    protected function multiSelect(string $label, array $options, array $default = []): array
    {
        return multiselect(
            label: $label,
            options: $options,
            default: $default,
            scroll: 12,
        );
    }
}
