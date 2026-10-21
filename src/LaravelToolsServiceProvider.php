<?php

namespace EdrisaTuray\FilamentStarterMinimal;

use EdrisaTuray\FilamentStarterMinimal\Http\Middleware\AuthorizeLaravelTool;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Laravel\Telescope\Telescope;

class LaravelToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booting(function (): void {
            foreach (['horizon', 'telescope', 'pulse'] as $tool) {
                if (config($tool) !== null) {
                    config([$tool.'.middleware' => array_values(array_unique([
                        ...(array) config($tool.'.middleware', ['web']),
                        AuthorizeLaravelTool::class,
                    ]))]);
                }
            }
            foreach (config('starter-minimal-socialite', []) as $provider => $settings) {
                config(['services.'.$provider => array_replace($settings, config('services.'.$provider, []))]);
            }
        });

        $this->app->booted(function (): void {
            foreach (['viewHorizon', 'viewTelescope', 'viewPulse'] as $ability) {
                Gate::define($ability, fn ($user): bool => AuthorizeLaravelTool::allows($user));
            }
            $authorize = fn ($request): bool => AuthorizeLaravelTool::allows(
                $request->user(config('starter-minimal-tools.guard') ?: config('auth.defaults.guard', 'web')),
            );
            if (class_exists(Horizon::class)) {
                Horizon::auth($authorize);
            }
            if (class_exists(Telescope::class)) {
                Telescope::auth($authorize);
                Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
                Telescope::hideRequestHeaders(['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token']);
                Telescope::filter(fn ($entry): bool => app()->environment('local')
                    || $entry->isReportableException() || $entry->isFailedRequest()
                    || $entry->isFailedJob() || $entry->isScheduledTask() || $entry->hasMonitoredTag());
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $enabled = config('starter-minimal-tools.enabled', []);
            if (in_array('horizon', $enabled, true) && class_exists(Horizon::class)) {
                $schedule->command('horizon:snapshot')->everyFiveMinutes();
            }
            if (in_array('telescope', $enabled, true) && class_exists(Telescope::class)) {
                $schedule->command('telescope:prune', ['--hours' => config('starter-minimal-tools.telescope_retention_hours', 48)])->daily();
            }
        });
    }
}
