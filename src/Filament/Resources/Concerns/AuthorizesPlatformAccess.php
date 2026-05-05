<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesPlatformAccess
{
    /**
     * Stable gate name for the package's super-admin authorization. Consumers
     * can override decisions via:
     *
     *   Gate::before(function ($user, $ability) {
     *       if ($ability === AuthorizesPlatformAccess::PLATFORM_GATE) {
     *           return /* your decision: true | false | null *\/;
     *       }
     *   });
     */
    public const PLATFORM_GATE = 'filament-starter-minimal:manage-platform';

    /**
     * Whether the current user holds the configured super-admin role,
     * delegated to a Laravel Gate so consumers can override centrally.
     */
    protected static function isSuperAdmin(): bool
    {
        return Gate::allows(self::PLATFORM_GATE);
    }
}
