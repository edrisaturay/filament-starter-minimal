<?php

namespace EdrisaTuray\FilamentStarterMinimal;

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterDoctorCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterSafeModeCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterUpdateCommand;
use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use EdrisaTuray\FilamentStarterMinimal\Registry\DefaultPluginCatalog;
use EdrisaTuray\FilamentStarterMinimal\Support\MediaManagerBridge;
use EdrisaTuray\FilamentStarterMinimal\Support\PlatformGate;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginRegistry;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Slimani\MediaManager\Models\File as SlimaniFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class FilamentStarterMinimalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-starter-minimal.php',
            'filament-starter-minimal'
        );

        $this->app->singleton(PluginRegistryContract::class, function ($app): PluginRegistry {
            $registry = new PluginRegistry;
            DefaultPluginCatalog::register($registry);

            return $registry;
        });

        $this->app->alias(PluginRegistryContract::class, PluginRegistry::class);

        // Single shared plugin instance so withPlugin()/withPlugins() registrations
        // accumulate predictably even when multiple panels include the plugin.
        $this->app->singleton(FilamentStarterMinimalPlugin::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->autoRegisterPanelPlugin();

        Gate::define(PlatformGate::MANAGE_PLATFORM, function (Authenticatable $user): bool {
            $role = config('filament-starter-minimal.superadmin.role', 'super_admin');

            return method_exists($user, 'hasRole') && $user->hasRole($role);
        });

        $this->registerMediaManagerBridge();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-starter-minimal.php' => config_path('filament-starter-minimal.php'),
            ], 'filament-starter-minimal-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-starter-minimal-migrations');

            $this->commands([
                MinimalStarterInstallCommand::class,
                MinimalStarterUpdateCommand::class,
                MinimalStarterDoctorCommand::class,
                MinimalStarterSafeModeCommand::class,
            ]);
        }
    }

    /**
     * Auto-register the FilamentStarterMinimalPlugin into every managed panel
     * without requiring consumers to add it manually to their PanelProvider.
     *
     * Consumers can opt out globally by setting
     * `filament-starter-minimal.auto_register_panel_plugin` to false, or by
     * limiting via `managed_panels` in config.
     */
    protected function autoRegisterPanelPlugin(): void
    {
        if (! config('filament-starter-minimal.auto_register_panel_plugin', true)) {
            return;
        }

        if (! class_exists(Panel::class)) {
            return;
        }

        Panel::configureUsing(function (Panel $panel): void {
            $managedPanels = config('filament-starter-minimal.managed_panels', []);

            if (! empty($managedPanels) && ! in_array($panel->getId(), $managedPanels, true)) {
                return;
            }

            $plugin = FilamentStarterMinimalPlugin::make();

            // Avoid double-registration if the consumer also added it manually.
            if (in_array($plugin, $panel->getPlugins(), true)) {
                return;
            }

            $panel->plugin($plugin);
        });
    }

    /**
     * Hook the Spatie ↔ Slimani bridge into the Media model's `created` event.
     * Skipped silently when either package isn't autoloadable, so the rest of
     * the starter still boots in slimmer installs.
     */
    protected function registerMediaManagerBridge(): void
    {
        if (! config('filament-starter-minimal.media_manager_bridge.enabled', true)) {
            return;
        }

        if (! class_exists(SpatieMedia::class) || ! class_exists(SlimaniFile::class)) {
            return;
        }

        $mediaModel = config('media-library.media_model', SpatieMedia::class);

        $mediaModel::created(function ($media): void {
            app(MediaManagerBridge::class)->handleMediaCreated($media);
        });
    }
}
