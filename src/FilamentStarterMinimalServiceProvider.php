<?php

namespace EdrisaTuray\FilamentStarterMinimal;

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterDoctorCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterSafeModeCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterUpdateCommand;
use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns\AuthorizesPlatformAccess;
use EdrisaTuray\FilamentStarterMinimal\Registry\DefaultPluginCatalog;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        Gate::define(AuthorizesPlatformAccess::PLATFORM_GATE, function (Authenticatable $user): bool {
            $role = config('filament-starter-minimal.superadmin.role', 'super_admin');

            return method_exists($user, 'hasRole') && $user->hasRole($role);
        });

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
}
