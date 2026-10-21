<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;
use EdrisaTuray\FilamentStarterMinimal\Support\UserModelRolesSetup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Jeffgreco13\FilamentBreezy\FilamentBreezyServiceProvider;
use Spatie\Health\HealthServiceProvider;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\multiselect;

class MinimalStarterInstallCommand extends Command
{
    protected $signature = 'minimal-starter:install
                            {--laravel-tools=* : Optional Laravel tool keys to install}
                            {--skip-laravel-tools : Skip the optional Laravel tool selection}
                            {--publish-config : Publish filament-starter-minimal.php to the config directory}
                            {--publish-all : Publish all plugin configs/migrations without asking}
                            {--force : Re-run install even if it has been completed before}';

    protected $description = 'Publish config, migrate, sync panels, and set up Filament Starter Minimal plugins.';

    public function handle(): int
    {
        $this->info('Filament Starter Minimal: install');

        try {
            $model = config('filament-starter-minimal.users.model') ?: config('auth.providers.users.model');
            if (! is_string($model) || $model === '') {
                throw new \RuntimeException('Configure a valid user model before installing.');
            }

            if (app(UserModelRolesSetup::class)->ensure($model)) {
                $this->info("Added HasRoles to {$model}. Restarting installation to load the updated model...");

                return $this->restartInstallation();
            }

            $this->publishOwnConfig();
            $this->publishStarterAssets();

            $available = $this->availablePlugins();
            $options = collect($available)
                ->mapWithKeys(fn (PluginDefinition $d, string $key): array => [$key => $d->label])
                ->all();

            if ($available === []) {
                $this->warn('No registered plugins are installed. Install the suggested composer packages and re-run.');
            } else {
                // Plugin migrations must be published before the first migrate:
                // the snapshot below writes models that plugins observe (e.g.
                // filament-logger writes to activity_log on every model event).
                $this->publishPluginAssets($available, $options);
            }

            $this->info('Running migrations...');
            $exit = $this->call('migrate', ['--force' => true]);
            if ($exit !== 0) {
                $this->error('migrate failed with exit code '.$exit);

                return self::FAILURE;
            }

            $this->info('Snapshotting panels and syncing plugin registry rows...');
            PanelSnapshotManager::snapshot();

            if ($available !== []) {
                $this->activatePlugins($available, $options);
            }

            $this->setupShield();

            if (! $this->option('skip-laravel-tools')) {
                $arguments = ['tools' => $this->option('laravel-tools')];
                if ($this->option('no-interaction')) {
                    $arguments['--no-interaction'] = true;
                }
                if ($this->call('minimal-starter:tools', $arguments) !== self::SUCCESS) {
                    return self::FAILURE;
                }
            }

            $this->info('minimal-starter:install completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('minimal-starter:install failed: '.$e->getMessage());
            $this->line('Run `php artisan minimal-starter:doctor` for diagnostics.');

            return self::FAILURE;
        }
    }

    protected function restartInstallation(): int
    {
        $arguments = [PHP_BINARY, '-d', 'opcache.enable_cli=0', base_path('artisan'), 'minimal-starter:install'];

        foreach ($this->options() as $name => $value) {
            if ($value === true) {
                $arguments[] = '--'.$name;
            } elseif ($value !== false && $value !== null) {
                foreach ((array) $value as $item) {
                    $arguments[] = '--'.$name.'='.$item;
                }
            }
        }

        $process = new Process($arguments, base_path());
        $process->setTimeout(null);
        $process->setInput(STDIN);

        return $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));
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

    /**
     * @return array<string, PluginDefinition>
     */
    protected function availablePlugins(): array
    {
        return collect(app(PluginRegistryContract::class)->all())
            ->filter(fn (PluginDefinition $d): bool => $d->isAvailable())
            ->all();
    }

    /**
     * @param  array<string, PluginDefinition>  $plugins
     * @param  array<string, string>  $options
     */
    protected function activatePlugins(array $plugins, array $options): void
    {
        if ($this->option('no-interaction')) {
            $this->applyDefaultPanelOverrides($plugins);

            return;
        }

        $this->activatePluginsPerPanel($plugins, $options);
        $this->markDangerousPlugins($plugins, $options);
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
     * Shield is deliberately absent: `shield:install` requires a panel argument
     * and injects `FilamentShieldPlugin::make()` into the panel provider, which
     * duplicates the registry's own Shield registration. Its configs and the
     * permission migrations are published like any other plugin instead.
     *
     * @return array<string, string>
     */
    protected function pluginsWithInstallCommand(): array
    {
        return [
            // mradder/filament-logger uses Spatie's PackageServiceProvider, which
            // exposes an install command named `{package-name}:install`. It also
            // publishes spatie/laravel-activitylog's migrations.
            'filament-logger' => 'filament-logger:install',
        ];
    }

    protected function publishPluginConfig(PluginDefinition $definition): void
    {
        if ($definition->package === null) {
            return;
        }

        $tags = $this->configPublishTags()[$definition->package] ?? null;

        if ($tags !== null) {
            $this->info("Publishing config for {$definition->label}...");

            foreach ((array) $tags as $tag) {
                $this->call('vendor:publish', ['--tag' => $tag]);
            }

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
        foreach ($this->pluginMigrationPublishers()[$key] ?? [] as $publisher) {
            if ($this->hasTableForMigration($publisher['table'])) {
                continue;
            }

            if ($this->hasMigrationPublished($publisher['migration_glob'])) {
                continue;
            }

            $arguments = ['--tag' => $publisher['tag']];
            if (! empty($publisher['provider'])) {
                $arguments['--provider'] = $publisher['provider'];
            }

            $this->info("Publishing {$publisher['label']} migrations...");
            $this->call('vendor:publish', $arguments);
        }
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
     * @return array<string, string|list<string>>
     */
    protected function configPublishTags(): array
    {
        return [
            'bezhansalleh/filament-shield' => ['filament-shield-config', 'permission-config'],
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
     * Migration publishers per registry plugin key. Each publisher is skipped
     * when its table already exists or its migration was already published.
     *
     * @return array<string, list<array{label: string, table: string, tag: string, provider: ?string, migration_glob: string}>>
     */
    protected function pluginMigrationPublishers(): array
    {
        $publishers = [
            'filament-shield' => [[
                'label' => 'Spatie Permission',
                'table' => config('permission.table_names.roles', 'roles'),
                'tag' => 'permission-migrations',
                'provider' => null,
                'migration_glob' => '*_create_permission_tables.php',
            ]],
            'filament-breezy' => [[
                'label' => 'Filament Breezy',
                'table' => 'breezy_sessions',
                'tag' => 'filament-breezy-migrations',
                'provider' => class_exists(FilamentBreezyServiceProvider::class)
                    ? FilamentBreezyServiceProvider::class
                    : null,
                'migration_glob' => '*_create_breezy_sessions_table.php',
            ]],
            'filament-exceptions' => [[
                'label' => 'Filament Exceptions',
                'table' => 'filament_exceptions_table',
                'tag' => 'filament-exceptions-migrations',
                'provider' => null,
                'migration_glob' => '*_create_filament_exceptions_table.php',
            ]],
            'filament-menu-builder' => [[
                'label' => 'Filament Menu Builder',
                'table' => config('filament-menu-builder.tables.menus', 'menus'),
                'tag' => 'filament-menu-builder-migrations',
                'provider' => null,
                'migration_glob' => '*_create_menus_table.php',
            ]],
            'filament-media-manager' => [
                [
                    'label' => 'Spatie Media Library',
                    'table' => 'media',
                    'tag' => 'medialibrary-migrations',
                    'provider' => null,
                    'migration_glob' => '*_create_media_table.php',
                ],
                [
                    'label' => 'Media Manager',
                    'table' => 'media_folders',
                    'tag' => 'media-manager-migrations',
                    'provider' => null,
                    'migration_glob' => '*_create_media_manager_tables.php',
                ],
            ],
            'filament-api-service' => [[
                'label' => 'Laravel Sanctum',
                'table' => 'personal_access_tokens',
                'tag' => 'sanctum-migrations',
                'provider' => null,
                'migration_glob' => '*_create_personal_access_tokens_table.php',
            ]],
        ];

        if (class_exists(HealthServiceProvider::class)) {
            $publishers['filament-spatie-health'] = [[
                'label' => 'Spatie Health',
                'table' => 'health_check_result_history_items',
                'tag' => 'health-migrations',
                'provider' => HealthServiceProvider::class,
                'migration_glob' => '*_create_health_tables.php',
            ]];
        }

        return $publishers;
    }

    /**
     * Generates Shield permissions and policies for every managed panel that is
     * registered with Filament. Configs and permission tables are already in
     * place at this point (published with the plugin assets, then migrated).
     */
    protected function setupShield(): void
    {
        if (! $this->userModelSupportsRoles()) {
            throw new \RuntimeException('Add Spatie\\Permission\\Traits\\HasRoles to the configured user model before installing.');
        }

        foreach (['ShieldSeeder', 'UserSeeder'] as $seeder) {
            $class = 'Database\\Seeders\\'.$seeder;

            if (! class_exists($class)) {
                require_once database_path('seeders/'.$seeder.'.php');
            }

            if ($this->call('db:seed', ['--class' => $class, '--force' => true]) !== self::SUCCESS) {
                throw new \RuntimeException("{$seeder} failed.");
            }
        }
    }

    protected function publishStarterAssets(): void
    {
        foreach (['filament-starter-minimal-seeders', 'filament-starter-minimal-users', 'filament-users-config', 'filament-impersonate-config'] as $tag) {
            if ($this->call('vendor:publish', ['--tag' => $tag]) !== self::SUCCESS) {
                throw new \RuntimeException("Publishing [{$tag}] failed.");
            }
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

    protected function userModelSupportsRoles(): bool
    {
        $userModel = config('filament-starter-minimal.users.model') ?: config('auth.providers.users.model');

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
