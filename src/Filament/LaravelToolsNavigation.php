<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament;

use EdrisaTuray\FilamentStarterMinimal\Http\Middleware\AuthorizeLaravelTool;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

class LaravelToolsNavigation
{
    public static function register(Panel $panel): void
    {
        foreach (['horizon' => 'horizon.index', 'telescope' => 'telescope', 'pulse' => 'pulse'] as $tool => $route) {
            if (! in_array($tool, config('starter-minimal-tools.enabled', []), true)) {
                continue;
            }
            $panel->navigationItems([
                NavigationItem::make(ucfirst($tool))
                    ->group(config('starter-minimal-tools.navigation_group', 'Laravel Tools'))
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (): string => Route::has($route) ? route($route) : '#', shouldOpenInNewTab: true)
                    ->visible(fn (): bool => Route::has($route) && AuthorizeLaravelTool::allows(Filament::auth()->user())),
            ]);
        }
    }
}
