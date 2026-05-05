<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MinimalSafeMode
{
    public static function isActive(): bool
    {
        if (config('app.env') === 'testing') {
            return false;
        }

        return (bool) config('filament-starter-minimal.safe_mode', false)
            || Cache::has('starter_minimal_safe_mode_active');
    }

    public static function activate(): void
    {
        Cache::forever('starter_minimal_safe_mode_active', true);
        Log::info('starter_minimal.safe_mode.on', ['actor_user_id' => auth()->id()]);
    }

    public static function deactivate(): void
    {
        Cache::forget('starter_minimal_safe_mode_active');
        Log::info('starter_minimal.safe_mode.off', ['actor_user_id' => auth()->id()]);
    }
}
