<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns;

use EdrisaTuray\FilamentStarterMinimal\Support\PlatformGate;
use Illuminate\Support\Facades\Gate;

trait AuthorizesPlatformAccess
{
    /**
     * Stable gate name for the package's super-admin authorization. Consumers
     * can override decisions via:
     *
     *   Gate::before(function ($user, $ability) {
     *       if ($ability === PlatformGate::MANAGE_PLATFORM) {
     *           return /* your decision: true | false | null *\/;
     *       }
     *   });
     */

    /**
     * Whether the current user holds the configured super-admin role,
     * delegated to a Laravel Gate so consumers can override centrally.
     */
    protected static function isSuperAdmin(): bool
    {
        return Gate::allows(PlatformGate::MANAGE_PLATFORM);
    }
}
