<?php

namespace EdrisaTuray\FilamentStarterMinimal;

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterDoctorCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterSafeModeCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterToolsCommand;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterUpdateCommand;
use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\FilamentStarterMinimalPlugin;
use EdrisaTuray\FilamentStarterMinimal\Filament\Users\UserActions;
use EdrisaTuray\FilamentStarterMinimal\Registry\DefaultPluginCatalog;
use EdrisaTuray\FilamentStarterMinimal\Support\MediaManagerBridge;
use EdrisaTuray\FilamentStarterMinimal\Support\PlatformGate;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Slimani\MediaManager\Models\File as SlimaniFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use STS\FilamentImpersonate\Facades\Impersonation;

class FilamentStarterMinimalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-starter-minimal.php',
            'filament-starter-minimal'
        );

        $this->mergeConfigFrom(__DIR__.'/../config/starter-minimal-tools.php', 'starter-minimal-tools');
        $this->app->register(LaravelToolsServiceProvider::class);

        $this->app->booting(function (): void {
            config([
                'filament-users.impersonate.enabled' => false,
                // TomatoPHP registers its banner unconditionally, so move it off rendered hooks.
                'filament-users.impersonate.banner.render_hook' => 'starter-minimal::disabled-impersonation-banner',
                'filament-users.resource.table.actions' => UserActions::class,
            ]);
        });

        $this->app->booted(function (): void {
            if ($this->app->routesAreCached()) {
                return;
            }

            foreach ($this->app['router']->getRoutes() as $route) {
                if ($route->getName() === 'filament-impersonate.leave') {
                    $action = $route->getAction();
                    unset($action['as']);
                    $route->setAction($action);
                }
            }

            // Both vendors claim the same leave URI. Register STS on a distinct URI after they boot.
            Route::get('starter-minimal/impersonation/leave', function () {
                if (! Impersonation::isImpersonating()) {
                    return redirect('/');
                }

                Impersonation::leave();

                return redirect(session()->pull('impersonate.back_to') ?? '/');
            })->name('filament-impersonate.leave')
                ->middleware(config('filament-impersonate.leave_middleware', 'web'));
        });

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

        Gate::define(PlatformGate::MANAGE_PLATFORM, function (Authenticatable $user): bool {
            $role = config('filament-starter-minimal.superadmin.role', 'super_admin');

            return method_exists($user, 'hasRole') && $user->hasRole($role);
        });

        $this->registerShieldIntegration();

        $this->registerMediaManagerBridge();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-starter-minimal.php' => config_path('filament-starter-minimal.php'),
            ], 'filament-starter-minimal-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-starter-minimal-migrations');

            $this->publishes([
                __DIR__.'/../stubs/seeders/ShieldSeeder.php' => database_path('seeders/ShieldSeeder.php'),
                __DIR__.'/../stubs/seeders/UserSeeder.php' => database_path('seeders/UserSeeder.php'),
            ], ['filament-starter-minimal', 'filament-starter-minimal-seeders']);

            $this->publishes([
                __DIR__.'/../stubs/resources/Users' => app_path('Filament/Resources/Users'),
            ], ['filament-starter-minimal', 'filament-starter-minimal-users']);

            $this->commands([
                MinimalStarterInstallCommand::class,
                MinimalStarterToolsCommand::class,
                MinimalStarterUpdateCommand::class,
                MinimalStarterDoctorCommand::class,
                MinimalStarterSafeModeCommand::class,
            ]);
        }
    }

    /**
     * Wire Shield up so the bundled plugins' resources are actually reachable:
     *
     * - `shield:generate` writes policies for vendor models (Activity, Menu,
     *   Exception, ...) outside Laravel's policy discovery, and some resources
     *   (e.g. filament-logger's) refuse access outright without a registered
     *   policy. enforcePolicies() registers those generated policies; explicit
     *   Gate::policy() registrations still win.
     * - Some vendor policies check permission names Shield never generates
     *   (api-service's TokenPolicy asks for `view_any_token`), so the super
     *   admin is granted every ability up front unless Shield already does so
     *   via its own `super_admin.define_via_gate`.
     */
    protected function registerShieldIntegration(): void
    {
        if (config('filament-starter-minimal.shield.enforce_policies', true) && $this->app->bound('filament-shield')) {
            $this->app->make('filament-shield')->enforcePolicies();
        }

        if (! config('filament-starter-minimal.superadmin.bypass_gates', true)
            || config('filament-shield.super_admin.define_via_gate', false)
        ) {
            return;
        }

        Gate::before(function (Authenticatable $user): ?bool {
            $role = config('filament-starter-minimal.superadmin.role', 'super_admin');

            return method_exists($user, 'hasRole') && $user->hasRole($role) ? true : null;
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
